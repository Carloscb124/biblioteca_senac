<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'Leitor inválido.');
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "UPDATE usuarios SET ativo = 1 WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
  flash_set('success', 'Leitor reativado com sucesso!');
  header("Location: listar.php");
  exit;
}

flash_set('danger', 'Erro ao reativar leitor.');
header("Location: listar.php");
exit;
