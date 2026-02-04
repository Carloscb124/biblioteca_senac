<?php
include("../conexao.php");
include("../includes/flash.php");

$nome = trim($_POST['nome'] ?? '');
$cpf_raw = trim($_POST['cpf'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefone = trim($_POST['telefone'] ?? '');
$ativo = (int)($_POST['ativo'] ?? 1);

// Segurança: leitor sempre é leitor
$perfil = 'leitor';

// pega só dígitos para validar
$cpf_digits = preg_replace('/\D+/', '', $cpf_raw);

if ($nome === '') {
  flash_set('danger', 'O nome é obrigatório.');
  header("Location: cadastrar.php");
  exit;
}

if ($cpf_digits === '' || strlen($cpf_digits) !== 11) {
  flash_set('danger', 'CPF inválido. Informe 11 dígitos.');
  header("Location: cadastrar.php");
  exit;
}

// formata para salvar com máscara: 000.000.000-00
$cpf = substr($cpf_digits, 0, 3) . '.' .
       substr($cpf_digits, 3, 3) . '.' .
       substr($cpf_digits, 6, 3) . '-' .
       substr($cpf_digits, 9, 2);

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('danger', 'Email inválido.');
  header("Location: cadastrar.php");
  exit;
}

$ativo = ($ativo === 1) ? 1 : 0;

// grava email/telefone como NULL quando vazios
$emailDb = ($email !== '') ? $email : null;
$telDb   = ($telefone !== '') ? $telefone : null;

$stmt = mysqli_prepare($conn, "
  INSERT INTO usuarios (nome, cpf, email, telefone, perfil, ativo)
  VALUES (?, ?, ?, ?, ?, ?)
");
mysqli_stmt_bind_param(
  $stmt,
  "sssssi",
  $nome,
  $cpf,
  $emailDb,
  $telDb,
  $perfil,
  $ativo
);

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Leitor cadastrado com sucesso!');
  header("Location: listar.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'CPF ou Email já cadastrado.');
} else {
  flash_set('danger', 'Erro ao cadastrar leitor.');
}

header("Location: cadastrar.php");
exit;
