<?php
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set("danger", "ID inválido.");
  header("Location: listar.php");
  exit;
}

/*
  Reativar = disponivel 1
  E recalcula qtd_disp = qtd_total - (qtd emprestada em aberto)

  IMPORTANTE:
  No seu modelo atual, o livro emprestado está em emprestimo_itens (ei.id_livro),
  e o status de devolução é ei.devolvido.
*/
$sql = "
  UPDATE livros l
  SET
    l.disponivel = 1,
    l.qtd_disp = GREATEST(0, l.qtd_total - (
      SELECT COUNT(*)
      FROM emprestimo_itens ei
      WHERE ei.id_livro = l.id
        AND ei.devolvido = 0
    ))
  WHERE l.id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
  flash_set("success", "Livro reativado!");
  header("Location: listar.php");
  exit;
}

flash_set("danger", "Erro ao reativar livro.");
header("Location: listar.php");
exit;
