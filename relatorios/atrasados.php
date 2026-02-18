<?php
$titulo_pagina = "Atrasados e Perdidos";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

// ===== ATRASADOS (itens abertos e vencidos) =====
$sqlAtrasados = "
SELECT
  l.titulo,
  u.nome AS usuario,
  e.data_emprestimo,
  e.data_prevista
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
JOIN livros l ON l.id = ei.id_livro
WHERE e.cancelado = 0
  AND ei.devolvido = 0
  AND ei.perdido = 0
  AND e.data_prevista IS NOT NULL
  AND e.data_prevista < CURDATE()
ORDER BY e.data_prevista ASC
";
$r = mysqli_query($conn, $sqlAtrasados);
$atrasos = [];
while ($row = mysqli_fetch_assoc($r)) $atrasos[] = $row;
$total_atrasos = count($atrasos);

// ===== PERDIDOS (itens marcados como perdido) =====
$sqlPerdidos = "
SELECT
  l.titulo,
  u.nome AS usuario,
  e.data_emprestimo,
  e.data_prevista,
  ei.data_perdido
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
JOIN livros l ON l.id = ei.id_livro
WHERE e.cancelado = 0
  AND ei.perdido = 1
ORDER BY COALESCE(ei.data_perdido, e.data_emprestimo) DESC
";
$r2 = mysqli_query($conn, $sqlPerdidos);
$perdidos = [];
while ($row = mysqli_fetch_assoc($r2)) $perdidos[] = $row;
$total_perdidos = count($perdidos);

// dados pro gráfico
$chartLabels = ["Atrasados", "Perdidos"];
$chartValues = [(int)$total_atrasos, (int)$total_perdidos];
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Atrasados e Perdidos</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="report-kpis mt-3">
      <div class="report-card report-card--danger">
        <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Atrasados</span></div>
        <div class="report-card__num"><?= (int)$total_atrasos ?></div>
        <div class="report-card__foot">itens vencidos</div>
      </div>

      <div class="report-card report-card--danger">
        <div class="report-card__top"><i class="bi bi-x-octagon"></i><span>Perdidos</span></div>
        <div class="report-card__num"><?= (int)$total_perdidos ?></div>
        <div class="report-card__foot">itens marcados como perdidos</div>
      </div>
    </div>

    <div class="report-chart mt-4">
      <div class="report-chart__head">
        <strong>Visão geral</strong>
        <span class="text-muted small">Atrasados vs Perdidos</span>
      </div>
      <div class="chart-wrap">
        <canvas id="chartAtrasosPerdidos" height="120"></canvas>
      </div>
    </div>

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
            </tr>
          </thead>
          <tbody>
            <?php if ($total_atrasos === 0) { ?>
              <tr><td colspan="4" class="text-muted">Nenhum atraso encontrado.</td></tr>
            <?php } ?>
            <?php foreach ($atrasos as $a) { ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($a['titulo']) ?></td>
                <td><?= htmlspecialchars($a['usuario']) ?></td>
                <td><?= htmlspecialchars($a['data_emprestimo']) ?></td>
                <td><span class="badge-status badge-late"><?= htmlspecialchars($a['data_prevista']) ?></span></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

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
              <tr><td colspan="5" class="text-muted">Nenhum item perdido.</td></tr>
            <?php } ?>
            <?php foreach ($perdidos as $p) { ?>
              <tr>
                <td class="fw-semibold"><?= htmlspecialchars($p['titulo']) ?></td>
                <td><?= htmlspecialchars($p['usuario']) ?></td>
                <td><?= htmlspecialchars($p['data_emprestimo']) ?></td>
                <td><?= htmlspecialchars($p['data_prevista'] ?? '-') ?></td>
                <td><span class="badge-soft-no"><i class="bi bi-x-circle"></i> <?= htmlspecialchars($p['data_perdido'] ?? '-') ?></span></td>
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
