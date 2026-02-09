<?php
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set("danger", "ID inválido.");
  header("Location: listar.php"); exit;
}

// "Baixar" = disponivel 0
$stmt = mysqli_prepare($conn, "UPDATE livros SET disponivel = 0 WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
  flash_set("success", "Livro baixado do acervo.");
  header("Location: listar.php"); exit;
}

flash_set("danger", "Erro ao baixar livro.");
header("Location: listar.php"); exit;
