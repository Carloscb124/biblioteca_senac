<?php
$titulo_pagina = "Empréstimos em Atraso";
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "
  SELECT l.titulo, u.nome, e.data_prevista
  FROM emprestimos e
  JOIN livros l ON l.id = e.id_livro
  JOIN usuarios u ON u.id = e.id_usuario
  WHERE e.devolvido = 0
    AND e.data_prevista < CURDATE()
");
?>

<div class="container my-4">
  <div class="page-card">
    <h2 class="page-card__title mb-3">Empréstimos em atraso</h2>

    <table class="table table-clean">
      <thead>
        <tr>
          <th>Livro</th>
          <th>Usuário</th>
          <th>Devolução prevista</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($a = mysqli_fetch_assoc($r)) { ?>
          <tr>
            <td><?= htmlspecialchars($a['titulo']) ?></td>
            <td><?= htmlspecialchars($a['nome']) ?></td>
            <td><?= $a['data_prevista'] ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
