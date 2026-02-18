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

// por padrão, esconde cancelados
$where[] = "(IFNULL(e.cancelado,0) = 0)";

if ($q !== '') {
  $where[] = "(u.nome LIKE ? OR l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?)";
  $like = "%{$q}%";
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types .= "ssss";
}

// abertos = devolvido=0 E perdido=0
$exprAbertos = "SUM(CASE WHEN ei.devolvido = 0 AND IFNULL(ei.perdido,0)=0 THEN 1 ELSE 0 END)";
$exprPerdidos = "SUM(CASE WHEN IFNULL(ei.perdido,0)=1 THEN 1 ELSE 0 END)";

$having = [];

if ($status === 'atrasado') {
  $where[] = "(e.data_prevista IS NOT NULL AND e.data_prevista < ?)";
  $params[] = $hoje;
  $types .= "s";
  // atrasado = tem item em aberto e NÃO tem item perdido
  $having[] = "{$exprAbertos} > 0";
  $having[] = "{$exprPerdidos} = 0";
} elseif ($status === 'aberto') {
  $where[] = "(e.data_prevista IS NULL OR e.data_prevista >= ?)";
  $params[] = $hoje;
  $types .= "s";
  // aberto = tem item em aberto e NÃO tem item perdido
  $having[] = "{$exprAbertos} > 0";
  $having[] = "{$exprPerdidos} = 0";
} elseif ($status === 'devolvido') {
  // devolvido = não tem itens em aberto e NÃO tem item perdido
  $having[] = "{$exprAbertos} = 0";
  $having[] = "{$exprPerdidos} = 0";
} elseif ($status === 'perdido') {
  // perdido = tem pelo menos 1 item perdido (independente de ter abertos)
  $having[] = "{$exprPerdidos} > 0";
}


$whereSql  = count($where)  ? ("WHERE "  . implode(" AND ", $where))  : "";
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
$total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC))['total'] ?? 0);

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
    {$exprAbertos} AS abertos,
    {$exprPerdidos} AS perdidos,
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
  if ($s === 'perdido') return "Perdidos";
  return "Todos";
}
function badgeStatusLoan(bool $devolvido, bool $atrasado, bool $temPerdido)
{
  if ($temPerdido) return "<span class='badge-status badge-lost'><i class='bi bi-exclamation-triangle'></i> Perdido</span>";
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
          <option value="perdido" <?= $status === 'perdido' ? 'selected' : '' ?>>Perdidos</option>
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
              $perdidos = (int)($e['perdidos'] ?? 0);

              $encerrado = ($abertos === 0);
              $prevista = $e['data_prevista'] ?? null;

              $atrasado = (!$encerrado && !empty($prevista) && $prevista < $hoje);
              $temPerdido = ($perdidos > 0);

              $titulos = (string)($e['livros_titulos'] ?? '—');

              // coluna "Livro(s)"
              if ($encerrado) {
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

                <td><?= badgeStatusLoan($encerrado, $atrasado, $temPerdido) ?></td>

                <td class="text-end">
                  <?php if (!$encerrado) { ?>
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

                    <!-- Cancelar (você usa excluir.php como cancelar) -->
                    <a class="icon-btn icon-btn--del"
                      href="#"
                      title="Cancelar empréstimo"
                      data-action="cancelar"
                      data-id="<?= $id ?>"
                      data-livro="<?= $livroAttr ?>"
                      data-usuario="<?= $usuarioAttr ?>"
                      data-stop-row="1">
                      <i class="bi bi-x-circle"></i>
                    </a>
                  <?php } else { ?>
                    <span class="text-muted small">—</span>
                  <?php } ?>
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
        <!-- só o X -->
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body" id="detalhesBody">
        <div class="text-muted">Carregando...</div>
      </div>

      <!-- footer é opcional, mas se o buscar.php mandar botões a gente coloca -->
      <div class="modal-footer d-flex gap-2 justify-content-end" id="detalhesFooter"></div>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: CANCELAR (usa excluir.php)
     ========================================================= -->
<div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-x-circle me-2"></i>Cancelar empréstimo
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2 text-muted">Você está prestes a cancelar:</div>
        <div class="fw-semibold" id="modalCanLivro">Empréstimo</div>
        <div class="text-muted small mt-1">Usuário: <span id="modalCanUsuario">Usuário</span></div>

        <div class="alert alert-warning mt-3 mb-0" style="border-radius:12px;">
          Isso não apaga o histórico, só marca como cancelado.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:999px;">Voltar</button>
        <a href="#" class="btn btn-warning" id="btnConfirmarCancelar" style="border-radius:999px;">
          <i class="bi bi-x-circle me-1"></i> Cancelar
        </a>
      </div>
    </div>
  </div>
</div>

<!-- =========================================================
     MODAL: PERDIDO (bonito, sem confirm feio)
     ========================================================= -->
<div class="modal fade" id="modalPerdido" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-exclamation-triangle me-2 text-danger"></i>Marcar como perdido
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="text-muted mb-2">Você tem certeza?</div>
        <div class="fw-semibold" id="perdidoTitulo">Livro</div>

        <div class="alert alert-danger mt-3 mb-0" style="border-radius:12px;">
          Isso vai descontar <b>1 exemplar</b> do total do acervo desse livro.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:999px;">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmarPerdido" style="border-radius:999px;">
          <i class="bi bi-exclamation-triangle me-1"></i> Confirmar
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  tr.row-click { cursor: pointer; }
  tr.row-click:hover { background: rgba(0, 0, 0, .02); }

  /* Badge do PERDIDO (pra não ficar igual atrasado) */
  .badge-status.badge-lost{
    background: rgba(220,53,69,.12);
    border: 1px solid rgba(220,53,69,.35);
    color: #dc3545;
  }
</style>

<script>
(function () {
  const input = document.getElementById('search');
  const status = document.getElementById('status');
  const tbody = document.getElementById('tbody-emprestimos');
  const pagWrap = document.getElementById('paginacao-emprestimos');
  const resumo = document.getElementById('resumo-emprestimos');

  const modalDetalhesEl = document.getElementById('modalDetalhes');
  const detalhesBody = document.getElementById('detalhesBody');
  const detalhesFooter = document.getElementById('detalhesFooter');

  const modalCancelarEl = document.getElementById('modalCancelar');
  const modalCanLivro = document.getElementById('modalCanLivro');
  const modalCanUsuario = document.getElementById('modalCanUsuario');
  const btnCancelar = document.getElementById('btnConfirmarCancelar');

  const modalPerdidoEl = document.getElementById('modalPerdido');
  const perdidoTitulo = document.getElementById('perdidoTitulo');
  const btnConfirmarPerdido = document.getElementById('btnConfirmarPerdido');

  let timer = null;

  function getModalInstance(el) {
    if (!(window.bootstrap && typeof bootstrap.Modal === "function")) return null;
    return bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
  }

  // estado pra recarregar
  let currentPage = <?= (int)$pagina ?>;
  let currentEmprestimoId = null;

  // controle do "Perdido"
  let pendingPerdidoItemId = null;
  let pendingPerdidoTitulo = "Livro";

  async function load(p = 1) {
    currentPage = p;

    const q = input.value.trim();
    const s = status.value;

    const url = new URL('buscar.php', window.location.href);
    url.searchParams.set('q', q);
    url.searchParams.set('status', s);
    url.searchParams.set('p', p);

    try {
      const res = await fetch(url.toString(), { headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();

      tbody.innerHTML = data.rows ?? `<tr><td colspan="8" class="text-center text-muted py-4">Nada encontrado.</td></tr>`;
      pagWrap.innerHTML = data.pagination ?? "";
      resumo.textContent = data.summary ?? "";
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

  async function abrirDetalhes(id) {
    currentEmprestimoId = id;

    const modal = getModalInstance(modalDetalhesEl);
    if (!modal) {
      window.location.href = `devolver.php?id=${encodeURIComponent(id)}`;
      return;
    }

    detalhesBody.innerHTML = "<div class='text-muted'>Carregando...</div>";
    detalhesFooter.innerHTML = "";
    modal.show();

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
        detalhesFooter.innerHTML = "";
        return;
      }

      detalhesBody.innerHTML = data.html || "";
      detalhesFooter.innerHTML = data.footer || "";

      // remove qualquer "Fechar" duplicado vindo do buscar.php
      detalhesFooter.querySelectorAll('[data-bs-dismiss="modal"]').forEach(el => el.remove());

    } catch (e) {
      console.error(e);
      detalhesBody.innerHTML = "<div class='text-muted'>Erro ao carregar detalhes.</div>";
      detalhesFooter.innerHTML = "";
    }
  }

  // =========================================================
  // AÇÕES dentro do MODAL de detalhes (delegação)
  // =========================================================
  modalDetalhesEl.addEventListener('click', async (ev) => {
    // DEVOLVER ITEM
    const btnDev = ev.target.closest('.js-devolver-item');
    if (btnDev) {
      const itemId = btnDev.getAttribute('data-item-id');
      if (!itemId) return;

      btnDev.disabled = true;
      const old = btnDev.innerHTML;
      btnDev.innerHTML = "<span class='spinner-border spinner-border-sm me-2'></span>Devolvendo...";

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
        btnDev.disabled = false;
        btnDev.innerHTML = old;
      }
      return;
    }

    // PERDER ITEM (abre modal bonitinho)
    const btnPerder = ev.target.closest('.js-perder-item');
    if (btnPerder) {
      const itemId = btnPerder.getAttribute('data-item-id');
      if (!itemId) return;

      pendingPerdidoItemId = itemId;
      pendingPerdidoTitulo = btnPerder.getAttribute('data-titulo') || "Livro";

      perdidoTitulo.textContent = pendingPerdidoTitulo;

      const m = getModalInstance(modalPerdidoEl);
      if (!m) {
        // fallback simples
        if (confirm("Marcar como PERDIDO? Isso desconta 1 do total do acervo desse livro.")) {
          btnConfirmarPerdido.click();
        }
        return;
      }

      m.show();
      return;
    }
  });

  // Confirma "Perdido"
  btnConfirmarPerdido.addEventListener('click', async () => {
    if (!pendingPerdidoItemId) return;

    btnConfirmarPerdido.disabled = true;
    const old = btnConfirmarPerdido.innerHTML;
    btnConfirmarPerdido.innerHTML = "<span class='spinner-border spinner-border-sm me-2'></span>Confirmando...";

    try {
      const resp = await fetch(`perder_item.php?ajax=1&item_id=${encodeURIComponent(pendingPerdidoItemId)}`, {
        headers: { "X-Requested-With": "fetch" },
        cache: "no-store"
      });

      const data = await resp.json();
      if (!data.ok) {
        alert(data.msg || "Não foi possível marcar como perdido.");
        return;
      }

      // fecha modal
      const m = getModalInstance(modalPerdidoEl);
      if (m) m.hide();

      if (currentEmprestimoId) await abrirDetalhes(currentEmprestimoId);
      await load(currentPage);

    } catch (e) {
      alert("Erro. Tente novamente.");
    } finally {
      pendingPerdidoItemId = null;
      btnConfirmarPerdido.disabled = false;
      btnConfirmarPerdido.innerHTML = old;
    }
  });

  // =========================================================
  // Clique na tabela (delegação correta)
  // =========================================================
  tbody.addEventListener('click', (e) => {
    // DETALHES
    const btnDetalhes = e.target.closest("[data-action='detalhes']");
    if (btnDetalhes) {
      e.preventDefault();
      abrirDetalhes(btnDetalhes.getAttribute("data-id"));
      return;
    }

    // CANCELAR
    const btnCan = e.target.closest("[data-action='cancelar']");
    if (btnCan) {
      e.preventDefault();

      const id = btnCan.getAttribute('data-id');
      const livro = btnCan.getAttribute('data-livro') || 'Empréstimo';
      const usuario = btnCan.getAttribute('data-usuario') || 'Usuário';

      const modal = getModalInstance(modalCancelarEl);
      if (!modal) {
        window.location.href = `excluir.php?id=${encodeURIComponent(id)}`;
        return;
      }

      modalCanLivro.textContent = livro;
      modalCanUsuario.textContent = usuario;
      btnCancelar.href = `excluir.php?id=${encodeURIComponent(id)}`;
      modal.show();
      return;
    }

    // se clicou em algum link/botão (tipo renovar), deixa navegar
    const anyLinkOrButton = e.target.closest("a, button");
    if (anyLinkOrButton) return;

    // clique na linha abre detalhes
    const tr = e.target.closest("tr[data-emprestimo-id]");
    if (!tr) return;

    abrirDetalhes(tr.getAttribute("data-emprestimo-id"));
  });

})();
</script>

<?php include("../includes/footer.php"); ?>
