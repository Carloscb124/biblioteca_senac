<?php
$titulo_pagina = "Empréstimos em atraso";
include("../conexao.php");
include("../includes/header.php");

$sql = "
SELECT
  l.titulo,
  u.nome AS usuario,
  e.data_emprestimo,
  e.data_prevista
FROM emprestimos e
JOIN livros l ON l.id = e.id_livro
JOIN usuarios u ON u.id = e.id_usuario
WHERE e.devolvido = 0
  AND e.data_prevista IS NOT NULL
  AND e.data_prevista < CURDATE()
ORDER BY e.data_prevista ASC
";
$r = mysqli_query($conn, $sql);

$atrasos = [];
while ($row = mysqli_fetch_assoc($r)) {
  $atrasos[] = $row;
}

$total_atrasos = count($atrasos);

/* gráfico: atrasos por usuário */
$labels = [];
$values = [];
$g = mysqli_query($conn, "
  SELECT u.nome AS usuario, COUNT(*) AS total
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  WHERE e.devolvido = 0
    AND e.data_prevista IS NOT NULL
    AND e.data_prevista < CURDATE()
  GROUP BY e.id_usuario
  ORDER BY total DESC
  LIMIT 8
");
while ($row = mysqli_fetch_assoc($g)) {
  $labels[] = $row['usuario'];
  $values[] = (int)$row['total'];
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Empréstimos em atraso</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <!-- KPIs -->
    <div class="report-kpis mt-3">
      <div class="report-card report-card--danger">
        <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Total em atraso</span></div>
        <div class="report-card__num"><?= $total_atrasos ?></div>
        <div class="report-card__foot">requer atenção</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-people"></i><span>Usuários com atraso</span></div>
        <div class="report-card__num"><?= count($labels) ?></div>
        <div class="report-card__foot">no top do gráfico</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-clock-history"></i><span>Status</span></div>
        <div class="report-card__num">⏰</div>
        <div class="report-card__foot">pendente de devolução</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-list-check"></i><span>Prioridade</span></div>
        <div class="report-card__num">Alta</div>
        <div class="report-card__foot">controle de acervo</div>
      </div>
    </div>

    <!-- Gráfico -->
    <div class="report-chart mt-4">
      <div class="report-chart__head">
        <strong>Atrasos por usuário (Top 8)</strong>
        <span class="text-muted small">ranking</span>
      </div>
      <canvas id="chartAtrasos" height="90"></canvas>
    </div>

    <!-- Tabela -->
    <div class="mt-4 table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th>Livro</th>
              <th>Usuário</th>
              <th>Empréstimo</th>
              <th>Prevista</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($atrasos as $a) { ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($a['titulo']) ?></td>
                <td><?= htmlspecialchars($a['usuario']) ?></td>
                <td><?= htmlspecialchars($a['data_emprestimo']) ?></td>
                <td><?= htmlspecialchars($a['data_prevista']) ?></td>
                <td><span class="badge-status badge-late">Atrasado</span></td>
              </tr>
            <?php } ?>
            <?php if ($total_atrasos === 0) { ?>
              <tr><td colspan="5" class="text-muted">Nenhum atraso registrado 🎉</td></tr>
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
  const ctx = document.getElementById('chartAtrasos');

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{ label: 'Atrasos', data: values }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
</script>

<?php include("../includes/footer.php"); ?>
