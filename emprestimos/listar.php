<?php
$titulo_pagina = "Empréstimos";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$hoje = date('Y-m-d');

// ===============================
// PAGINAÇÃO
// ===============================
$por_pagina = 10;
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $por_pagina;

// ===============================
// FILTROS
// ===============================
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

// filtro vindo do dashboard
$f = trim($_GET['f'] ?? '');
if ($status === '' && $f !== '') {
  if ($f === 'abertos') $status = 'aberto';
  if ($f === 'atrasados') $status = 'atrasado';
}

// ===============================
// WHERE / HAVING (modelo novo)
// ===============================
$where = [];
$params = [];
$types = "";

if ($q !== '') {
  $where[] = "(u.nome LIKE ? OR l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?)";
  $like = "%{$q}%";
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types .= "ssss";
}

$having = [];

if ($status === 'atrasado') {
  $where[] = "(e.data_prevista IS NOT NULL AND e.data_prevista < ?)";
  $params[] = $hoje;
  $types .= "s";
  $having[] = "SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) > 0";
} elseif ($status === 'aberto') {
  $where[] = "(e.data_prevista IS NULL OR e.data_prevista >= ?)";
  $params[] = $hoje;
  $types .= "s";
  $having[] = "SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) > 0";
} elseif ($status === 'devolvido') {
  $having[] = "SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) = 0";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";
$havingSql = count($having) ? ("HAVING " . implode(" AND ", $having)) : "";

// ===============================
// COUNT
// ===============================
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM (
    SELECT e.id
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.id_usuario
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    JOIN livros l ON l.id = ei.id_livro
    $whereSql
    GROUP BY e.id
    $havingSql
  ) t
";
$stmtC = mysqli_prepare($conn, $sqlCount);
if ($types !== "") mysqli_stmt_bind_param($stmtC, $types, ...$params);
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
$total = (int)(mysqli_fetch_assoc($resC)['total'] ?? 0);

$total_paginas = max(1, (int)ceil($total / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;
$offset = ($pagina - 1) * $por_pagina;

// ===============================
// SELECT (agregado)
// ===============================
$sql = "
  SELECT
    e.id,
    e.data_emprestimo,
    e.data_prevista,
    u.nome AS usuario_nome,
    COUNT(ei.id) AS qtd_itens,
    SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) AS abertos,
    MAX(ei.data_devolucao) AS ultima_devolucao,
    GROUP_CONCAT(DISTINCT l.titulo ORDER BY l.titulo SEPARATOR ' | ') AS livros_titulos
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  JOIN livros l ON l.id = ei.id_livro
  $whereSql
  GROUP BY e.id
  $havingSql
  ORDER BY e.id DESC
  LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $sql);

$types2 = $types . "ii";
$params2 = array_merge($params, [$por_pagina, $offset]);

mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

// ===============================
// helpers
// ===============================
$inicio = ($total === 0) ? 0 : ($offset + 1);
$fim = min($offset + $por_pagina, $total);

function montaLink($p, $q, $status)
{
  $qs = [];
  if ($p) $qs['p'] = $p;
  if ($q !== '') $qs['q'] = $q;
  if ($status !== '') $qs['status'] = $status;
  return "listar.php?" . http_build_query($qs);
}
function statusLabel($s)
{
  if ($s === 'aberto') return "Abertos";
  if ($s === 'atrasado') return "Atrasados";
  if ($s === 'devolvido') return "Devolvidos";
  return "Todos";
}
function badgeStatus(bool $devolvido, bool $atrasado)
{
  if ($devolvido) return "<span class='badge-status badge-done'><i class='bi bi-check-circle'></i> Devolvido</span>";
  if ($atrasado)  return "<span class='badge-status badge-late'><i class='bi bi-exclamation-circle'></i> Atrasado</span>";
  return "<span class='badge-status badge-open'><i class='bi bi-clock-history'></i> Aberto</span>";
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Empréstimos</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Empréstimo
      </a>
    </div>

    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="text-muted small">Filtro:</span>
      <span class="badge rounded-pill text-bg-light" style="border:1px solid #e7e1d6;">
        <?= htmlspecialchars(statusLabel($status)) ?>
      </span>

      <?php if ($status !== '' || $q !== '') { ?>
        <a class="small text-decoration-none ms-2" href="listar.php">Limpar filtros</a>
      <?php } ?>
    </div>

    <div class="row g-2 align-items-center mb-3">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input
            id="search"
            type="text"
            class="form-control"
            placeholder="Buscar por usuário, livro, autor ou ISBN..."
            autocomplete="off"
            value="<?= htmlspecialchars($q) ?>">
        </div>
      </div>

      <div class="col-md-3">
        <select id="status" class="form-select">
          <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
          <option value="aberto" <?= $status === 'aberto' ? 'selected' : '' ?>>Abertos</option>
          <option value="atrasado" <?= $status === 'atrasado' ? 'selected' : '' ?>>Atrasados</option>
          <option value="devolvido" <?= $status === 'devolvido' ? 'selected' : '' ?>>Devolvidos</option>
        </select>
      </div>
    </div>

    <div class="table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th class="col-id">ID</th>
              <th>Usuário</th>
              <th>Livro(s)</th>
              <th class="col-ano">Empréstimo</th>
              <th class="col-ano">Prevista</th>
              <th class="col-ano">Devolução</th>
              <th class="col-status">Status</th>
              <th class="text-end col-acoes">Ações</th>
            </tr>
          </thead>

          <tbody id="tbody-emprestimos">
            <?php if ($total === 0) { ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">Nenhum empréstimo encontrado.</td>
              </tr>
            <?php } ?>

            <?php while ($e = mysqli_fetch_assoc($r)) {
              $id = (int)$e['id'];
              $qtd = (int)($e['qtd_itens'] ?? 0);
              $abertos = (int)($e['abertos'] ?? 0);

              $devolvido = ($abertos === 0);
              $prevista = $e['data_prevista'] ?? null;
              $atrasado = (!$devolvido && !empty($prevista) && $prevista < $hoje);

              $titulos = (string)($e['livros_titulos'] ?? '—');

              // ✅ AQUI: o que você pediu
              // se estiver em aberto, mostra quantos ainda faltam devolver
              if ($devolvido) {
                $livroMostrar = ($qtd > 1) ? ($qtd . " livros") : $titulos;
              } else {
                $livroMostrar = ($abertos === 1) ? "1 livro" : ($abertos . " livros");
              }

              $usuarioAttr = htmlspecialchars($e['usuario_nome'] ?? '', ENT_QUOTES);
              $livroAttr   = htmlspecialchars($livroMostrar, ENT_QUOTES);
            ?>
              <tr class="row-click" data-emprestimo-id="<?= $id ?>">
                <td class="text-muted fw-semibold">#<?= $id ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($e['usuario_nome'] ?? '-') ?></td>
                <td><?= htmlspecialchars($livroMostrar) ?></td>

                <td><?= htmlspecialchars($e['data_emprestimo'] ?? '-') ?></td>
                <td><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></td>
                <td><?= htmlspecialchars($e['ultima_devolucao'] ?? '-') ?></td>

                <td><?= badgeStatus($devolvido, $atrasado) ?></td>

                <td class="text-end">
                  <?php if (!$devolvido) { ?>
                    <a class="icon-btn icon-btn--edit"
                      href="renovar.php?id=<?= $id ?>"
                      title="Renovar"
                      data-stop-row="1">
                      <i class="bi bi-arrow-repeat"></i>
                    </a>

                    <a class="icon-btn icon-btn--edit"
                      href="#"
                      title="Detalhes"
                      data-action="detalhes"
                      data-id="<?= $id ?>"
                      data-stop-row="1">
                      <i class="bi bi-check2-circle"></i>
                    </a>

                    <!-- ✅ trocar "editar" por cancelar -->
                    <a class="icon-btn icon-btn--del"
                      href="cancelar.php?id=<?= $id ?>"
                      title="Cancelar empréstimo"
                      data-stop-row="1"
                      onclick="return confirm('Cancelar este empréstimo?');">
                      <i class="bi bi-x-circle"></i>
                    </a>
                  <?php } ?>

                  <a class="icon-btn icon-btn--del"
                    href="#"
                    data-action="excluir"
                    data-id="<?= $id ?>"
                    data-livro="<?= $livroAttr ?>"
                    data-usuario="<?= $usuarioAttr ?>"
                    title="Excluir"
                    data-stop-row="1">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex flex-column align-items-center gap-2 mt-3">
      <nav id="paginacao-emprestimos">
        <ul class="pagination pagination-green mb-0">
          <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= montaLink($pagina - 1, $q, $status) ?>">Anterior</a>
          </li>

          <?php
          $janela = 2;
          $ini = max(1, $pagina - $janela);
          $fimPag = min($total_paginas, $pagina + $janela);
          for ($p = $ini; $p <= $fimPag; $p++) {
          ?>
            <li class="page-item <?= ($p === $pagina) ? 'active' : '' ?>">
              <a class="page-link" href="<?= montaLink($p, $q, $status) ?>"><?= $p ?></a>
            </li>
          <?php } ?>

          <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= montaLink($pagina + 1, $q, $status) ?>">Próxima</a>
          </li>
        </ul>
      </nav>

      <div id="resumo-emprestimos" class="text-muted small">
        Mostrando <?= $inicio ?>–<?= $fim ?> de <?= $total ?> empréstimos
      </div>
    </div>

  </div>
</div>

<!-- =========================================================
     MODAL: DETALHES DO EMPRÉSTIMO
     ========================================================= -->
<div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-receipt me-2"></i>Detalhes do empréstimo
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body" id="detalhesBody">
        <div class="text-muted">Carregando...</div>
      </div>

      <!-- ✅ sem botão de fechar aqui, só o X do header -->
      <div class="modal-footer d-flex gap-2 justify-content-end" id="detalhesFooter"></div>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: EXCLUIR
     ========================================================= -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-trash me-2"></i>Excluir empréstimo
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2 text-muted">Você está prestes a excluir:</div>
        <div class="fw-semibold" id="modalExcLivro">Empréstimo</div>
        <div class="text-muted small mt-1">Usuário: <span id="modalExcUsuario">Usuário</span></div>

        <div class="alert alert-warning mt-3 mb-0" style="border-radius:12px;">
          Atenção: excluir remove o registro do empréstimo do sistema.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-danger" id="btnConfirmarExcluir">
          <i class="bi bi-trash me-1"></i> Excluir
        </a>
      </div>
    </div>
  </div>
</div>

<style>
  tr.row-click {
    cursor: pointer;
  }

  tr.row-click:hover {
    background: rgba(0, 0, 0, .02);
  }
</style>

<script>
  (function() {
    const input = document.getElementById('search');
    const status = document.getElementById('status');
    const tbody = document.getElementById('tbody-emprestimos');
    const pagWrap = document.getElementById('paginacao-emprestimos');
    const resumo = document.getElementById('resumo-emprestimos');

    const modalDetalhesEl = document.getElementById('modalDetalhes');
    const detalhesBody = document.getElementById('detalhesBody');
    const detalhesFooter = document.getElementById('detalhesFooter');

    const modalExcluirEl = document.getElementById('modalExcluir');
    const modalExcLivro = document.getElementById('modalExcLivro');
    const modalExcUsuario = document.getElementById('modalExcUsuario');
    const btnExc = document.getElementById('btnConfirmarExcluir');

    let timer = null;

    function getModalInstance(el) {
      if (!(window.bootstrap && typeof bootstrap.Modal === "function")) return null;
      return bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    }

    async function load(p = 1) {
      const q = input.value.trim();
      const s = status.value;

      const url = new URL('buscar.php', window.location.href);
      url.searchParams.set('q', q);
      url.searchParams.set('status', s);
      url.searchParams.set('p', p);

      try {
        const res = await fetch(url.toString(), {
          headers: { 'X-Requested-With': 'fetch' }
        });
        const data = await res.json();
        tbody.innerHTML = data.rows;
        pagWrap.innerHTML = data.pagination;
        resumo.textContent = data.summary;
      } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">Erro ao buscar. Tente novamente.</td></tr>`;
      }
    }

    function debounceLoad() {
      clearTimeout(timer);
      timer = setTimeout(() => load(1), 250);
    }

    input.addEventListener('input', debounceLoad);
    status.addEventListener('change', () => load(1));

    pagWrap.addEventListener('click', (ev) => {
      const a = ev.target.closest('a');
      if (!a) return;
      ev.preventDefault();
      const url = new URL(a.href);
      const p = parseInt(url.searchParams.get('p') || '1', 10);
      load(p);
    });

    // ✅ fixa estado fora pra não ficar resetando dentro
    let currentPage = <?= (int)$pagina ?>;
    let currentEmprestimoId = null;
    let modalDetalhesListenerAdded = false;

    async function abrirDetalhes(id) {
      currentEmprestimoId = id;

      const modal = getModalInstance(modalDetalhesEl);
      if (!modal) {
        window.location.href = `cancelar.php?id=${encodeURIComponent(id)}`;
        return;
      }

      detalhesBody.innerHTML = "<div class='text-muted'>Carregando...</div>";
      detalhesFooter.innerHTML = ""; // ✅ sem botão de fechar extra
      modal.show();

      // ✅ adiciona 1x só o listener do modal
      if (!modalDetalhesListenerAdded) {
        modalDetalhesListenerAdded = true;

        modalDetalhesEl.addEventListener('click', async (ev) => {
          const btn = ev.target.closest('.js-devolver-item');
          if (!btn) return;

          const itemId = btn.getAttribute('data-item-id');
          if (!itemId) return;

          btn.disabled = true;
          btn.innerHTML = "<span class='spinner-border spinner-border-sm me-2'></span>Devolvendo...";

          try {
            const resp = await fetch(`devolver_item.php?ajax=1&item_id=${encodeURIComponent(itemId)}`, {
              headers: { "X-Requested-With": "fetch" },
              cache: "no-store"
            });

            const data = await resp.json();
            if (!data.ok) {
              alert(data.msg || "Não foi possível devolver.");
              return;
            }

            if (currentEmprestimoId) await abrirDetalhes(currentEmprestimoId);
            await load(currentPage);

          } catch (e) {
            alert("Erro ao devolver. Tente novamente.");
          } finally {
            btn.disabled = false;
            btn.innerHTML = "<i class='bi bi-check2-circle me-1'></i> Devolver";
          }
        });
      }

      try {
        const url = new URL('buscar.php', window.location.href);
        url.searchParams.set('action', 'detalhes');
        url.searchParams.set('id', id);

        const res = await fetch(url.toString(), {
          headers: { 'X-Requested-With': 'fetch' },
          cache: 'no-store'
        });

        const data = await res.json();

        if (!data.ok) {
          detalhesBody.innerHTML = data.html || "<div class='text-muted'>Não foi possível carregar.</div>";
          return;
        }

        detalhesBody.innerHTML = data.html;
        detalhesFooter.innerHTML = data.footer || ""; // ✅ aqui você controla no buscar.php o que vai no footer

      } catch (e) {
        console.error(e);
        detalhesBody.innerHTML = "<div class='text-muted'>Erro ao carregar detalhes.</div>";
      }
    }

    tbody.addEventListener('click', (e) => {
      const stop = e.target.closest("[data-stop-row='1']");
      const btnDetalhes = e.target.closest("[data-action='detalhes']");
      const btnExcluir = e.target.closest("[data-action='excluir']");
      const anyLinkOrButton = e.target.closest("a, button");

      if (stop) return;

      if (btnDetalhes) {
        e.preventDefault();
        abrirDetalhes(btnDetalhes.getAttribute("data-id"));
        return;
      }

      if (btnExcluir) {
        e.preventDefault();

        const id = btnExcluir.getAttribute('data-id');
        const livro = btnExcluir.getAttribute('data-livro') || 'Empréstimo';
        const usuario = btnExcluir.getAttribute('data-usuario') || 'Usuário';

        const modal = getModalInstance(modalExcluirEl);
        if (!modal) {
          window.location.href = `excluir.php?id=${encodeURIComponent(id)}`;
          return;
        }

        modalExcLivro.textContent = livro;
        modalExcUsuario.textContent = usuario;
        btnExc.href = `excluir.php?id=${encodeURIComponent(id)}`;
        modal.show();
        return;
      }

      if (anyLinkOrButton) return;

      const tr = e.target.closest("tr[data-emprestimo-id]");
      if (!tr) return;

      const id = tr.getAttribute("data-emprestimo-id");
      abrirDetalhes(id);
    });

    // ✅ mantém página atual quando você navegar pela paginação via fetch
    pagWrap.addEventListener('click', (ev) => {
      const a = ev.target.closest('a');
      if (!a) return;
      const url = new URL(a.href);
      currentPage = parseInt(url.searchParams.get('p') || '1', 10);
    });

  })();
</script>

<?php include("../includes/footer.php"); ?>
