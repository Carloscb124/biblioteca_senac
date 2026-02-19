<?php
// auth/cadastrar_salvar.php
// Regras:
// - Primeiro cadastro do sistema: cria ADMIN e exige confirmação por código
// - Cadastros feitos por ADMIN logado: pode criar ADMIN ou BIBLIOTECARIO, já verificado (sem código)

include("../conexao.php");
include("../includes/flash.php");
include("../includes/mailer.php");

if (session_status() === PHP_SESSION_NONE) session_start();

/* =========================
   CONFIG
========================= */
$ALLOWED_DOMAINS = [
  "gmail.com","outlook.com","hotmail.com","yahoo.com","icloud.com","live.com","proton.me","protonmail.com"
];

$CODIGO_MINUTOS = 15;

/* =========================
   HELPERS
========================= */
function normalize_email(string $email): string {
  $email = strtolower(trim($email));
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

/* =========================
   INPUT
========================= */
$nome  = trim($_POST["nome"] ?? "");
$email = normalize_email($_POST["email"] ?? "");
$senha = (string)($_POST["senha"] ?? "");
$cpf   = trim($_POST["cpf"] ?? "");
$cpfDigits = only_digits($cpf);

// cargo opcional (quando admin cadastra alguém):
// valores aceitos: ADMIN ou BIBLIOTECARIO
$cargoPost = strtoupper(trim((string)($_POST["cargo"] ?? "")));

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
  flash_set("danger", "Use um email válido (Gmail, Outlook, Hotmail...).");
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
  // daqui pra frente só ADMIN logado cadastra
  require_once(__DIR__ . "/auth_guard.php");
  require_admin();

  // Se o admin escolheu o cargo, usa. Se não, padrão: bibliotecário
  if ($cargoPost === "ADMIN" || $cargoPost === "BIBLIOTECARIO") $cargo = $cargoPost;
  else $cargo = "BIBLIOTECARIO";

  // ✅ qualquer usuário criado por admin já nasce verificado
  $emailVerificado = 1;

} else {
  // primeiro cadastro do sistema
  $cargo = "ADMIN";
  $emailVerificado = 0; // precisa confirmar por código
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
   SENHA
========================= */
$hash = password_hash($senha, PASSWORD_DEFAULT);

/* =========================
   INSERÇÃO (blindada)
========================= */

// Caso cadastro por ADMIN (qualquer cargo): insere já verificado, sem código
if ($hasAny) {

  $stmt = mysqli_prepare($conn, "
    INSERT INTO funcionarios
      (nome, email, cpf, senha, cargo, ativo, email_verificado, email_codigo_hash, email_codigo_expira)
    VALUES
      (?, ?, ?, ?, ?, 1, 1, NULL, NULL)
  ");

  mysqli_stmt_bind_param($stmt, "sssss", $nome, $email, $cpfDigits, $hash, $cargo);

  if (!mysqli_stmt_execute($stmt)) {
    flash_set("danger", "Erro ao cadastrar usuário.");
    header("Location: cadastrar.php");
    exit;
  }

  unset($_SESSION["old_auth"]);
  flash_set("success", "Usuário criado! Ele já pode entrar no sistema.");
  header("Location: cadastrar.php");
  exit;
}

// Caso primeiro ADMIN: cria com código
$codigo = (string)random_int(100000, 999999);
$codigoHash = hash("sha256", $codigo);
$expira = (new DateTime())->modify("+{$CODIGO_MINUTOS} minutes")->format("Y-m-d H:i:s");

$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios
    (nome, email, cpf, senha, cargo, ativo, email_verificado, email_codigo_hash, email_codigo_expira)
  VALUES
    (?, ?, ?, ?, 'ADMIN', 1, 0, ?, ?)
");

mysqli_stmt_bind_param($stmt, "ssssss", $nome, $email, $cpfDigits, $hash, $codigoHash, $expira);

if (!mysqli_stmt_execute($stmt)) {
  flash_set("danger", "Erro ao cadastrar administrador.");
  header("Location: cadastrar.php");
  exit;
}

unset($_SESSION["old_auth"]);

// envia o código via SMTP
$ok = mailer_send_code($email, $nome, $codigo, $CODIGO_MINUTOS);

if ($ok) {
  flash_set("success", "Cadastro criado! Enviamos um código de confirmação no seu email.");
} else {
  flash_set("warning", "Cadastro criado, mas não consegui enviar o email (SMTP). Código para teste: {$codigo}");
}

header("Location: confirmar_email.php?email=" . urlencode($email));
exit;
