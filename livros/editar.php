<?php
$titulo_pagina = "Editar Livro";
include("../conexao.php");
include("../includes/header.php");

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT * FROM livros WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$l = mysqli_fetch_assoc($res);

if (!$l) { ?>
  <div class="container my-4">
    <div class="alert alert-danger mb-0">Livro não encontrado.</div>
  </div>
  <?php include("../includes/footer.php"); exit; ?>
<?php } ?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Editar Livro</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="atualizar.php" method="post" class="form-grid">
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Título</label>
          <input class="form-control" name="titulo" required
                 value="<?= htmlspecialchars($l['titulo']) ?>"
                 placeholder="Ex: Dom Casmurro">
        </div>

        <div class="col-md-6">
          <label class="form-label">Autor</label>
          <input class="form-control" name="autor"
                 value="<?= htmlspecialchars($l['autor'] ?? '') ?>"
                 placeholder="Ex: Machado de Assis">
        </div>

        <div class="col-md-3">
          <label class="form-label">Ano</label>
          <input class="form-control" type="number" name="ano_publicacao" min="0"
                 value="<?= htmlspecialchars($l['ano_publicacao'] ?? '') ?>"
                 placeholder="Ex: 1899">
        </div>

        <div class="col-md-3">
          <label class="form-label">ISBN</label>
          <input class="form-control" name="ISBN" maxlength="20"
                 value="<?= htmlspecialchars($l['ISBN'] ?? '') ?>"
                 placeholder="Ex: 978-85-...">
        </div>

        <div class="col-md-4">
          <label class="form-label">Disponibilidade</label>
          <select class="form-select" name="disponivel">
            <option value="1" <?= ((int)$l['disponivel'] === 1) ? "selected" : "" ?>>Disponível</option>
            <option value="0" <?= ((int)$l['disponivel'] === 0) ? "selected" : "" ?>>Indisponível</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-brand" type="submit">
          <i class="bi bi-check2"></i>
          Atualizar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">
          Cancelar
        </a>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
