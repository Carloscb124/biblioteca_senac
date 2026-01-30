<?php
include("../conexao.php");
include("../includes/flash.php");

$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbn   = trim($_POST['ISBN'] ?? '');

$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) {
  $ano = (int)$anoRaw;
}

if ($titulo === '') {
  flash_set('danger', 'O título é obrigatório.');
  header("Location: cadastrar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "
  INSERT INTO livros (titulo, autor, ano_publicacao, ISBN, disponivel)
  VALUES (?, ?, ?, ?, 1)
");
mysqli_stmt_bind_param($stmt, "ssis", $titulo, $autor, $ano, $isbn);

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Livro cadastrado com sucesso!');
  header("Location: listar.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'Já existe um livro com esse ISBN.');
} else {
  flash_set('danger', 'Erro ao cadastrar livro.');
}

header("Location: cadastrar.php");
exit;
