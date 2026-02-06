<?php
include("../auth/auth_guard.php");
include("../conexao.php");

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
  header("Location: listar.php");
  exit;
}

// Reativar: ativa o livro e restaura disponíveis para o total
$sql = "UPDATE livros
        SET disponivel = 1,
            qtd_disp = qtd_total
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

header("Location: listar.php");
exit;
