<?php
// auth/cadastrar_salvar.php
// - Primeiro cadastro cria ADMIN
// - Depois disso, só ADMIN cadastra (cria BIBLIOTECARIO)
// - Envia código de 6 dígitos por email para confirmar antes de logar

include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   CONFIG
========================= */
$base = "/biblioteca_senac";

// provedores permitidos
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

// código expira em 15 minutos
$CODIGO_MINUTOS = 15;

/* =========================
   HELPERS
========================= */
function email_domain(string $email): string {
  $email = trim(strtolower($email));
  $pos = strrpos($email, "@");
  if ($pos === false) return "";
  return substr($email, $pos + 1);
}

function only_digits(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

function normalize_email(string $email): string {
  // trim + lower + remove espaços acidentais
  $email = strtolower(trim($email));
  $email = preg_replace('/\s+/', '', $email);
  return $email;
}

function send_code_email(string $to, string $nome, string $codigo): bool {
  $subject = "Código de confirmação - Biblioteca";
  $nomeSafe = trim($nome) !== "" ? $nome : "usuário";

  $message  = "Olá, {$nomeSafe}!\n\n";
  $message .= "Seu código de confirmação é: {$codigo}\n\n";
  $message .= "Ele expira em 15 minutos.\n\n";
  $message .= "Se você não solicitou isso, ignore esta mensagem.\n";

  $headers  = "From: Biblioteca <no-reply@biblioteca.local>\r\n";
  $headers .= "Reply-To: no-reply@biblioteca.local\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

  return @mail($to, $subject, $message, $headers);
}

/* =========================
   DADOS DO FORM
========================= */
$nome  = trim($_POST["nome"] ?? "");
$email = normalize_email($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");
$cpf   = trim($_POST["cpf"] ?? "");

// repopular caso dê erro
$_SESSION["old_auth"] = [
  "nome"  => $nome,
  "email" => $email,
  "cpf"   => $cpf,
];

/* =========================
   VALIDAÇÕES
========================= */
$cpfDigits = only_digits($cpf);

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

// domínio permitido
$dom = email_domain($email);
if ($dom === "" || !in_array($dom, $ALLOWED_DOMAINS, true)) {
  flash_set("danger", "Use um email válido (Gmail, Outlook, Hotmail, Yahoo, iCloud, Live, Proton...).");
  header("Location: cadastrar.php");
  exit;
}

/* =========================
   REGRA DO PRIMEIRO CADASTRO
========================= */
$hasAny = false;
$res = mysqli_query($conn, "SELECT 1 FROM funcionarios LIMIT 1");
if ($res && mysqli_fetch_row($res)) $hasAny = true;

if ($hasAny) {
  // se já existe alguém, só ADMIN pode cadastrar
  require_once(__DIR__ . "/auth_guard.php");
  require_admin();
  $cargo = "BIBLIOTECARIO";
} else {
  $cargo = "ADMIN";
}

/* =========================
   DUPLICADOS
========================= */
$chkEmail = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($chkEmail, "s", $email);
mysqli_stmt_execute($chkEmail);
$rEmail = mysqli_stmt_get_result($chkEmail);
if ($rEmail && mysqli_fetch_row($rEmail)) {
  flash_set("warning", "Esse email já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

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
   GERA CÓDIGO + INSERE
========================= */
// senha
$hash = password_hash($senha, PASSWORD_DEFAULT);

// código de 6 dígitos
$codigo = (string)random_int(100000, 999999);
$codigoHash = hash("sha256", $codigo);

// expiração
$expira = (new DateTime())->modify("+{$CODIGO_MINUTOS} minutes")->format("Y-m-d H:i:s");

// insert com email_verificado=0 e código armazenado como hash
$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios (nome, email, cpf, senha, cargo, ativo, email_verificado, email_codigo_hash, email_codigo_expira)
  VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)
");
mysqli_stmt_bind_param($stmt, "sssssss", $nome, $email, $cpfDigits, $hash, $cargo, $codigoHash, $expira);

if (!mysqli_stmt_execute($stmt)) {
  $errno = mysqli_errno($conn);
  if ($errno === 1062) flash_set("warning", "Email ou CPF já cadastrado.");
  else flash_set("danger", "Erro ao cadastrar. Tente novamente.");
  header("Location: cadastrar.php");
  exit;
}

// tenta enviar e-mail com o código
$ok = send_code_email($email, $nome, $codigo);

unset($_SESSION["old_auth"]);

// se mail() falhar (XAMPP comum), a gente mostra o código pra teste
if ($ok) {
  flash_set("success", "Cadastro criado! Enviamos um código de confirmação no seu email.");
} else {
  flash_set("warning", "Cadastro criado, mas não consegui enviar email (SMTP). Código para teste: {$codigo}");
}

// manda pra tela de confirmação
header("Location: confirmar_email.php?email=" . urlencode($email));
exit;
