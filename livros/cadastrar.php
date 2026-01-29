<?php
$titulo_pagina = "Cadastrar Livro";
include("../includes/header.php");
?>

<h2 class="mb-3">Cadastrar Livro</h2>

<div class="card p-3">
  <form action="salvar.php" method="post">
    <label class="form-label">Título</label>
    <input class="form-control mb-3" name="titulo" required>

    <label class="form-label">Autor</label>
    <input class="form-control mb-3" name="autor">

    <label class="form-label">Ano de Publicação</label>
    <input class="form-control mb-3" type="number" name="ano_publicacao" min="0">

    <label class="form-label">ISBN</label>
    <input class="form-control mb-3" name="ISBN" maxlength="20">

    <button class="btn btn-primary">Salvar</button>
    <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
  </form>
</div>

<?php include("../includes/footer.php"); ?>
