<?php
$titulo_pagina = "Empréstimos em atraso";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$sql = "
SELECT
  l.titulo,
  u.nome AS usuario,
  e.data_emprestimo,
  e.data_prevista
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
JOIN livros l ON l.id = ei.id_livro
WHERE ei.devolvido = 0
  AND e.data_prevista IS NOT NULL
  AND e.data_prevista < CURDATE()
ORDER BY e.data_prevista ASC
";
$r = mysqli_query($conn, $sql);

$atrasos = [];
while ($row = mysqli_fetch_assoc($r)) $atrasos[] = $row;
$total_atrasos = count($atrasos);
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Empréstimos em atraso</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="report-kpis mt-3">
      <div class="report-card report-card--danger">
        <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Total</span></div>
        <div class="report-card__num"><?= (int)$total_atrasos ?></div>
        <div class="report-card__foot">itens em atraso</div>
      </div>
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

  </div>
</div>

<?php include("../includes/footer.php"); ?>
