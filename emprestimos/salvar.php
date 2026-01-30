<?php
include("../conexao.php");

$id_usuario = (int)($_POST['id_usuario'] ?? 0);
$id_livro = (int)($_POST['id_livro'] ?? 0);
$data_emprestimo = $_POST['data_emprestimo'] ?? '';

if ($id_usuario <= 0 || $id_livro <= 0 || $data_emprestimo === '') {
  die("Dados inválidos.");
}

mysqli_begin_transaction($conn);

try {
  // Confere se o livro ainda está disponível
  $stmt = mysqli_prepare($conn, "SELECT disponivel FROM livros WHERE id = ? FOR UPDATE");
  mysqli_stmt_bind_param($stmt, "i", $id_livro);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $livro = mysqli_fetch_assoc($res);

  if (!$livro) {
    throw new Exception("Livro não encontrado.");
  }

  if ((int)$livro['disponivel'] !== 1) {
    throw new Exception("Livro indisponível no momento.");
  }

  // Insere empréstimo (devolvido = 0, data_devolucao = NULL)
  $stmt = mysqli_prepare($conn, "
    INSERT INTO emprestimos (id_usuario, id_livro, data_emprestimo, data_devolucao, devolvido)
    VALUES (?, ?, ?, NULL, 0)
  ");
  mysqli_stmt_bind_param($stmt, "iis", $id_usuario, $id_livro, $data_emprestimo);
  mysqli_stmt_execute($stmt);

  // Marca livro como indisponível
  $stmt = mysqli_prepare($conn, "UPDATE livros SET disponivel = 0 WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "i", $id_livro);
  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  echo "Erro: " . htmlspecialchars($e->getMessage());
}
?>