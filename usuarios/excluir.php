<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'Usuário inválido.');
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
  if (mysqli_stmt_affected_rows($stmt) > 0) {
    flash_set('success', 'Usuário excluído com sucesso.');
  } else {
    flash_set('warning', 'Usuário não encontrado.');
  }
  header("Location: listar.php");
  exit;
} else {
  flash_set('danger', 'Erro ao excluir usuário.');
  header("Location: listar.php");
  exit;
}
?>