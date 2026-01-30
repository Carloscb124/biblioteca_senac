<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST['id'] ?? 0);
$id_usuario = (int)($_POST['id_usuario'] ?? 0);
$id_livro = (int)($_POST['id_livro'] ?? 0);
$id_livro_atual = (int)($_POST['id_livro_atual'] ?? 0);
$data_emp = $_POST['data_emprestimo'] ?? '';
$data_prev = $_POST['data_prevista'] ?? null;

if ($id <= 0 || $id_usuario <= 0 || $id_livro <= 0 || $data_emp === '') {
  flash_set('danger', 'Dados inválidos.');
  header("Location: editar.php?id=$id");
  exit;
}
if ($data_prev === '') $data_prev = null;

mysqli_begin_transaction($conn);

try {
  // trava o empréstimo (garante que ainda está aberto)
  $stmt = mysqli_prepare($conn, "SELECT devolvido FROM emprestimos WHERE id = ? FOR UPDATE");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $emp = mysqli_fetch_assoc($res);

  if (!$emp) throw new Exception("Empréstimo não encontrado.");
  if ((int)$emp['devolvido'] === 1) throw new Exception("Empréstimo devolvido não pode ser editado.");

  // se mudou livro: liberar antigo e travar novo
  if ($id_livro !== $id_livro_atual) {
    // trava e verifica novo livro disponível
    $stmt = mysqli_prepare($conn, "SELECT disponivel FROM livros WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $novo = mysqli_fetch_assoc($res);

    if (!$novo) throw new Exception("Novo livro não encontrado.");
    if ((int)$novo['disponivel'] !== 1) throw new Exception("Novo livro está indisponível.");

    // libera o antigo
    $stmt = mysqli_prepare($conn, "UPDATE livros SET disponivel = 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_livro_atual);
    mysqli_stmt_execute($stmt);

    // trava o novo
    $stmt = mysqli_prepare($conn, "UPDATE livros SET disponivel = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
  }

  // atualiza empréstimo
  $stmt = mysqli_prepare($conn, "
    UPDATE emprestimos
    SET id_usuario = ?, id_livro = ?, data_emprestimo = ?, data_prevista = ?
    WHERE id = ?
  ");
  mysqli_stmt_bind_param($stmt, "iissi", $id_usuario, $id_livro, $data_emp, $data_prev, $id);
  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);
  flash_set('success', 'Empréstimo atualizado com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: editar.php?id=$id");
  exit;
}
