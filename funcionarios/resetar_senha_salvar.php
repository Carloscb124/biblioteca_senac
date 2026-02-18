<?php
// funcionarios/resetar_senha_salvar.php
require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST["id"] ?? 0);
$senha = (string)($_POST["senha"] ?? "");
$senha2 = (string)($_POST["senha2"] ?? "");

if ($id <= 0) {
  flash_set("danger", "Funcionário inválido.");
  header("Location: listar.php");
  exit;
}

$meuId = (int)($_SESSION["auth"]["id"] ?? 0);
if ($id === $meuId) {
  flash_set("warning", "Use a tela de editar para sua própria senha.");
  header("Location: editar.php?id=".$id);
  exit;
}

if ($senha === "" || $senha2 === "") {
  flash_set("danger", "Preencha as duas senhas.");
  header("Location: resetar_senha.php?id=".$id);
  exit;
}

if ($senha !== $senha2) {
  flash_set("danger", "As senhas não conferem.");
  header("Location: resetar_senha.php?id=".$id);
  exit;
}

if (strlen($senha) < 6) {
  flash_set("danger", "A senha deve ter pelo menos 6 caracteres.");
  header("Location: resetar_senha.php?id=".$id);
  exit;
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "UPDATE funcionarios SET senha = ? WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "si", $hash, $id);

if (!mysqli_stmt_execute($stmt)) {
  flash_set("danger", "Erro ao resetar senha.");
  header("Location: resetar_senha.php?id=".$id);
  exit;
}

flash_set("success", "Senha resetada com sucesso!");
header("Location: listar.php");
exit;
