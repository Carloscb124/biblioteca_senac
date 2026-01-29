<?php
$titulo_pagina = "Editar Livro";
include("../conexao.php");
include("../includes/header.php");

$id = (int)($_GET['id'] ?? 0);
$l = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM livros WHERE id=$id"));

if (!$l) {
  echo "<div class='alert alert-danger'>Livro não encontrado.</div>";
  include("../includes/footer.php");
  exit;
}
?>

<h2 class="mb-3">Editar Livro</h2>

<div class="card p-3">
  <form action="atualizar.php" method="post">
    <input type="hidden" name="id" value="<?= $l['id'] ?>">

    <label class="form-label">Título</label>
    <input class="form-control mb-3" name="titulo" required value="<?= htmlspecialchars($l['titulo']) ?>">

    <label class="form-label">Autor</label>
    <input class="form-control mb-3" name="autor" value="<?= htmlspecialchars($l['autor'] ?? '') ?>">

    <label class="form-label">Ano de Publicação</label>
    <input class="form-control mb-3" type="number" name="ano_publicacao" min="0"
           value="<?= htmlspecialchars($l['ano_publicacao'] ?? '') ?>">

    <label class="form-label">ISBN</label>
    <input class="form-control mb-3" name="ISBN" maxlength="20"
           value="<?= htmlspecialchars($l['ISBN'] ?? '') ?>">

    <label class="form-label">Disponível</label>
    <select class="form-select mb-3" name="disponivel">
      <option value="1" <?= ($l['disponivel'] == 1) ? "selected" : "" ?>>Sim</option>
      <option value="0" <?= ($l['disponivel'] == 0) ? "selected" : "" ?>>Não</option>
    </select>

    <button class="btn btn-primary">Atualizar</button>
    <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
  </form>
</div>

<?php include("../includes/footer.php"); ?>
