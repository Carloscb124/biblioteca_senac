<?php
// auth/entrar.php
// - Faz login normal
// - Só exige confirmação de email para ADMIN

include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

$base = "/biblioteca_senac";

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

function normalize_email(string $email): string {
  $email = strtolower(trim($email));
  $email = preg_replace('/\s+/', '', $email);
  return $email;
}

$email = normalize_email($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");

if ($email === "" || $senha === "") {
  flash_set("danger", "Preencha email e senha.");
  header("Location: login.php");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: login.php");
  exit;
}

$dom = email_domain($email);
if ($dom === "" || !in_array($dom, $ALLOWED_DOMAINS, true)) {
  flash_set("danger", "Email não permitido. Use um provedor comum (Gmail, Outlook...).");
  header("Location: login.php");
  exit;
}

// Busca funcionário ativo
$stmt = mysqli_prepare($conn, "
  SELECT id, nome, email, senha, cargo, email_verificado
  FROM funcionarios
  WHERE email = ? AND ativo = 1
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$f = $res ? mysqli_fetch_assoc($res) : null;

// senha
if (!$f || !password_verify($senha, $f["senha"])) {
  flash_set("danger", "Email ou senha inválidos.");
  header("Location: login.php");
  exit;
}

/*
  ✅ REGRA CORRIGIDA:
  Só ADMIN precisa confirmar email.
  Bibliotecário nunca é travado por confirmação.
*/
$cargo = strtoupper((string)($f["cargo"] ?? ""));
$emailVer = (int)($f["email_verificado"] ?? 0);

if ($cargo === "ADMIN" && $emailVer !== 1) {
  flash_set("warning", "Seu email ainda não foi confirmado. Digite o código enviado.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

// Login OK
$_SESSION["auth"] = [
  "id"    => (int)$f["id"],
  "nome"  => $f["nome"],
  "email" => $f["email"],
  "cargo" => $cargo
];

flash_set("success", "Bem-vindo(a), {$f["nome"]}!");
header("Location: {$base}/index.php");
exit;
