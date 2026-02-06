<?php
include("../conexao.php");
include("../includes/flash.php");

$id_usuario = (int)($_POST['id_usuario'] ?? 0);
$id_livro   = (int)($_POST['id_livro'] ?? 0);
$data_emp   = $_POST['data_emprestimo'] ?? '';
$data_prev  = $_POST['data_prevista'] ?? null;

if ($id_usuario <= 0 || $id_livro <= 0 || $data_emp === '') {
  flash_set('danger', 'Preencha os dados do empréstimo.');
  header("Location: cadastrar.php");
  exit;
}
if ($data_prev === '') $data_prev = null;

mysqli_begin_transaction($conn);

try {
  // trava e confere: livro precisa estar ATIVO no acervo e com exemplares disponíveis
  $stmt = mysqli_prepare($conn, "
    SELECT disponivel, qtd_disp
    FROM livros
    WHERE id = ?
    FOR UPDATE
  ");
  mysqli_stmt_bind_param($stmt, "i", $id_livro);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $livro = mysqli_fetch_assoc($res);

  if (!$livro) throw new Exception("Livro não encontrado.");

  if ((int)$livro['disponivel'] !== 1) {
    throw new Exception("Este livro está baixado/desativado no acervo.");
  }

  if ((int)$livro['qtd_disp'] <= 0) {
    throw new Exception("Sem exemplares disponíveis no momento.");
  }

  // cria empréstimo
  $stmt = mysqli_prepare($conn, "
    INSERT INTO emprestimos (id_usuario, id_livro, data_emprestimo, data_prevista, data_devolucao, devolvido)
    VALUES (?, ?, ?, ?, NULL, 0)
  ");
  mysqli_stmt_bind_param($stmt, "iiss", $id_usuario, $id_livro, $data_emp, $data_prev);
  mysqli_stmt_execute($stmt);

  // baixa 1 exemplar (NÃO mexe no 'disponivel' porque ele é status do acervo)
  $stmt = mysqli_prepare($conn, "
    UPDATE livros
    SET qtd_disp = GREATEST(qtd_disp - 1, 0)
    WHERE id = ?
  ");
  mysqli_stmt_bind_param($stmt, "i", $id_livro);
  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);
  flash_set('success', 'Empréstimo registrado com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: cadastrar.php");
  exit;
}
