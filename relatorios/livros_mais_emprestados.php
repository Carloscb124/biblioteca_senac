<?php
$titulo_pagina = "Livros mais emprestados";
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "
  SELECT l.titulo, COUNT(*) AS total
  FROM emprestimos e
  JOIN livros l ON l.id = e.id_livro
  GROUP BY e.id_livro
  ORDER BY total DESC
");

$labels = [];
$values = [];
$top = [];

while ($row = mysqli_fetch_assoc($r)) {
  $top[] = $row;
  if (count($labels) < 10) { // top 10 no gráfico
    $labels[] = $row['titulo'];
    $values[] = (int)$row['total'];
  }
}

$total_emprestimos = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM emprestimos"))['c'];
$total_livros = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM livros"))['c'];
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Livros mais emprestados</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- KPIs -->
    <div class="report-kpis mt-3">
      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-book"></i><span>Total de livros</span></div>
        <div class="report-card__num"><?= $total_livros ?></div>
        <div class="report-card__foot">cadastrados</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-arrow-repeat"></i><span>Total de empréstimos</span></div>
        <div class="report-card__num"><?= $total_emprestimos ?></div>
        <div class="report-card__foot">registrados</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-star"></i><span>Top no gráfico</span></div>
        <div class="report-card__num"><?= count($labels) ?></div>
        <div class="report-card__foot">livros</div>
      </div>

      <div class="report-card report-card--danger">
        <div class="report-card__top"><i class="bi bi-graph-up"></i><span>Maior valor</span></div>
        <div class="report-card__num"><?= isset($top[0]) ? (int)$top[0]['total'] : 0 ?></div>
        <div class="report-card__foot">empréstimos</div>
      </div>
    </div>

    <!-- Gráfico -->
    <div class="report-chart mt-4">
      <div class="report-chart__head">
        <strong>Top 10 livros mais emprestados</strong>
        <span class="text-muted small">Ranking</span>
      </div>
      <canvas id="chartTopLivros" height="90"></canvas>
    </div>

    <!-- Tabela -->
    <div class="mt-4 table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th>Livro</th>
              <th class="text-end">Total de empréstimos</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top as $row) { ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($row['titulo']) ?></td>
                <td class="text-end"><?= (int)$row['total'] ?></td>
              </tr>
            <?php } ?>
            <?php if (count($top) === 0) { ?>
              <tr><td colspan="2" class="text-muted">Nenhum empréstimo registrado.</td></tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const labels = <?= json_encode($labels) ?>;
  const values = <?= json_encode($values) ?>;

  const ctx = document.getElementById('chartTopLivros');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Empréstimos',
        data: values
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>

<?php include("../includes/footer.php"); ?>
