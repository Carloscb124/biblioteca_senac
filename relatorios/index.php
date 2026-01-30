<?php
$titulo_pagina = "Relatórios";
include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <h2 class="page-card__title mb-3">Relatórios</h2>

    <ul class="list-group list-group-flush">
      <li class="list-group-item">
        <a href="emprestimos_periodo.php">📅 Empréstimos por período</a>
      </li>
      <li class="list-group-item">
        <a href="livros_mais_emprestados.php">📚 Livros mais emprestados</a>
      </li>
      <li class="list-group-item">
        <a href="atrasados.php">⏰ Empréstimos em atraso</a>
      </li>
      <li class="list-group-item">
        <a href="usuario.php">👤 Histórico por usuário</a>
      </li>
    </ul>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
