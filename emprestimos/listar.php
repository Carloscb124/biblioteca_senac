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
// FILTROS INICIAIS
// ===============================
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

// ===============================
// FILTRO VINDO DO DASHBOARD (cards)
// dashboard manda ?f=abertos | atrasados
// ===============================
$f = trim($_GET['f'] ?? '');
if ($status === '' && $f !== '') {
  if ($f === 'abertos') $status = 'aberto';
  if ($f === 'atrasados') $status = 'atrasado';
}

// ===============================
// MONTA WHERE + PARAMS
// ===============================
$where = [];
$params = [];
$types = "";

// Busca por nome do usuário ou título do livro
if ($q !== '') {
  $where[] = "(u.nome LIKE ? OR l.titulo LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}

// Filtro por status
if ($status === 'devolvido') {
  $where[] = "e.devolvido = 1";
} elseif ($status === 'atrasado') {
  $where[] = "(e.devolvido = 0 AND e.data_prevista IS NOT NULL AND e.data_prevista < ?)";
  $params[] = $hoje;
  $types .= "s";
} elseif ($status === 'aberto') {
  $where[] = "(e.devolvido = 0 AND (e.data_prevista IS NULL OR e.data_prevista >= ?))";
  $params[] = $hoje;
  $types .= "s";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

// ===============================
// COUNT TOTAL (pra paginação)
// ===============================
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN livros l ON l.id = e.id_livro
  $whereSql
";
$stmtC = mysqli_prepare($conn, $sqlCount);
if ($types !== "") {
  mysqli_stmt_bind_param($stmtC, $types, ...$params);
}
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
$total = (int)(mysqli_fetch_assoc($resC)['total'] ?? 0);

// Calcula total de páginas e ajusta offset
$total_paginas = max(1, (int)ceil($total / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;
$offset = ($pagina - 1) * $por_pagina;

// ===============================
// SELECT PAGINADO
// ===============================
$sql = "
  SELECT
    e.id,
    e.data_emprestimo,
    e.data_prevista,
    e.data_devolucao,
    e.devolvido,
    u.nome AS usuario_nome,
    l.titulo AS livro_titulo
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN livros l ON l.id = e.id_livro
  $whereSql
  ORDER BY e.id DESC
  LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $sql);

// bind dos filtros + limit/offset
$types2 = $types . "ii";
$params2 = $params;
$params2[] = $por_pagina;
$params2[] = $offset;

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

    <!-- CHIP: mostra filtro ativo -->
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="text-muted small">Filtro:</span>
      <span class="badge rounded-pill text-bg-light" style="border:1px solid #e7e1d6;">
        <?= htmlspecialchars(statusLabel($status)) ?>
      </span>

      <?php if ($status !== '' || $q !== '') { ?>
        <a class="small text-decoration-none ms-2" href="listar.php">Limpar filtros</a>
      <?php } ?>
    </div>

    <!-- BUSCA + FILTRO -->
    <div class="row g-2 align-items-center mb-3">
      <div class="col-md-5">
        <div class="input-group">
          <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
          <input
            id="search"
            type="text"
            class="form-control"
            placeholder="Buscar por usuário ou livro..."
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
              <th>Livro</th>
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
              $devolvido = (int)$e['devolvido'];
              $prevista  = $e['data_prevista'] ?? null;
              $atrasado  = ($devolvido === 0 && !empty($prevista) && $prevista < $hoje);

              // Preparando textos pro modal (ENT_QUOTES evita quebrar atributo HTML)
              $livroAttr = htmlspecialchars($e['livro_titulo'], ENT_QUOTES);
              $userAttr  = htmlspecialchars($e['usuario_nome'], ENT_QUOTES);
            ?>
              <tr>
                <td class="text-muted fw-semibold">#<?= (int)$e['id'] ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($e['usuario_nome']) ?></td>
                <td><?= htmlspecialchars($e['livro_titulo']) ?></td>

                <td><?= htmlspecialchars($e['data_emprestimo']) ?></td>
                <td><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></td>
                <td><?= htmlspecialchars($e['data_devolucao'] ?? '-') ?></td>

                <td>
                  <?php if ($devolvido === 1) { ?>
                    <span class="badge-status badge-done">
                      <i class="bi bi-check-circle"></i> Devolvido
                    </span>
                  <?php } elseif ($atrasado) { ?>
                    <span class="badge-status badge-late">
                      <i class="bi bi-exclamation-circle"></i> Atrasado
                    </span>
                  <?php } else { ?>
                    <span class="badge-status badge-open">
                      <i class="bi bi-clock-history"></i> Aberto
                    </span>
                  <?php } ?>
                </td>

                <td class="text-end">
                  <?php if ($devolvido === 0) { ?>
                    <!-- Editar normal -->
                    <a class="icon-btn icon-btn--edit"
                      href="editar.php?id=<?= (int)$e['id'] ?>"
                      title="Editar">
                      <i class="bi bi-pencil"></i>
                    </a>

                    <!-- Devolver: agora abre MODAL (sem confirm do navegador) -->
                    <a class="icon-btn icon-btn--edit"
                      href="#"
                      data-action="devolver"
                      data-id="<?= (int)$e['id'] ?>"
                      data-livro="<?= $livroAttr ?>"
                      data-usuario="<?= $userAttr ?>"
                      title="Devolver">
                      <i class="bi bi-check2-circle"></i>
                    </a>
                  <?php } ?>

                  <!-- Excluir: abre MODAL -->
                  <a class="icon-btn icon-btn--del"
                    href="#"
                    data-action="excluir"
                    data-id="<?= (int)$e['id'] ?>"
                    data-livro="<?= $livroAttr ?>"
                    data-usuario="<?= $userAttr ?>"
                    title="Excluir">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PAGINAÇÃO + RESUMO -->
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
     MODAL: DEVOLVER
     ========================================================= -->
<div class="modal fade" id="modalDevolver" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-check2-circle me-2"></i>Confirmar devolução
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2 text-muted">Você está prestes a devolver:</div>
        <div class="fw-semibold" id="modalDevLivro">Livro</div>
        <div class="text-muted small mt-1">Para: <span id="modalDevUsuario">Usuário</span></div>

        <div class="alert alert-success mt-3 mb-0" style="border-radius:12px;">
          Isso vai marcar o empréstimo como devolvido e liberar o exemplar no acervo.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-success" id="btnConfirmarDevolver">
          <i class="bi bi-check2-circle me-1"></i> Confirmar
        </a>
      </div>
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
        <div class="fw-semibold" id="modalExcLivro">Livro</div>
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

<script>
  (function() {
    // =========================================================
    // PARTE 1: BUSCA / FILTRO / PAGINAÇÃO via fetch (AJAX)
    // =========================================================
    const input = document.getElementById('search');
    const status = document.getElementById('status');
    const tbody = document.getElementById('tbody-emprestimos');
    const pagWrap = document.getElementById('paginacao-emprestimos');
    const resumo = document.getElementById('resumo-emprestimos');

    let timer = null;

    // Carrega a tabela pelo endpoint buscar.php (retorna HTML das linhas + paginação)
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

        // Atualiza só pedaços da tela (sem reload)
        tbody.innerHTML = data.rows;
        pagWrap.innerHTML = data.pagination;
        resumo.textContent = data.summary;
      } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">Erro ao buscar. Tente novamente.</td></tr>`;
      }
    }

    // Debounce: evita chamar o servidor a cada tecla
    function debounceLoad() {
      clearTimeout(timer);
      timer = setTimeout(() => load(1), 250);
    }

    input.addEventListener('input', debounceLoad);
    status.addEventListener('change', () => load(1));

    // Paginação: delegação (clicou em qualquer link dentro do pagWrap)
    pagWrap.addEventListener('click', (ev) => {
      const a = ev.target.closest('a');
      if (!a) return;
      ev.preventDefault();

      const url = new URL(a.href);
      const p = parseInt(url.searchParams.get('p') || '1', 10);
      load(p);
    });

    // =========================================================
    // PARTE 2: MODAIS BONITOS (Devolver / Excluir)
    // =========================================================
    const modalDevolverEl = document.getElementById('modalDevolver');
    const modalExcluirEl  = document.getElementById('modalExcluir');

    const modalDevLivro   = document.getElementById('modalDevLivro');
    const modalDevUsuario = document.getElementById('modalDevUsuario');
    const btnDev          = document.getElementById('btnConfirmarDevolver');

    const modalExcLivro   = document.getElementById('modalExcLivro');
    const modalExcUsuario = document.getElementById('modalExcUsuario');
    const btnExc          = document.getElementById('btnConfirmarExcluir');

    // Pega (ou cria) instância do modal na hora do clique (evita bug de bootstrap)
    function getModalInstance(el) {
      if (!(window.bootstrap && typeof bootstrap.Modal === "function")) return null;
      return bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
    }

    // Delegação no tbody:
    // funciona mesmo quando o tbody muda (porque você faz fetch e substitui HTML)
    tbody.addEventListener('click', (e) => {
      const btnDevolver = e.target.closest("[data-action='devolver']");
      const btnExcluir  = e.target.closest("[data-action='excluir']");

      if (!btnDevolver && !btnExcluir) return;
      e.preventDefault();

      const btn = btnDevolver || btnExcluir;
      const id = btn.getAttribute('data-id');
      const livro = btn.getAttribute('data-livro') || 'Livro';
      const usuario = btn.getAttribute('data-usuario') || 'Usuário';

      // -------- Modal DEVOLVER --------
      if (btnDevolver) {
        const modal = getModalInstance(modalDevolverEl);

        // fallback: se bootstrap não carregar, vai direto
        if (!modal) {
          window.location.href = `devolver.php?id=${encodeURIComponent(id)}`;
          return;
        }

        modalDevLivro.textContent = livro;
        modalDevUsuario.textContent = usuario;
        btnDev.href = `devolver.php?id=${encodeURIComponent(id)}`;

        modal.show();
        return;
      }

      // -------- Modal EXCLUIR --------
      const modal = getModalInstance(modalExcluirEl);
      if (!modal) {
        window.location.href = `excluir.php?id=${encodeURIComponent(id)}`;
        return;
      }

      modalExcLivro.textContent = livro;
      modalExcUsuario.textContent = usuario;
      btnExc.href = `excluir.php?id=${encodeURIComponent(id)}`;

      modal.show();
    });

    // Se você quiser já carregar a primeira página via AJAX, descomenta:
    // load(1);
  })();
</script>

<?php include("../includes/footer.php"); ?>
