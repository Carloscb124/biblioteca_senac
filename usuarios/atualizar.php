<?php
include("../conexao.php");
include("../includes/flash.php");

$id     = (int)($_POST['id'] ?? 0);
$nome   = trim($_POST['nome'] ?? '');
$email  = trim($_POST['email'] ?? '');
$perfil = $_POST['perfil'] ?? 'leitor';
$senha  = $_POST['senha'] ?? '';

if ($id <= 0) {
  flash_set('danger', 'Usuário inválido.');
  header("Location: listar.php");
  exit;
}

if ($nome === '' || $email === '') {
  flash_set('danger', 'Preencha nome e email.');
  header("Location: editar.php?id=$id");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('danger', 'Email inválido.');
  header("Location: editar.php?id=$id");
  exit;
}

if ($perfil !== 'admin' && $perfil !== 'leitor') {
  $perfil = 'leitor';
}

if (trim($senha) !== '') {
  $hash = password_hash($senha, PASSWORD_DEFAULT);
  $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nome=?, email=?, perfil=?, senha=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "ssssi", $nome, $email, $perfil, $hash, $id);
} else {
  $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nome=?, email=?, perfil=? WHERE id=?");
  mysqli_stmt_bind_param($stmt, "sssi", $nome, $email, $perfil, $id);
}

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Usuário atualizado com sucesso!');
  header("Location: listar.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'Esse email já está em uso.');
} else {
  flash_set('danger', 'Erro ao atualizar usuário.');
}

header("Location: editar.php?id=$id");
exit;
?>