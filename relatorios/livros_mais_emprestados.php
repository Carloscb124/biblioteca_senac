<?php
$titulo_pagina = "Livros mais emprestados";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$hoje = date('Y-m-d');
$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fim    = $_GET['fim'] ?? $hoje;

function h($s){ return htmlspecialchars($s ?? ''); }

// KPI: total de itens emprestados no período
$stmt = mysqli_prepare($conn, "
  SELECT COUNT(*) AS total_itens
  FROM emprestimos e
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  WHERE e.cancelado = 0
    AND e.data_emprestimo BETWEEN ? AND ?
");
mysqli_stmt_bind_param($stmt, "ss", $inicio, $fim);
mysqli_stmt_execute($stmt);
$kpiTotal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['total_itens'=>0];

// Ranking top 10
$stmt = mysqli_prepare($conn, "
  SELECT
    l.titulo,
    COUNT(*) AS qtd
  FROM emprestimos e
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  JOIN livros l ON l.id = ei.id_livro
  WHERE e.cancelado = 0
    AND e.data_emprestimo BETWEEN ? AND ?
  GROUP BY l.id
  ORDER BY qtd DESC, l.titulo ASC
  LIMIT 10
");
mysqli_stmt_bind_param($stmt, "ss", $inicio, $fim);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$top = [];
$labels = [];
$values = [];
while ($row = mysqli_fetch_assoc($res)) {
  $top[] = $row;
  $labels[] = $row['titulo'];
  $values[] = (int)$row['qtd'];
}

// Atalhos
$inicio7 = date('Y-m-d', strtotime('-7 days'));
$inicio30 = date('Y-m-d', strtotime('-30 days'));
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Livros mais emprestados</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="report-shortcuts">
      <a class="chip" href="?inicio=<?= $inicio7 ?>&fim=<?= $hoje ?>">Últimos 7 dias</a>
      <a class="chip" href="?inicio=<?= $inicio30 ?>&fim=<?= $hoje ?>">Últimos 30 dias</a>
    </div>

    <form class="row g-3 mt-1">
      <div class="col-md-4">
        <label class="form-label">Data inicial</label>
        <input type="date" name="inicio" class="form-control" value="<?= h($inicio) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Data final</label>
        <input type="date" name="fim" class="form-control" value="<?= h($fim) ?>" required>
      </div>
      <div class="col-md-4 align-self-end">
        <button class="btn btn-pill" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
      </div>
    </form>

    <div class="report-kpis mt-4">
      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-collection"></i><span>Total de itens</span></div>
        <div class="report-card__num"><?= (int)$kpiTotal['total_itens'] ?></div>
        <div class="report-card__foot">itens emprestados no período</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-trophy"></i><span>Top list</span></div>
        <div class="report-card__num"><?= count($top) ?></div>
        <div class="report-card__foot">livros no ranking</div>
      </div>
    </div>

    <div class="report-chart mt-4">
      <div class="report-chart__head">
        <strong>Top 10 no período</strong>
        <span class="text-muted small"><?= h($inicio) ?> até <?= h($fim) ?></span>
      </div>
      <canvas id="chartTopLivros" height="120"></canvas>
    </div>

    <div class="mt-4 table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Livro</th>
              <th class="text-end">Qtd</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($top) === 0) { ?>
              <tr><td colspan="3" class="text-muted">Nenhum empréstimo no período.</td></tr>
            <?php } ?>
            <?php foreach ($top as $i => $r) { ?>
              <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td class="fw-semibold"><?= h($r['titulo']) ?></td>
                <td class="text-end"><span class="badge-soft-ok"><i class="bi bi-graph-up"></i> <?= (int)$r['qtd'] ?></span></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('chartTopLivros'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [{ label: 'Empréstimos', data: <?= json_encode($values) ?> }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
</script>

<?php include("../includes/footer.php"); ?>
