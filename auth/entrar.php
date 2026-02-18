<?php
// auth/entrar.php
include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

/* mesmos domínios do cadastro */
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

$email = trim($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");

if ($email === "" || $senha === "") {
  flash_set("danger", "Preencha email e senha.");
  header("Location: login.php");
  exit;
}

$emailLower = strtolower($email);

// valida formato
if (!filter_var($emailLower, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: login.php");
  exit;
}

// valida domínio
$dom = email_domain($emailLower);
if ($dom === "" || !in_array($dom, $ALLOWED_DOMAINS, true)) {
  flash_set("danger", "Email não permitido. Use um provedor comum (Gmail, Outlook...).");
  header("Location: login.php");
  exit;
}

// Busca usuário ativo
$stmt = mysqli_prepare($conn, "
  SELECT id, nome, email, senha, cargo
  FROM funcionarios
  WHERE email = ? AND ativo = 1
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "s", $emailLower);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$f = mysqli_fetch_assoc($res);

if (!$f || !password_verify($senha, $f["senha"])) {
  flash_set("danger", "Email ou senha inválidos.");
  header("Location: login.php");
  exit;
}

$cargo = strtoupper(trim($f["cargo"] ?? "BIBLIOTECARIO"));
if ($cargo !== "ADMIN" && $cargo !== "BIBLIOTECARIO") $cargo = "BIBLIOTECARIO";

$_SESSION["auth"] = [
  "id"    => (int)$f["id"],
  "nome"  => $f["nome"],
  "email" => $f["email"],
  "cargo" => $cargo,
];

flash_set("success", "Bem-vindo(a), " . $f["nome"] . "!");
header("Location: ../index.php");
exit;
