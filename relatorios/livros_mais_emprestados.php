<?php
$titulo_pagina = "Empréstimos por período";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$hoje = date('Y-m-d');

$inicio = $_GET['inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fim    = $_GET['fim'] ?? $hoje;

function h($s){ return htmlspecialchars($s ?? ''); }

// KPIs do período (contando emprestimos, status pelos itens)
$stmt = mysqli_prepare($conn, "
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN t.abertos = 0 THEN 1 ELSE 0 END) AS devolvidos,
    SUM(CASE WHEN t.abertos > 0 THEN 1 ELSE 0 END) AS abertos,
    SUM(CASE WHEN t.abertos > 0 AND t.data_prevista IS NOT NULL AND t.data_prevista < CURDATE() THEN 1 ELSE 0 END) AS atrasados
  FROM (
    SELECT
      e.id,
      e.data_prevista,
      SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) AS abertos
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    WHERE e.data_emprestimo BETWEEN ? AND ?
    GROUP BY e.id
  ) t
");
mysqli_stmt_bind_param($stmt, "ss", $inicio, $fim);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['total'=>0,'devolvidos'=>0,'abertos'=>0,'atrasados'=>0];

// gráfico por dia (quantos emprestimos criados por dia)
$stmt = mysqli_prepare($conn, "
  SELECT e.data_emprestimo AS dia, COUNT(*) AS qtd
  FROM emprestimos e
  WHERE e.data_emprestimo BETWEEN ? AND ?
  GROUP BY e.data_emprestimo
  ORDER BY e.data_emprestimo ASC
");
mysqli_stmt_bind_param($stmt, "ss", $inicio, $fim);
mysqli_stmt_execute($stmt);
$resGraf = mysqli_stmt_get_result($stmt);

$labels = [];
$values = [];
while ($row = mysqli_fetch_assoc($resGraf)) {
  $labels[] = $row['dia'];
  $values[] = (int)$row['qtd'];
}

// Lista detalhada (1 linha por emprestimo)
$stmt = mysqli_prepare($conn, "
  SELECT
    e.id,
    u.nome AS usuario,
    e.data_emprestimo,
    e.data_prevista,
    COUNT(ei.id) AS qtd_itens,
    SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) AS abertos,
    GROUP_CONCAT(DISTINCT l.titulo ORDER BY l.titulo SEPARATOR ' | ') AS livros_titulos
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  JOIN livros l ON l.id = ei.id_livro
  WHERE e.data_emprestimo BETWEEN ? AND ?
  GROUP BY e.id
  ORDER BY e.data_emprestimo DESC
");
mysqli_stmt_bind_param($stmt, "ss", $inicio, $fim);
mysqli_stmt_execute($stmt);
$lista = mysqli_stmt_get_result($stmt);

// Atalhos rápidos
$inicio7 = date('Y-m-d', strtotime('-7 days'));
$inicio30 = date('Y-m-d', strtotime('-30 days'));
$mesAtualIni = date('Y-m-01');
$mesAtualFim = $hoje;

$mesPassadoIni = date('Y-m-01', strtotime('first day of last month'));
$mesPassadoFim = date('Y-m-t', strtotime('last day of last month'));
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Empréstimos por período</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="report-shortcuts">
      <a class="chip" href="?inicio=<?= $inicio7 ?>&fim=<?= $hoje ?>">Últimos 7 dias</a>
      <a class="chip" href="?inicio=<?= $inicio30 ?>&fim=<?= $hoje ?>">Últimos 30 dias</a>
      <a class="chip" href="?inicio=<?= $mesAtualIni ?>&fim=<?= $mesAtualFim ?>">Mês atual</a>
      <a class="chip" href="?inicio=<?= $mesPassadoIni ?>&fim=<?= $mesPassadoFim ?>">Mês passado</a>
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
        <div class="report-card__top"><i class="bi bi-list-check"></i><span>Total</span></div>
        <div class="report-card__num"><?= (int)$kpi['total'] ?></div>
        <div class="report-card__foot">no período</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-check-circle"></i><span>Devolvidos</span></div>
        <div class="report-card__num"><?= (int)$kpi['devolvidos'] ?></div>
        <div class="report-card__foot">finalizados</div>
      </div>

      <div class="report-card">
        <div class="report-card__top"><i class="bi bi-clock-history"></i><span>Abertos</span></div>
        <div class="report-card__num"><?= (int)$kpi['abertos'] ?></div>
        <div class="report-card__foot">em andamento</div>
      </div>

      <div class="report-card report-card--danger">
        <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Atrasados</span></div>
        <div class="report-card__num"><?= (int)$kpi['atrasados'] ?></div>
        <div class="report-card__foot">requer atenção</div>
      </div>
    </div>

    <div class="report-chart mt-4">
      <div class="report-chart__head">
        <strong>Empréstimos por dia</strong>
        <span class="text-muted small"><?= h($inicio) ?> até <?= h($fim) ?></span>
      </div>
      <canvas id="chartEmprestimos" height="90"></canvas>
    </div>

    <div class="mt-4 table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th>Livro</th>
              <th>Membro</th>
              <th>Empréstimo</th>
              <th>Prevista</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $hoje2 = date('Y-m-d');
              while ($r = mysqli_fetch_assoc($lista)) {
                $abertos = (int)$r['abertos'];
                $qtd = (int)$r['qtd_itens'];
                $prev = $r['data_prevista'] ?? null;

                $devolvido = ($abertos === 0);
                $atrasado = (!$devolvido && !empty($prev) && $prev < $hoje2);

                $livroTxt = ($qtd > 1) ? ($qtd . " livros") : ($r['livros_titulos'] ?? '—');
            ?>
              <tr>
                <td class="fw-semibold"><?= h($livroTxt) ?></td>
                <td><?= h($r['usuario']) ?></td>
                <td><?= h($r['data_emprestimo']) ?></td>
                <td><?= h($r['data_prevista'] ?? '-') ?></td>
                <td>
                  <?php if ($devolvido) { ?>
                    <span class="badge-status badge-done">Devolvido</span>
                  <?php } elseif ($atrasado) { ?>
                    <span class="badge-status badge-late">Atrasado</span>
                  <?php } else { ?>
                    <span class="badge-status badge-open">Aberto</span>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
            <?php if ((int)$kpi['total'] === 0) { ?>
              <tr><td colspan="5" class="text-muted">Nenhum empréstimo no período.</td></tr>
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

  new Chart(document.getElementById('chartEmprestimos'), {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Empréstimos', data: values }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });
</script>

<?php include("../includes/footer.php"); ?>
