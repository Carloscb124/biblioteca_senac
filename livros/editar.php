<?php
$titulo_pagina = "Editar Livro";
include("../auth/auth_guard.php");
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

    <!-- enctype por causa do upload -->
    <form action="atualizar.php" method="post" class="form-grid" autocomplete="off" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Título</label>
          <input class="form-control" name="titulo" required value="<?= htmlspecialchars($l['titulo']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Autor</label>
          <input class="form-control" name="autor" value="<?= htmlspecialchars($l['autor'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Ano</label>
          <input class="form-control" type="number" name="ano_publicacao" min="0"
            value="<?= htmlspecialchars($l['ano_publicacao'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">ISBN</label>
          <input class="form-control" name="ISBN" maxlength="32"
            value="<?= htmlspecialchars($l['ISBN'] ?? '') ?>">
          <div class="form-text">Pode colar com traço. O sistema limpa e salva só números.</div>
        </div>

        <!-- Categoria simples (se você já usa CDD autocomplete em outros arquivos, pode plugar igual aqui) -->
        <div class="col-md-4">
          <label class="form-label">Categoria (CDD)</label>
          <input class="form-control" name="categoria" value="<?= htmlspecialchars($l['categoria'] ?? '') ?>">
        </div>

        <!-- Quantidade -->
        <div class="col-md-4">
          <label class="form-label">Total atual</label>
          <input class="form-control" value="<?= (int)($l['qtd_total'] ?? 0) ?>" disabled>
        </div>

        <div class="col-md-4">
          <label class="form-label">Adicionar exemplares</label>
          <input class="form-control" type="number" name="qtd_add" min="0" value="0">
        </div>

        <!-- Capa -->
        <div class="col-md-6">
          <label class="form-label">Capa por URL (opcional)</label>
          <input class="form-control" name="capa_url" placeholder="Cole um link de imagem (https://...)"
            value="<?= htmlspecialchars($l['capa_url'] ?? '') ?>">
          <div class="form-text">Se preencher, vira a capa do livro.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Upload de capa (opcional)</label>
          <input class="form-control" type="file" name="capa_upload" accept="image/*">
        </div>

        <div class="col-md-4">
          <label class="form-label">Disponível (ativo no acervo)</label>
          <select class="form-select" name="disponivel">
            <option value="1" <?= ((int)$l['disponivel'] === 1) ? "selected" : "" ?>>Sim</option>
            <option value="0" <?= ((int)$l['disponivel'] === 0) ? "selected" : "" ?>>Não</option>
          </select>
          <div class="form-text">Se estiver “Não”, ele fica baixado do acervo.</div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-brand" type="submit">
          <i class="bi bi-check2"></i>
          Atualizar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>



<?php include("../includes/footer.php"); ?>
