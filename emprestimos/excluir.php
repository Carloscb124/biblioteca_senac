<?php
include("../conexao.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID inválido.");

mysqli_begin_transaction($conn);

try {
  $stmt = mysqli_prepare($conn, "SELECT id_livro, devolvido FROM emprestimos WHERE id = ? FOR UPDATE");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $e = mysqli_fetch_assoc($res);

  if (!$e) throw new Exception("Empréstimo não encontrado.");

  $id_livro = (int)$e['id_livro'];
  $devolvido = (int)$e['devolvido'];

  // Exclui empréstimo
  $stmt = mysqli_prepare($conn, "DELETE FROM emprestimos WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);

  // Se estava aberto, libera livro
  if ($devolvido === 0) {
    $stmt = mysqli_prepare($conn, "UPDATE livros SET disponivel = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
  }

  mysqli_commit($conn);
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  echo "Erro: " . htmlspecialchars($e->getMessage());
}
?>