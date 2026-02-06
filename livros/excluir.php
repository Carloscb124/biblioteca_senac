<?php
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'Livro inválido.');
  header("Location: listar.php");
  exit;
}

mysqli_begin_transaction($conn);

try {
  // trava o livro
  $st = mysqli_prepare($conn, "SELECT qtd_total, qtd_disp, titulo FROM livros WHERE id = ? FOR UPDATE");
  mysqli_stmt_bind_param($st, "i", $id);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  $l = mysqli_fetch_assoc($rs);

  if (!$l) {
    mysqli_rollback($conn);
    flash_set('danger', 'Livro não encontrado.');
    header("Location: listar.php");
    exit;
  }

  $qtd_total = (int)$l['qtd_total'];
  $qtd_disp  = (int)$l['qtd_disp'];
  $emprestados = max(0, $qtd_total - $qtd_disp);

  if ($emprestados > 0) {
    mysqli_rollback($conn);
    flash_set('warning', "Não dá pra baixar este livro. Existem {$emprestados} exemplar(es) emprestado(s).");
    header("Location: listar.php");
    exit;
  }

  // baixa (desativa) o livro: zera disponíveis e marca indisponível
  $up = mysqli_prepare($conn, "UPDATE livros SET disponivel = 0, qtd_disp = 0 WHERE id = ?");
  mysqli_stmt_bind_param($up, "i", $id);
  mysqli_stmt_execute($up);

  mysqli_commit($conn);
  flash_set('success', 'Livro baixado do acervo com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  flash_set('danger', 'Erro ao baixar livro: ' . $e->getMessage());
  header("Location: listar.php");
  exit;
}
