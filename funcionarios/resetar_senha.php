<?php
// funcionarios/resetar_senha.php
$titulo_pagina = "Resetar Senha";

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

$meuId = (int)($_SESSION["auth"]["id"] ?? 0);
if ($id === $meuId) {
  flash_set("warning", "Para sua própria senha, use a tela de editar.");
  header("Location: editar.php?id=".$id);
  exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, nome, email FROM funcionarios WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$f) {
  flash_set("danger", "Funcionário não encontrado.");
  header("Location: listar.php");
  exit;
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Resetar Senha</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <div class="alert alert-warning" style="border-radius:16px;">
      Você vai definir uma nova senha para <strong><?= htmlspecialchars($f["nome"]) ?></strong>.
    </div>

    <form action="resetar_senha_salvar.php" method="post" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$f["id"] ?>">

      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Nova senha</label>
          <input class="form-control" type="password" name="senha" required placeholder="Mínimo 6 caracteres">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Confirmar nova senha</label>
          <input class="form-control" type="password" name="senha2" required placeholder="Repita a senha">
        </div>

        <div class="col-12">
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-pill btn-warning" type="submit">
              <i class="bi bi-key"></i> Resetar
            </button>
            <a class="btn btn-pill btn-outline-secondary" href="listar.php">Cancelar</a>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
