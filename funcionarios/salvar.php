<?php
require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/flash.php");

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

$nome  = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");

$cpf = trim($_POST["cpf"] ?? "");
$telefone = trim($_POST["telefone"] ?? "");

$cargoPost = strtoupper(trim($_POST["cargo"] ?? "BIBLIOTECARIO"));
if ($cargoPost !== "ADMIN" && $cargoPost !== "BIBLIOTECARIO") $cargoPost = "BIBLIOTECARIO";

if ($nome === "" || $email === "" || $senha === "") {
  flash_set("danger", "Preencha nome, email e senha.");
  header("Location: cadastrar.php");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: cadastrar.php");
  exit;
}

if (strlen($senha) < 6) {
  flash_set("danger", "A senha deve ter pelo menos 6 caracteres.");
  header("Location: cadastrar.php");
  exit;
}

// sanitiza CPF
$cpfDigits = only_digits($cpf);
if ($cpfDigits !== "" && strlen($cpfDigits) !== 11) {
  flash_set("danger", "CPF inválido. Informe 11 dígitos (com ou sem pontuação).");
  header("Location: cadastrar.php");
  exit;
}

// regra: só ADMIN pode criar ADMIN (validação de verdade aqui)
$meuCargo = strtoupper(trim($_SESSION["auth"]["cargo"] ?? "BIBLIOTECARIO"));
if ($meuCargo !== "ADMIN") $meuCargo = "BIBLIOTECARIO";

if ($cargoPost === "ADMIN" && $meuCargo !== "ADMIN") {
  flash_set("danger", "Você não tem permissão para criar administradores.");
  header("Location: cadastrar.php");
  exit;
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

// email único
$chk = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($chk, "s", $email);
mysqli_stmt_execute($chk);
$r = mysqli_stmt_get_result($chk);
if ($r && mysqli_fetch_row($r)) {
  flash_set("warning", "Esse email já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

// cpf único (opcional, mas recomendado se você quiser)
if ($cpfDigits !== "") {
  $chkCpf = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE cpf = ? LIMIT 1");
  mysqli_stmt_bind_param($chkCpf, "s", $cpfDigits);
  mysqli_stmt_execute($chkCpf);
  $rCpf = mysqli_stmt_get_result($chkCpf);
  if ($rCpf && mysqli_fetch_row($rCpf)) {
    flash_set("warning", "Esse CPF já está cadastrado.");
    header("Location: cadastrar.php");
    exit;
  }
}

$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios (nome, email, cpf, telefone, senha, cargo, ativo)
  VALUES (?, ?, ?, ?, ?, ?, 1)
");

mysqli_stmt_bind_param(
  $stmt,
  "ssssss",
  $nome,
  $email,
  $cpfDigits,     // salva só números
  $telefone,
  $hash,
  $cargoPost
);

if (!mysqli_stmt_execute($stmt)) {
  flash_set("danger", "Erro ao cadastrar funcionário.");
  header("Location: cadastrar.php");
  exit;
}

flash_set("success", "Funcionário cadastrado com sucesso!");
header("Location: listar.php");
exit;
