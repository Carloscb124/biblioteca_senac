<?php
// auth/cadastrar_salvar.php
include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================================================
   DOMÍNIOS PERMITIDOS (os mais comuns)
========================================================= */
$ALLOWED_DOMAINS = [
  "gmail.com",
  "outlook.com",
  "hotmail.com",
  "yahoo.com",
  "icloud.com",
  "live.com",
  "proton.me",
  "protonmail.com"
];

function email_domain(string $email): string {
  $email = trim(strtolower($email));
  $pos = strrpos($email, "@");
  if ($pos === false) return "";
  return substr($email, $pos + 1);
}

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

// Dados
$nome  = trim($_POST["nome"] ?? "");
$email = trim($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");
$cpf   = trim($_POST["cpf"] ?? "");

$emailLower = strtolower($email);
$cpfDigits = only_digits($cpf);

// Repopular (pra manter preenchido se der erro)
$_SESSION["old_auth"] = [
  "nome"  => $nome,
  "email" => $email,
  "cpf"   => $cpf,
];

// Validações básicas
if ($nome === "" || $email === "" || $senha === "" || $cpfDigits === "") {
  flash_set("danger", "Preencha nome, email, CPF e senha.");
  header("Location: cadastrar.php");
  exit;
}

if (strlen($senha) < 6) {
  flash_set("danger", "A senha deve ter pelo menos 6 caracteres.");
  header("Location: cadastrar.php");
  exit;
}

// CPF: 11 dígitos
if (strlen($cpfDigits) !== 11) {
  flash_set("danger", "CPF inválido. Informe 11 dígitos.");
  header("Location: cadastrar.php");
  exit;
}

// 1) valida formato do email
if (!filter_var($emailLower, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: cadastrar.php");
  exit;
}

// 2) valida domínio permitido
$dom = email_domain($emailLower);
if ($dom === "" || !in_array($dom, $ALLOWED_DOMAINS, true)) {
  flash_set("danger", "Use um email válido (Gmail, Outlook, Hotmail, Yahoo, iCloud, Live...).");
  header("Location: cadastrar.php");
  exit;
}

// Verifica se já existe algum funcionário no sistema
$hasAny = false;
$res = mysqli_query($conn, "SELECT 1 FROM funcionarios LIMIT 1");
if ($res && mysqli_fetch_row($res)) $hasAny = true;

/*
  Regra do seu sistema:
  - Se NÃO existe ninguém: permite criar o ADMIN inicial
  - Se JÁ existe: só ADMIN logado pode criar outros (e cria BIBLIOTECARIO)
*/
if ($hasAny) {
  require_once(__DIR__ . "/auth_guard.php");
  require_admin();
  $cargo = "BIBLIOTECARIO";
} else {
  $cargo = "ADMIN";
}

// Email único
$chkEmail = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($chkEmail, "s", $emailLower);
mysqli_stmt_execute($chkEmail);
$rEmail = mysqli_stmt_get_result($chkEmail);

if ($rEmail && mysqli_fetch_row($rEmail)) {
  flash_set("warning", "Esse email já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

// CPF único
$chkCpf = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE cpf = ? LIMIT 1");
mysqli_stmt_bind_param($chkCpf, "s", $cpfDigits);
mysqli_stmt_execute($chkCpf);
$rCpf = mysqli_stmt_get_result($chkCpf);

if ($rCpf && mysqli_fetch_row($rCpf)) {
  flash_set("warning", "Esse CPF já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

// Hash de senha
$hash = password_hash($senha, PASSWORD_DEFAULT);

// Insert (agora com CPF)
$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios (nome, email, cpf, senha, cargo, ativo)
  VALUES (?, ?, ?, ?, ?, 1)
");
mysqli_stmt_bind_param($stmt, "sssss", $nome, $emailLower, $cpfDigits, $hash, $cargo);

if (!mysqli_stmt_execute($stmt)) {
  // se por algum motivo bater em UNIQUE no banco:
  $errno = mysqli_errno($conn);
  if ($errno === 1062) {
    flash_set("warning", "Email ou CPF já cadastrado.");
  } else {
    flash_set("danger", "Erro ao cadastrar. Tente novamente.");
  }
  header("Location: cadastrar.php");
  exit;
}

unset($_SESSION["old_auth"]);
flash_set("success", "Cadastro realizado! Agora você pode entrar.");
header("Location: login.php");
exit;
