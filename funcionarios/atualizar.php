<?php
// funcionarios/atualizar.php
require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/flash.php");

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

$id = (int)($_POST["id"] ?? 0);
$nome = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$cpf = trim($_POST["cpf"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");
$cargoPost = strtoupper(trim($_POST["cargo"] ?? "BIBLIOTECARIO"));
$ativoPost = (int)($_POST["ativo"] ?? 1);
$novaSenha = (string)($_POST["nova_senha"] ?? "");

if ($id <= 0) {
  flash_set("danger", "Funcionário inválido.");
  header("Location: listar.php");
  exit;
}

if ($nome === "" || $email === "") {
  flash_set("danger", "Preencha nome e email.");
  header("Location: editar.php?id=".$id);
  exit;
}

$emailLower = strtolower($email);
if (!filter_var($emailLower, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: editar.php?id=".$id);
  exit;
}

if ($cargoPost !== "ADMIN" && $cargoPost !== "BIBLIOTECARIO") $cargoPost = "BIBLIOTECARIO";
$ativoPost = ($ativoPost === 1) ? 1 : 0;

$cpfDigits = only_digits($cpf);
if ($cpfDigits !== "" && strlen($cpfDigits) !== 11) {
  flash_set("danger", "CPF inválido. Informe 11 dígitos.");
  header("Location: editar.php?id=".$id);
  exit;
}

if ($novaSenha !== "" && strlen($novaSenha) < 6) {
  flash_set("danger", "A nova senha deve ter pelo menos 6 caracteres.");
  header("Location: editar.php?id=".$id);
  exit;
}

$meuId = (int)($_SESSION["auth"]["id"] ?? 0);
$editandoEu = ($meuId === $id);

// trava: não deixa você se desativar nem mudar cargo via POST
if ($editandoEu) {
  $stmtMe = mysqli_prepare($conn, "SELECT cargo, ativo FROM funcionarios WHERE id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmtMe, "i", $id);
  mysqli_stmt_execute($stmtMe);
  $me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtMe));

  if ($me) {
    $cargoPost = strtoupper(trim($me["cargo"] ?? "BIBLIOTECARIO"));
    if ($cargoPost !== "ADMIN" && $cargoPost !== "BIBLIOTECARIO") $cargoPost = "BIBLIOTECARIO";
    $ativoPost = ((int)$me["ativo"] === 1) ? 1 : 0;
  }
}

// email único
$chk = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE email = ? AND id <> ? LIMIT 1");
mysqli_stmt_bind_param($chk, "si", $emailLower, $id);
mysqli_stmt_execute($chk);
$r = mysqli_stmt_get_result($chk);
if ($r && mysqli_fetch_row($r)) {
  flash_set("warning", "Esse email já está em uso.");
  header("Location: editar.php?id=".$id);
  exit;
}

// cpf único (se informado)
if ($cpfDigits !== "") {
  $chkCpf = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE cpf = ? AND id <> ? LIMIT 1");
  mysqli_stmt_bind_param($chkCpf, "si", $cpfDigits, $id);
  mysqli_stmt_execute($chkCpf);
  $rCpf = mysqli_stmt_get_result($chkCpf);
  if ($rCpf && mysqli_fetch_row($rCpf)) {
    flash_set("warning", "Esse CPF já está em uso.");
    header("Location: editar.php?id=".$id);
    exit;
  }
}

if ($novaSenha !== "") {
  $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

  $stmt = mysqli_prepare($conn, "
    UPDATE funcionarios
    SET nome = ?, email = ?, cpf = ?, telefone = ?, cargo = ?, ativo = ?, senha = ?
    WHERE id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmt, "sssssisi",
    $nome, $emailLower, $cpfDigits, $telefone, $cargoPost, $ativoPost, $hash, $id
  );
} else {
  $stmt = mysqli_prepare($conn, "
    UPDATE funcionarios
    SET nome = ?, email = ?, cpf = ?, telefone = ?, cargo = ?, ativo = ?
    WHERE id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmt, "sssssii",
    $nome, $emailLower, $cpfDigits, $telefone, $cargoPost, $ativoPost, $id
  );
}

if (!mysqli_stmt_execute($stmt)) {
  flash_set("danger", "Erro ao atualizar funcionário.");
  header("Location: editar.php?id=".$id);
  exit;
}

flash_set("success", "Funcionário atualizado!");
header("Location: listar.php");
exit;
