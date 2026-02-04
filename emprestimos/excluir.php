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

  $id_livro = (int)$e['id_livro'];
  $devolvido = (int)$e['devolvido'];

  $stmt = mysqli_prepare($conn, "DELETE FROM emprestimos WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);

  // se estava aberto, devolve 1 exemplar
  if ($devolvido === 0) {
    $stmt = mysqli_prepare($conn, "
      UPDATE livros
      SET qtd_disp = LEAST(qtd_disp + 1, qtd_total),
          disponivel = IF(LEAST(qtd_disp + 1, qtd_total) > 0, 1, 0)
      WHERE id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
  }

  mysqli_commit($conn);
  flash_set('success', 'Empréstimo excluído com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: listar.php");
  exit;
}
