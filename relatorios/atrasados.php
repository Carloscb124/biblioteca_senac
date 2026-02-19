<?php
$titulo_pagina = "Atrasados e Perdidos";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

/**
 * Relatório: Atrasados e Perdidos
 * Estilo igual aos outros relatórios:
 * - chips de atalho (7/30/90 dias)
 * - filtro por período (inicio/fim)
 * - filtro por status (todos/atrasados/perdidos)
 * - busca (livro/usuário)
 * - KPIs dinâmicos
 */

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$hoje = date('Y-m-d');

// ====== FILTROS (padrão igual aos outros relatórios) ======
$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fim    = $_GET['fim']    ?? $hoje;

// "as_of" controla a data de referência pra dizer o que está atrasado
// (por padrão, hoje)
$as_of  = $_GET['as_of']  ?? $hoje;

// status: todos | atrasados | perdidos
$status = $_GET['status'] ?? 'todos';
$status = in_array($status, ['todos','atrasados','perdidos'], true) ? $status : 'todos';

// busca: procura por título e usuário
$q = trim($_GET['q'] ?? '');

// atalhos de período (chips)
$inicio7  = date('Y-m-d', strtotime('-7 days'));
$inicio30 = date('Y-m-d', strtotime('-30 days'));
$inicio90 = date('Y-m-d', strtotime('-90 days'));

// ====== HELPERS de SQL (monta WHERE + params de forma segura) ======
/**
 * Retorna [whereSql, types, params]
 */
function buildSearchWhere(string $q, array $fields): array {
  $q = trim($q);
  if ($q === '') return ['', '', []];

  // procura em vários campos: (f1 LIKE ? OR f2 LIKE ? ...)
  $like = '%' . $q . '%';
  $parts = [];
  $params = [];
  foreach ($fields as $f) {
    $parts[] = "{$f} LIKE ?";
    $params[] = $like;
  }
  $where = " AND (" . implode(" OR ", $parts) . ") ";
  $types = str_repeat('s', count($params));
  return [$where, $types, $params];
}

// ====== CONSULTA: ATRASADOS ======
// atraso = item aberto (não devolvido), não perdido, e data_prevista < as_of
$atrasos = [];
$total_atrasos = 0;

if ($status === 'todos' || $status === 'atrasados') {

  // busca em livro + usuário
  [$whereBusca, $typesBusca, $paramsBusca] = buildSearchWhere($q, ['l.titulo', 'u.nome']);

  $sqlAtrasados = "
    SELECT
      l.titulo,
      u.nome AS usuario,
      e.data_emprestimo,
      e.data_prevista,
      DATEDIFF(?, e.data_prevista) AS dias_atraso
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.id_usuario
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    JOIN livros l ON l.id = ei.id_livro
    WHERE e.cancelado = 0
      AND ei.devolvido = 0
      AND ei.perdido = 0
      AND e.data_prevista IS NOT NULL
      AND e.data_prevista BETWEEN ? AND ?
      AND e.data_prevista < ?
      $whereBusca
    ORDER BY e.data_prevista ASC
  ";

  $stmt = mysqli_prepare($conn, $sqlAtrasados);
  if (!$stmt) {
    echo "<div class='container my-4'><div class='alert alert-danger'>Erro ao preparar relatório de atrasados.</div></div>";
    include("../includes/footer.php");
    exit;
  }

  // params base: as_of, inicio, fim, as_of
  $types = "ssss" . $typesBusca;
  $params = array_merge([$as_of, $inicio, $fim, $as_of], $paramsBusca);

  mysqli_stmt_bind_param($stmt, $types, ...$params);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  while ($row = mysqli_fetch_assoc($res)) $atrasos[] = $row;
  $total_atrasos = count($atrasos);

  mysqli_stmt_close($stmt);
}

// ====== CONSULTA: PERDIDOS ======
$perdidos = [];
$total_perdidos = 0;

if ($status === 'todos' || $status === 'perdidos') {

  // busca em livro + usuário
  [$whereBusca2, $typesBusca2, $paramsBusca2] = buildSearchWhere($q, ['l.titulo', 'u.nome']);

  // período aplica no "momento do evento" (quando foi marcado perdido, senão data do empréstimo)
  $sqlPerdidos = "
    SELECT
      l.titulo,
      u.nome AS usuario,
      e.data_emprestimo,
      e.data_prevista,
      ei.data_perdido,
      COALESCE(ei.data_perdido, e.data_emprestimo) AS data_evento
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.id_usuario
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    JOIN livros l ON l.id = ei.id_livro
    WHERE e.cancelado = 0
      AND ei.perdido = 1
      AND COALESCE(ei.data_perdido, e.data_emprestimo) BETWEEN ? AND ?
      $whereBusca2
    ORDER BY COALESCE(ei.data_perdido, e.data_emprestimo) DESC
  ";

  $stmt2 = mysqli_prepare($conn, $sqlPerdidos);
  if (!$stmt2) {
    echo "<div class='container my-4'><div class='alert alert-danger'>Erro ao preparar relatório de perdidos.</div></div>";
    include("../includes/footer.php");
    exit;
  }

  $types2 = "ss" . $typesBusca2;
  $params2 = array_merge([$inicio, $fim], $paramsBusca2);

  mysqli_stmt_bind_param($stmt2, $types2, ...$params2);
  mysqli_stmt_execute($stmt2);
  $res2 = mysqli_stmt_get_result($stmt2);

  while ($row = mysqli_fetch_assoc($res2)) $perdidos[] = $row;
  $total_perdidos = count($perdidos);

  mysqli_stmt_close($stmt2);
}

// dados pro gráfico (respeita filtro)
$chartLabels = [];
$chartValues = [];

if ($status === 'todos') {
  $chartLabels = ["Atrasados", "Perdidos"];
  $chartValues = [(int)$total_atrasos, (int)$total_perdidos];
} elseif ($status === 'atrasados') {
  $chartLabels = ["Atrasados"];
  $chartValues = [(int)$total_atrasos];
} else {
  $chartLabels = ["Perdidos"];
  $chartValues = [(int)$total_perdidos];
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Atrasados e Perdidos</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- CHIPS / ATALHOS -->
    <div class="report-shortcuts">
      <a class="chip" href="?status=<?= h($status) ?>&q=<?= urlencode($q) ?>&as_of=<?= h($as_of) ?>&inicio=<?= $inicio7 ?>&fim=<?= $hoje ?>">Últimos 7 dias</a>
      <a class="chip" href="?status=<?= h($status) ?>&q=<?= urlencode($q) ?>&as_of=<?= h($as_of) ?>&inicio=<?= $inicio30 ?>&fim=<?= $hoje ?>">Últimos 30 dias</a>
      <a class="chip" href="?status=<?= h($status) ?>&q=<?= urlencode($q) ?>&as_of=<?= h($as_of) ?>&inicio=<?= $inicio90 ?>&fim=<?= $hoje ?>">Últimos 90 dias</a>

      <span class="chip-sep"></span>

      <a class="chip <?= $status==='todos' ? 'active' : '' ?>" href="?status=todos&inicio=<?= h($inicio) ?>&fim=<?= h($fim) ?>&as_of=<?= h($as_of) ?>&q=<?= urlencode($q) ?>">Tudo</a>
      <a class="chip <?= $status==='atrasados' ? 'active' : '' ?>" href="?status=atrasados&inicio=<?= h($inicio) ?>&fim=<?= h($fim) ?>&as_of=<?= h($as_of) ?>&q=<?= urlencode($q) ?>">Só atrasados</a>
      <a class="chip <?= $status==='perdidos' ? 'active' : '' ?>" href="?status=perdidos&inicio=<?= h($inicio) ?>&fim=<?= h($fim) ?>&as_of=<?= h($as_of) ?>&q=<?= urlencode($q) ?>">Só perdidos</a>
    </div>

    <!-- FORM FILTRO (mesmo esquema dos outros relatórios) -->
    <form class="row g-3 mt-1">
      <input type="hidden" name="status" value="<?= h($status) ?>">

      <div class="col-md-3">
        <label class="form-label">Início</label>
        <input type="date" name="inicio" class="form-control" value="<?= h($inicio) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Fim</label>
        <input type="date" name="fim" class="form-control" value="<?= h($fim) ?>" required>
      </div>

      <div class="col-md-3">
        <label class="form-label">Atraso calculado até</label>
        <input type="date" name="as_of" class="form-control" value="<?= h($as_of) ?>" required>
        <div class="form-text">Define a data de referência pra considerar “atrasado”.</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Buscar</label>
        <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="Livro ou membro">
      </div>

      <div class="col-12">
        <button class="btn btn-pill" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
        <a class="btn btn-outline-secondary btn-pill ms-2" href="atrasados.php"><i class="bi bi-eraser"></i> Limpar</a>
      </div>
    </form>

    <!-- KPIs -->
    <div class="report-kpis mt-4">
      <?php if ($status === 'todos' || $status === 'atrasados') { ?>
        <div class="report-card report-card--danger">
          <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Atrasados</span></div>
          <div class="report-card__num"><?= (int)$total_atrasos ?></div>
          <div class="report-card__foot">itens vencidos no período</div>
        </div>
      <?php } ?>

      <?php if ($status === 'todos' || $status === 'perdidos') { ?>
        <div class="report-card report-card--danger">
          <div class="report-card__top"><i class="bi bi-x-octagon"></i><span>Perdidos</span></div>
          <div class="report-card__num"><?= (int)$total_perdidos ?></div>
          <div class="report-card__foot">itens marcados como perdidos</div>
        </div>
      <?php } ?>
    </div>

    <!-- GRÁFICO -->
    <div class="report-chart mt-4">
      <div class="report-chart__head">
        <strong>Visão geral</strong>
        <span class="text-muted small">
          Período <?= h($inicio) ?> até <?= h($fim) ?> • referência de atraso: <?= h($as_of) ?>
        </span>
      </div>
      <div class="chart-wrap">
        <canvas id="chartAtrasosPerdidos" height="120"></canvas>
      </div>
    </div>

    <!-- TABELA: ATRASADOS -->
    <?php if ($status === 'todos' || $status === 'atrasados') { ?>
      <h5 class="mt-4 mb-2"><i class="bi bi-clock-history me-1"></i> Itens em atraso</h5>

      <div class="table-base-wrap">
        <div class="table-responsive">
          <table class="table table-base align-middle mb-0">
            <thead>
              <tr>
                <th>Livro</th>
                <th>Membro</th>
                <th>Empréstimo</th>
                <th>Prevista</th>
                <th class="text-end">Dias</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($total_atrasos === 0) { ?>
                <tr><td colspan="5" class="text-muted">Nenhum atraso encontrado com esses filtros.</td></tr>
              <?php } ?>
              <?php foreach ($atrasos as $a) { ?>
                <tr>
                  <td class="fw-semibold"><?= h($a['titulo']) ?></td>
                  <td><?= h($a['usuario']) ?></td>
                  <td><?= h($a['data_emprestimo']) ?></td>
                  <td><span class="badge-status badge-late"><?= h($a['data_prevista']) ?></span></td>
                  <td class="text-end fw-semibold"><?= (int)$a['dias_atraso'] ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php } ?>

    <!-- TABELA: PERDIDOS -->
    <?php if ($status === 'todos' || $status === 'perdidos') { ?>
      <h5 class="mt-4 mb-2"><i class="bi bi-x-octagon me-1"></i> Itens perdidos</h5>

      <div class="table-base-wrap">
        <div class="table-responsive">
          <table class="table table-base align-middle mb-0">
            <thead>
              <tr>
                <th>Livro</th>
                <th>Membro</th>
                <th>Empréstimo</th>
                <th>Prevista</th>
                <th>Marcado perdido</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($total_perdidos === 0) { ?>
                <tr><td colspan="5" class="text-muted">Nenhum item perdido com esses filtros.</td></tr>
              <?php } ?>
              <?php foreach ($perdidos as $p) { ?>
                <tr>
                  <td class="fw-semibold"><?= h($p['titulo']) ?></td>
                  <td><?= h($p['usuario']) ?></td>
                  <td><?= h($p['data_emprestimo']) ?></td>
                  <td><?= h($p['data_prevista'] ?? '-') ?></td>
                  <td>
                    <span class="badge-soft-no">
                      <i class="bi bi-x-circle"></i>
                      <?= h($p['data_perdido'] ?? '-') ?>
                    </span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php } ?>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('chartAtrasosPerdidos'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [{ data: <?= json_encode($chartValues) ?> }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
</script>

<?php include("../includes/footer.php"); ?>
