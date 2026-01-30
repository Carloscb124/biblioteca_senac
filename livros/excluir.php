<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'Livro inválido.');
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM livros WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
  if (mysqli_stmt_affected_rows($stmt) > 0) {
    flash_set('success', 'Livro excluído com sucesso.');
  } else {
    flash_set('warning', 'Livro não encontrado.');
  }
  header("Location: listar.php");
  exit;
}

// Se existir FK com empréstimos, pode cair aqui
flash_set('danger', 'Não foi possível excluir. Esse livro pode estar ligado a empréstimos.');
header("Location: listar.php");
exit;
