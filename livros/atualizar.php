<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST['id'] ?? 0);

$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbn   = trim($_POST['ISBN'] ?? '');
$disp   = (int)($_POST['disponivel'] ?? 1);

$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) {
  $ano = (int)$anoRaw;
}

if ($id <= 0) {
  flash_set('danger', 'Livro inválido.');
  header("Location: listar.php");
  exit;
}

if ($titulo === '') {
  flash_set('danger', 'O título é obrigatório.');
  header("Location: editar.php?id=$id");
  exit;
}

$disp = ($disp === 1) ? 1 : 0;

$stmt = mysqli_prepare($conn, "
  UPDATE livros
  SET titulo = ?, autor = ?, ano_publicacao = ?, ISBN = ?, disponivel = ?
  WHERE id = ?
");
mysqli_stmt_bind_param($stmt, "ssisii", $titulo, $autor, $ano, $isbn, $disp, $id);

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Livro atualizado com sucesso!');
  header("Location: listar.php");
  exit;
}

$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'Já existe um livro com esse ISBN.');
} else {
  flash_set('danger', 'Erro ao atualizar livro.');
}

header("Location: editar.php?id=$id");
exit;
