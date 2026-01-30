<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('danger', 'Empréstimo inválido.');
  header("Location: listar.php");
  exit;
}

mysqli_begin_transaction($conn);

try {
  $stmt = mysqli_prepare($conn, "SELECT id_livro, devolvido FROM emprestimos WHERE id = ? FOR UPDATE");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $e = mysqli_fetch_assoc($res);

  if (!$e) throw new Exception("Empréstimo não encontrado.");
  if ((int)$e['devolvido'] === 1) throw new Exception("Esse empréstimo já foi devolvido.");

  $id_livro = (int)$e['id_livro'];
  $hoje = date('Y-m-d');

  $stmt = mysqli_prepare($conn, "UPDATE emprestimos SET devolvido = 1, data_devolucao = ? WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "si", $hoje, $id);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($conn, "UPDATE livros SET disponivel = 1 WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "i", $id_livro);
  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);
  flash_set('success', 'Devolução registrada com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: listar.php");
  exit;
}
