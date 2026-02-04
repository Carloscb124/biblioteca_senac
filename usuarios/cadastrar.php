<?php
$titulo_pagina = "Cadastrar Leitor";
include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Cadastrar Leitor</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" class="form-grid" autocomplete="off">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required placeholder="Ex: João da Silva">
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required placeholder="exemplo@email.com">
        </div>

        <div class="col-md-6">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="senha" required placeholder="••••••••">
        </div>

        <div class="col-md-6">
          <label class="form-label">Perfil</label>
          <select class="form-select" name="perfil">
            <option value="admin">Administrador</option>
            <option value="leitor" selected>Leitor</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-pill" type="submit">
          <i class="bi bi-check2"></i>
          Salvar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
