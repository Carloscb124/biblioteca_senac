<?php
// auth/reenviar_codigo.php
// Reenvia um novo código (com throttle simples por sessão)

include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

function normalize_email(string $email): string {
  $email = strtolower(trim($email));
  $email = preg_replace('/\s+/', '', $email);
  return $email;
}

function send_code_email(string $to, string $nome, string $codigo): bool {
  $subject = "Seu novo código - Biblioteca";

  $message  = "Olá, {$nome}!\n\n";
  $message .= "Seu novo código de confirmação é: {$codigo}\n\n";
  $message .= "Ele expira em 15 minutos.\n";

  $headers  = "From: Biblioteca <no-reply@biblioteca.local>\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

  return @mail($to, $subject, $message, $headers);
}

$email = normalize_email($_GET["email"] ?? "");
if ($email === "") {
  flash_set("danger", "Email inválido.");
  header("Location: login.php");
  exit;
}

// throttle: 1 envio a cada 30 segundos por sessão (pra não virar metralhadora)
$last = (int)($_SESSION["last_resend_code_ts"] ?? 0);
if ($last > 0 && (time() - $last) < 30) {
  flash_set("warning", "Calma aí, chefia. Espera uns segundos e tenta de novo.");
  header("Location: confirmar_email.php?email=" . urlencode($email));
  exit;
}

// busca funcionário
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

if ((int)$f["email_verificado"] === 1) {
  flash_set("success", "Seu email já está confirmado. Pode entrar.");
  header("Location: login.php");
  exit;
}

// gera novo código
$codigo = (string)random_int(100000, 999999);
$codigoHash = hash("sha256", $codigo);
$expira = (new DateTime())->modify("+15 minutes")->format("Y-m-d H:i:s");

// salva no banco
$upd = mysqli_prepare($conn, "
  UPDATE funcionarios
  SET email_codigo_hash = ?,
      email_codigo_expira = ?
  WHERE id = ?
  LIMIT 1
");
$id = (int)$f["id"];
mysqli_stmt_bind_param($upd, "ssi", $codigoHash, $expira, $id);
mysqli_stmt_execute($upd);

// envia
$ok = send_code_email($email, $f["nome"], $codigo);

$_SESSION["last_resend_code_ts"] = time();

if ($ok) {
  flash_set("success", "Novo código enviado! Confere sua caixa de entrada (e spam).");
} else {
  flash_set("warning", "Não consegui enviar email (SMTP). Código para teste: {$codigo}");
}

header("Location: confirmar_email.php?email=" . urlencode($email));
exit;
