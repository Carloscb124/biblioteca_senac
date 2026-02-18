<?php
// auth/cadastrar_salvar.php
// - Primeiro cadastro: ADMIN + confirmação por código
// - Cadastros feitos por ADMIN: BIBLIOTECARIO já verificado (sem código)

include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   CONFIG
========================= */
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

$CODIGO_MINUTOS = 15;

/* =========================
   HELPERS
========================= */
function normalize_email(string $email): string {
  $email = strtolower(trim($email));
  // remove espaços invisíveis tipo "email @gmail.com"
  $email = preg_replace('/\s+/', '', $email);
  return $email;
}

function email_domain(string $email): string {
  $pos = strrpos($email, "@");
  if ($pos === false) return "";
  return substr($email, $pos + 1);
}

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

/**
 * Envio simples via mail(). Em XAMPP pode falhar sem SMTP.
 */
function send_code_email(string $to, string $nome, string $codigo, int $minutos): bool {
  $subject = "Código de confirmação - Biblioteca";
  $nomeSafe = trim($nome) !== "" ? $nome : "usuário";

  $message  = "Olá, {$nomeSafe}!\n\n";
  $message .= "Seu código de confirmação é: {$codigo}\n\n";
  $message .= "Ele expira em {$minutos} minutos.\n\n";
  $message .= "Se você não solicitou isso, ignore.\n";

  $headers  = "From: Biblioteca <no-reply@biblioteca.local>\r\n";
  $headers .= "Reply-To: no-reply@biblioteca.local\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

  return @mail($to, $subject, $message, $headers);
}

/* =========================
   INPUT
========================= */
$nome  = trim($_POST["nome"] ?? "");
$email = normalize_email($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");
$cpf   = trim($_POST["cpf"] ?? "");

$cpfDigits = only_digits($cpf);

// mantém preenchido se der erro
$_SESSION["old_auth"] = [
  "nome"  => $nome,
  "email" => $email,
  "cpf"   => $cpf,
];

/* =========================
   VALIDAÇÕES
========================= */
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

if (strlen($cpfDigits) !== 11) {
  flash_set("danger", "CPF inválido. Informe 11 dígitos.");
  header("Location: cadastrar.php");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: cadastrar.php");
  exit;
}

$dom = email_domain($email);
if ($dom === "" || !in_array($dom, $ALLOWED_DOMAINS, true)) {
  flash_set("danger", "Use um email válido (Gmail, Outlook, Hotmail, Yahoo, iCloud, Live...).");
  header("Location: cadastrar.php");
  exit;
}

/* =========================
   REGRA DO SISTEMA
========================= */
// existe alguém já?
$hasAny = false;
$resAny = mysqli_query($conn, "SELECT 1 FROM funcionarios LIMIT 1");
if ($resAny && mysqli_fetch_row($resAny)) $hasAny = true;

if ($hasAny) {
  // só admin logado pode cadastrar depois do primeiro
  require_once(__DIR__ . "/auth_guard.php");
  require_admin();

  $cargo = "BIBLIOTECARIO";
  $emailVerificado = 1; // ✅ bibliotecário não precisa de código
} else {
  $cargo = "ADMIN";
  $emailVerificado = 0; // ✅ admin inicial precisa confirmar
}

/* =========================
   DUPLICADOS
========================= */
// email único
$chkEmail = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($chkEmail, "s", $email);
mysqli_stmt_execute($chkEmail);
$rEmail = mysqli_stmt_get_result($chkEmail);
if ($rEmail && mysqli_fetch_row($rEmail)) {
  flash_set("warning", "Esse email já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

// cpf único
$chkCpf = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE cpf = ? LIMIT 1");
mysqli_stmt_bind_param($chkCpf, "s", $cpfDigits);
mysqli_stmt_execute($chkCpf);
$rCpf = mysqli_stmt_get_result($chkCpf);
if ($rCpf && mysqli_fetch_row($rCpf)) {
  flash_set("warning", "Esse CPF já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

/* =========================
   SENHA
========================= */
$hash = password_hash($senha, PASSWORD_DEFAULT);

/* =========================
   CÓDIGO (somente ADMIN inicial)
========================= */
$codigo = null;
$codigoHash = null;
$expira = null;

if ($emailVerificado === 0) {
  $codigo = (string)random_int(100000, 999999);
  $codigoHash = hash("sha256", $codigo);
  $expira = (new DateTime())->modify("+{$CODIGO_MINUTOS} minutes")->format("Y-m-d H:i:s");
}

/* =========================
   INSERT
   Campos esperados na tabela:
   nome, email, cpf, senha, cargo, ativo,
   email_verificado, email_codigo_hash, email_codigo_expira
========================= */
$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios
    (nome, email, cpf, senha, cargo, ativo, email_verificado, email_codigo_hash, email_codigo_expira)
  VALUES
    (?, ?, ?, ?, ?, 1, ?, ?, ?)
");

// tipos: nome(s) email(s) cpf(s) senha(s) cargo(s) email_verificado(i) codigo_hash(s) expira(s)
mysqli_stmt_bind_param(
  $stmt,
  "sssssiss",
  $nome,
  $email,
  $cpfDigits,
  $hash,
  $cargo,
  $emailVerificado,
  $codigoHash,
  $expira
);

if (!mysqli_stmt_execute($stmt)) {
  $errno = mysqli_errno($conn);
  if ($errno === 1062) flash_set("warning", "Email ou CPF já cadastrado.");
  else flash_set("danger", "Erro ao cadastrar. Tente novamente.");
  header("Location: cadastrar.php");
  exit;
}

unset($_SESSION["old_auth"]);

/* =========================
   PÓS CADASTRO
========================= */
if ($emailVerificado === 1) {
  // bibliotecário criado por admin: entra direto, sem confirmação
  flash_set("success", "Funcionário criado! Ele já pode entrar no sistema.");
  header("Location: cadastrar.php");
  exit;
}

// admin inicial: tenta enviar o código
$ok = send_code_email($email, $nome, $codigo, $CODIGO_MINUTOS);

if ($ok) {
  flash_set("success", "Cadastro criado! Enviamos um código de confirmação no seu email.");
} else {
  flash_set("warning", "Cadastro criado, mas não consegui enviar email (SMTP). Código para teste: {$codigo}");
}

// manda pra tela de confirmação
header("Location: confirmar_email.php?email=" . urlencode($email));
exit;
