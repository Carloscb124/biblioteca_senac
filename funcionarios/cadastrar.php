<?php
$titulo_pagina = "Cadastrar Funcionário";
require_once("../auth/auth_guard.php");
require_admin();

include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Cadastrar Bibliotecário</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" autocomplete="off">
      <div class="row g-3">

        <div class="col-12 col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required placeholder="Ex: João da Silva">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required placeholder="exemplo@email.com">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="senha" required placeholder="••••••••">
          <div class="form-text">Mínimo 6 caracteres.</div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Cargo</label>
          <input class="form-control" value="Bibliotecário" disabled>
        </div>

        <div class="col-12">
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-pill btn-success" type="submit">
              <i class="bi bi-check2"></i>
              Salvar
            </button>

            <a class="btn btn-pill btn-outline-secondary" href="listar.php">
              Cancelar
            </a>
          </div>
        </div>

      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
