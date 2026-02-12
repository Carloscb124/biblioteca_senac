<?php
require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/flash.php");

$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($nome === '' || $email === '' || $senha === '') {
  flash_set("danger", "Preencha nome, email e senha.");
  header("Location: cadastrar.php");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set("danger", "Email inválido.");
  header("Location: cadastrar.php");
  exit;
}

if (strlen($senha) < 6) {
  flash_set("danger", "A senha deve ter pelo menos 6 caracteres.");
  header("Location: cadastrar.php");
  exit;
}

// Força cargo: ADMIN cria apenas BIBLIOTECARIO por aqui
$cargo = "BIBLIOTECARIO";
$hash = password_hash($senha, PASSWORD_DEFAULT);

// email único (se seu banco já tiver UNIQUE, isso cobre também)
$chk = mysqli_prepare($conn, "SELECT 1 FROM funcionarios WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($chk, "s", $email);
mysqli_stmt_execute($chk);
$r = mysqli_stmt_get_result($chk);
if ($r && mysqli_fetch_row($r)) {
  flash_set("warning", "Esse email já está cadastrado.");
  header("Location: cadastrar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "INSERT INTO funcionarios (nome, email, senha, cargo, ativo) VALUES (?, ?, ?, ?, 1)");
mysqli_stmt_bind_param($stmt, "ssss", $nome, $email, $hash, $cargo);

if (!mysqli_stmt_execute($stmt)) {
  flash_set("danger", "Erro ao cadastrar funcionário.");
  header("Location: cadastrar.php");
  exit;
}

flash_set("success", "Bibliotecário cadastrado com sucesso!");
header("Location: listar.php");
exit;
