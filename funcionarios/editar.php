<?php
// funcionarios/editar.php
$titulo_pagina = "Editar Funcionário";

require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/header.php");

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  flash_set("danger", "Funcionário inválido.");
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "
  SELECT id, nome, email, cpf, telefone, cargo, ativo
  FROM funcionarios
  WHERE id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$f) {
  flash_set("danger", "Funcionário não encontrado.");
  header("Location: listar.php");
  exit;
}

$meuId = (int)($_SESSION["auth"]["id"] ?? 0);
$editandoEu = ($meuId === (int)$f["id"]);

$cargo = strtoupper(trim($f["cargo"] ?? "BIBLIOTECARIO"));
if ($cargo !== "ADMIN" && $cargo !== "BIBLIOTECARIO") $cargo = "BIBLIOTECARIO";
$ativo = ((int)$f["ativo"] === 1);
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Editar Funcionário</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <?php if ($editandoEu): ?>
      <div class="alert alert-info" style="border-radius:16px;">
        Você está editando seu próprio usuário. Algumas opções ficam travadas pra evitar acidente.
      </div>
    <?php endif; ?>

    <form action="atualizar.php" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$f["id"] ?>">

      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required value="<?= htmlspecialchars($f["nome"] ?? "") ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($f["email"] ?? "") ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">CPF</label>
          <input class="form-control" name="cpf" id="cpf" maxlength="14"
                 placeholder="000.000.000-00" value="<?= htmlspecialchars($f["cpf"] ?? "") ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Telefone</label>
          <input class="form-control" name="telefone" id="telefone" maxlength="15"
                 placeholder="(00) 00000-0000" value="<?= htmlspecialchars($f["telefone"] ?? "") ?>">
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Cargo</label>
          <select class="form-select" name="cargo" <?= $editandoEu ? "disabled" : "" ?>>
            <option value="BIBLIOTECARIO" <?= $cargo === "BIBLIOTECARIO" ? "selected" : "" ?>>Bibliotecário</option>
            <option value="ADMIN" <?= $cargo === "ADMIN" ? "selected" : "" ?>>Admin</option>
          </select>
          <?php if ($editandoEu): ?>
            <div class="form-text">Você não pode mudar seu próprio cargo aqui.</div>
            <input type="hidden" name="cargo" value="<?= htmlspecialchars($cargo) ?>">
          <?php endif; ?>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Status</label>
          <select class="form-select" name="ativo" <?= $editandoEu ? "disabled" : "" ?>>
            <option value="1" <?= $ativo ? "selected" : "" ?>>Ativo</option>
            <option value="0" <?= !$ativo ? "selected" : "" ?>>Desativado</option>
          </select>
          <?php if ($editandoEu): ?>
            <div class="form-text">Você não pode se desativar aqui.</div>
            <input type="hidden" name="ativo" value="<?= $ativo ? 1 : 0 ?>">
          <?php endif; ?>
        </div>

        <div class="col-12 col-md-8">
          <label class="form-label">Nova senha (opcional)</label>
          <input class="form-control" type="password" name="nova_senha" placeholder="Deixe em branco para manter">
          <div class="form-text">Mínimo 6 caracteres, se preencher.</div>
        </div>

        <div class="col-12">
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-pill btn-success" type="submit">
              <i class="bi bi-save"></i> Salvar alterações
            </button>
            <a class="btn btn-pill btn-outline-secondary" href="listar.php">Cancelar</a>
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
      return d
        .replace(/^(\d{0,2})/, "($1")
        .replace(/^\((\d{2})/, "($1) ")
        .replace(/(\d{4})(\d{0,4})$/, "$1-$2")
        .replace(/-$/, "");
    }
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
    tel.addEventListener("input", () => tel.value = maskPhone(tel.value));
    tel.addEventListener("blur",  () => tel.value = maskPhone(tel.value));
    tel.value = maskPhone(tel.value);
  }

  if (cpf) {
    cpf.addEventListener("input", () => cpf.value = maskCPF(cpf.value));
    cpf.addEventListener("blur",  () => cpf.value = maskCPF(cpf.value));
    cpf.value = maskCPF(cpf.value);
  }
})();
</script>

<?php include("../includes/footer.php"); ?>
