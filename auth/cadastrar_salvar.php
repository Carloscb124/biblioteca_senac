<?php
include("../conexao.php");
include("../includes/flash.php");

if (session_status() === PHP_SESSION_NONE) session_start();

// Pega dados
$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$cargo = strtoupper(trim($_POST['cargo'] ?? 'BIBLIOTECARIO'));

// Guarda dados pra repopular
$_SESSION['old_auth'] = [
  'nome' => $nome,
  'email' => $email,
  'cargo' => $cargo
];

// Validações básicas
if ($nome === '' || $email === '' || $senha === '') {
  flash_set('danger', 'Preencha nome, email e senha.');
  header("Location: cadastrar.php");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('danger', 'Email inválido.');
  header("Location: cadastrar.php");
  exit;
}

if (strlen($senha) < 6) {
  flash_set('danger', 'A senha deve ter pelo menos 6 caracteres.');
  header("Location: cadastrar.php");
  exit;
}

// Verifica se já existe algum funcionário
$hasAny = false;
$res = mysqli_query($conn, "SELECT 1 FROM funcionarios LIMIT 1");
if ($res && mysqli_fetch_row($res)) $hasAny = true;

/*
  Regra:
  - Se NÃO existe ninguém: permite criar o primeiro ADMIN
  - Se JÁ existe: só ADMIN logado pode criar, e cria BIBLIOTECARIO (por enquanto)
*/
if ($hasAny) {
  include(__DIR__ . "/auth_guard.php");
  require_admin();

  // força bibliotecário: admin cria bibliotecário, e bibliotecário não cria admin
  $cargo = 'BIBLIOTECARIO';
} else {
  // bootstrap: primeiro usuário precisa ser admin
  $cargo = 'ADMIN';
}

// Hash
$hash = password_hash($senha, PASSWORD_DEFAULT);

// Insert
$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios (nome, email, senha, cargo, ativo)
  VALUES (?, ?, ?, ?, 1)
");
mysqli_stmt_bind_param($stmt, "ssss", $nome, $email, $hash, $cargo);

if (mysqli_stmt_execute($stmt)) {
  unset($_SESSION['old_auth']);
  flash_set('success', 'Cadastro realizado! Agora você pode entrar.');
  header("Location: login.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'Esse email já está cadastrado.');
} else {
  flash_set('danger', 'Erro ao cadastrar. Tente novamente.');
}

header("Location: cadastrar.php");
exit;
