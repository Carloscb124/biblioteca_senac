<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST['id'] ?? 0);

$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbn   = trim($_POST['ISBN'] ?? '');
$qtd_total = (int)($_POST['qtd_total'] ?? 1);

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

if ($qtd_total < 1) {
  flash_set('danger', 'A quantidade de exemplares deve ser no mínimo 1.');
  header("Location: editar.php?id=$id");
  exit;
}

/*
  Atualiza qtd_total e ajusta qtd_disp caso o total diminua.
  disponivel vira automático: se qtd_disp > 0 -> 1, senão 0
*/
$stmt = mysqli_prepare($conn, "
  UPDATE livros
  SET titulo = ?,
      autor = ?,
      ano_publicacao = ?,
      ISBN = ?,
      qtd_total = ?,
      qtd_disp = LEAST(qtd_disp, ?),
      disponivel = IF(LEAST(qtd_disp, ?) > 0, 1, 0)
  WHERE id = ?
");

mysqli_stmt_bind_param(
  $stmt,
  "ssissiii",
  $titulo,
  $autor,
  $ano,
  $isbn,
  $qtd_total,
  $qtd_total,
  $qtd_total,
  $id
);

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
