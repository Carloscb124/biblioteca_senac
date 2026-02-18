<?php
$titulo_pagina = "Cadastrar Funcionário";
require_once("../auth/auth_guard.php");
require_admin();

include("../includes/header.php");

$meuCargo = strtoupper(trim($_SESSION["auth"]["cargo"] ?? "BIBLIOTECARIO"));
if ($meuCargo !== "ADMIN") $meuCargo = "BIBLIOTECARIO";
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Cadastrar Funcionário</h2>

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

        <div class="col-12 col-md-4">
          <label class="form-label">CPF</label>
          <input class="form-control" name="cpf" id="cpf" placeholder="000.000.000-00" maxlength="14">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Telefone</label>
          <input class="form-control" name="telefone" id="telefone" placeholder="(00) 00000-0000" maxlength="15">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Cargo</label>
          <select class="form-select" name="cargo">
            <option value="BIBLIOTECARIO" selected>Bibliotecário</option>
            <?php if ($meuCargo === "ADMIN"): ?>
              <option value="ADMIN">Admin</option>
            <?php endif; ?>
          </select>
          <div class="form-text">Só ADMIN pode criar outro ADMIN.</div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="senha" required placeholder="••••••••">
          <div class="form-text">Mínimo 6 caracteres.</div>
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

<script>
(function(){
  const tel = document.getElementById("telefone");
  const cpf = document.getElementById("cpf");

  function onlyDigits(v){ return (v || "").replace(/\D+/g, ""); }

  function maskPhone(v){
    const d = onlyDigits(v).slice(0, 11);
    if (d.length <= 10) {
      // (99) 9999-9999
      return d
        .replace(/^(\d{0,2})/, "($1")
        .replace(/^\((\d{2})/, "($1) ")
        .replace(/(\d{4})(\d{0,4})$/, "$1-$2")
        .replace(/-$/, "");
    }
    // (99) 99999-9999
    return d
      .replace(/^(\d{0,2})/, "($1")
      .replace(/^\((\d{2})/, "($1) ")
      .replace(/(\d{5})(\d{0,4})$/, "$1-$2")
      .replace(/-$/, "");
  }

  function maskCPF(v){
    const d = onlyDigits(v).slice(0, 11);
    return d
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
  }

  if (tel) {
    tel.addEventListener("input", () => {
      tel.value = maskPhone(tel.value);
    });
    tel.addEventListener("blur", () => {
      tel.value = maskPhone(tel.value);
    });
  }

  if (cpf) {
    cpf.addEventListener("input", () => {
      cpf.value = maskCPF(cpf.value);
    });
    cpf.addEventListener("blur", () => {
      cpf.value = maskCPF(cpf.value);
    });
  }
})();
</script>

<?php include("../includes/footer.php"); ?>
