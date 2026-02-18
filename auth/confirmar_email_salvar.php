<?php
// auth/confirmar_email_salvar.php
// Confere código (hash) + expiração e marca email como verificado

include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

function normalize_email(string $email): string {
  $email = strtolower(trim($email));
  $email = preg_replace('/\s+/', '', $email);
  return $email;
}

$email  = normalize_email($_POST["email"] ?? "");
$codigo = preg_replace('/\D+/', '', (string)($_POST["codigo"] ?? ""));

if ($email === "" || strlen($codigo) !== 6) {
  flash_set("danger", "Informe um código válido de 6 dígitos.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

$codigoHash = hash("sha256", $codigo);

// busca funcionário
$stmt = mysqli_prepare($conn, "
  SELECT id, email_verificado, email_codigo_hash, email_codigo_expira
  FROM funcionarios
  WHERE email = ? AND ativo = 1
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$f = $res ? mysqli_fetch_assoc($res) : null;

if (!$f) {
  flash_set("danger", "Conta não encontrada.");
  header("Location: login.php");
  exit;
}

if ((int)$f["email_verificado"] === 1) {
  flash_set("success", "Seu email já está confirmado. Pode entrar.");
  header("Location: login.php");
  exit;
}

// expiração
$exp = $f["email_codigo_expira"] ?? null;
if (!$exp || strtotime($exp) < time()) {
  flash_set("danger", "Código expirado. Reenvie um novo código.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

// compara hash
if (!hash_equals((string)$f["email_codigo_hash"], $codigoHash)) {
  flash_set("danger", "Código incorreto.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

// confirma email
$upd = mysqli_prepare($conn, "
  UPDATE funcionarios
  SET email_verificado = 1,
      email_codigo_hash = NULL,
      email_codigo_expira = NULL
  WHERE id = ?
  LIMIT 1
");
$id = (int)$f["id"];
mysqli_stmt_bind_param($upd, "i", $id);

if (!mysqli_stmt_execute($upd)) {
  flash_set("danger", "Não consegui confirmar seu email. Tente novamente.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

flash_set("success", "Email confirmado! Agora você já pode entrar.");
header("Location: login.php");
exit;
