<?php
// auth/reenviar_codigo.php
// Reenvia um novo código usando SMTP (PHPMailer) + throttle simples por sessão

include("../conexao.php");
include("../includes/flash.php");
include("../includes/mailer.php"); // ✅ usa mailer_send_code()

if (session_status() === PHP_SESSION_NONE) session_start();

function normalize_email(string $email): string {
  $email = strtolower(trim($email));
  $email = preg_replace('/\s+/', '', $email);
  return $email;
}

$email = normalize_email($_GET["email"] ?? "");
if ($email === "") {
  flash_set("danger", "Email inválido.");
  header("Location: login.php");
  exit;
}

/* =========================
   Throttle: 1 envio a cada 30s por sessão
========================= */
$last = (int)($_SESSION["last_resend_code_ts"] ?? 0);
if ($last > 0 && (time() - $last) < 30) {
  flash_set("warning", "Calma aí, chefia. Espera uns segundos e tenta de novo.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

/* =========================
   Busca funcionário ativo
========================= */
$stmt = mysqli_prepare($conn, "
  SELECT id, nome, email_verificado
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

if ((int)($f["email_verificado"] ?? 0) === 1) {
  flash_set("success", "Seu email já está confirmado. Pode entrar.");
  header("Location: login.php");
  exit;
}

/* =========================
   Gera novo código + salva no banco
========================= */
$CODIGO_MINUTOS = 15;

$codigo = (string)random_int(100000, 999999);
$codigoHash = hash("sha256", $codigo);
$expira = (new DateTime())->modify("+{$CODIGO_MINUTOS} minutes")->format("Y-m-d H:i:s");

$upd = mysqli_prepare($conn, "
  UPDATE funcionarios
  SET email_codigo_hash = ?,
      email_codigo_expira = ?
  WHERE id = ?
  LIMIT 1
");
$id = (int)$f["id"];
mysqli_stmt_bind_param($upd, "ssi", $codigoHash, $expira, $id);

if (!mysqli_stmt_execute($upd)) {
  flash_set("danger", "Não consegui atualizar o código. Tente novamente.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

/* =========================
   Envia por SMTP (PHPMailer)
========================= */
$ok = mailer_send_code($email, $f["nome"], $codigo, $CODIGO_MINUTOS);

// marca envio (mesmo se falhar, pra não virar spam-click)
$_SESSION["last_resend_code_ts"] = time();

if ($ok) {
  flash_set("success", "Novo código enviado! Confere sua caixa de entrada (e spam).");
} else {
  // fallback pra dev (se SMTP falhar)
  flash_set("warning", "Não consegui enviar email (SMTP). Código para teste: {$codigo}");
}

header("Location: confirmar_email.php?email=" . urlencode($email));
exit;
