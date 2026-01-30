<?php
$titulo_pagina = "Livros Mais Emprestados";
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "
  SELECT l.titulo, COUNT(*) AS total
  FROM emprestimos e
  JOIN livros l ON l.id = e.id_livro
  GROUP BY e.id_livro
  ORDER BY total DESC
");
?>

<div class="container my-4">
  <div class="page-card">
    <h2 class="page-card__title mb-3">Livros mais emprestados</h2>

    <table class="table table-clean">
      <thead>
        <tr>
          <th>Livro</th>
          <th>Total de empréstimos</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($l = mysqli_fetch_assoc($r)) { ?>
          <tr>
            <td><?= htmlspecialchars($l['titulo']) ?></td>
            <td><?= $l['total'] ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
