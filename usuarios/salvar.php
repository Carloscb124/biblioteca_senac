<?php
include("../conexao.php");
include("../includes/flash.php");

$nome   = trim($_POST['nome'] ?? '');
$email  = trim($_POST['email'] ?? '');
$senha  = $_POST['senha'] ?? '';
$perfil = $_POST['perfil'] ?? 'leitor';

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

if ($perfil !== 'admin' && $perfil !== 'leitor') {
  $perfil = 'leitor';
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "ssss", $nome, $email, $hash, $perfil);

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Usuário cadastrado com sucesso!');
  header("Location: listar.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'Esse email já está em uso.');
} else {
  flash_set('danger', 'Erro ao cadastrar usuário.');
}

header("Location: cadastrar.php");
exit;
