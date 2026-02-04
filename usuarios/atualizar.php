<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$cpf_raw = trim($_POST['cpf'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$ativo = (int)($_POST['ativo'] ?? 1);

// trava perfil
$perfil = 'leitor';

$cpf = preg_replace('/\D+/', '', $cpf_raw);

if ($id <= 0) {
  flash_set('danger', 'Leitor inválido.');
  header("Location: listar.php");
  exit;
}

if ($nome === '') {
  flash_set('danger', 'O nome é obrigatório.');
  header("Location: editar.php?id=$id");
  exit;
}

if ($cpf === '' || strlen($cpf) !== 11) {
  flash_set('danger', 'CPF inválido. Informe 11 dígitos.');
  header("Location: editar.php?id=$id");
  exit;
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('danger', 'Email inválido.');
  header("Location: editar.php?id=$id");
  exit;
}

$ativo = ($ativo === 1) ? 1 : 0;
$emailDb = ($email !== '') ? $email : null;
$telDb   = ($telefone !== '') ? $telefone : null;

$stmt = mysqli_prepare($conn, "
  UPDATE usuarios
  SET nome=?, cpf=?, email=?, telefone=?, perfil=?, ativo=?
  WHERE id=?
");
mysqli_stmt_bind_param($stmt, "sssssii", $nome, $cpf, $emailDb, $telDb, $perfil, $ativo, $id);

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Leitor atualizado com sucesso!');
  header("Location: listar.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'CPF ou Email já está em uso.');
} else {
  flash_set('danger', 'Erro ao atualizar leitor.');
}

header("Location: editar.php?id=$id");
exit;
