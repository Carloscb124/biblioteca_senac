<?php
include("../conexao.php");
include("../includes/flash.php");

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($email === '' || $senha === '') {
  flash_set('danger', 'Preencha email e senha.');
  header("Location: login.php");
  exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, nome, email, senha, cargo FROM funcionarios WHERE email = ? AND ativo = 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$f = mysqli_fetch_assoc($res);

if (!$f || !password_verify($senha, $f['senha'])) {
  flash_set('danger', 'Email ou senha inválidos.');
  header("Location: login.php");
  exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['auth'] = [
  'id' => (int)$f['id'],
  'nome' => $f['nome'],
  'email' => $f['email'],
  'cargo' => $f['cargo'],
];

flash_set('success', 'Bem-vindo(a), ' . $f['nome'] . '!');
header("Location: ../index.php");
exit;
