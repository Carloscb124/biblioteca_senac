<?php
include("../conexao.php");

$id = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$perfil = $_POST['perfil'] ?? 'leitor';
$senha = $_POST['senha'] ?? '';

if ($id <= 0 || $nome === '' || $email === '') {
  die("Dados inválidos.");
}

if ($senha !== '') {
  // ideal: hash na senha
  $hash = password_hash($senha, PASSWORD_DEFAULT);

  $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nome=?, email=?, perfil=?, senha=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ssssi", $nome, $email, $perfil, $hash, $id);
  mysqli_stmt_execute($stmt);
} else {
  $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nome=?, email=?, perfil=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "sssi", $nome, $email, $perfil, $id);
  mysqli_stmt_execute($stmt);
}

header("Location: listar.php");
exit;
?>