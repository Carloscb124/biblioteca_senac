<?php
include("../auth/auth_guard.php");
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
  $stmt = mysqli_prepare($conn, "
    SELECT id, id_livro, devolvido
    FROM emprestimo_itens
    WHERE emprestimo_id = ?
    FOR UPDATE
  ");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);

  $itens = [];
  while ($r = mysqli_fetch_assoc($res)) $itens[] = $r;
  if (!$itens) throw new Exception("Empréstimo não encontrado ou sem itens.");

  $hoje = date('Y-m-d');
  $devolvidosAgora = 0;

  foreach ($itens as $item) {
    if ((int)$item['devolvido'] === 1) continue;

    $item_id = (int)$item['id'];
    $id_livro = (int)$item['id_livro'];

    $stmt = mysqli_prepare($conn, "
      UPDATE emprestimo_itens
      SET devolvido = 1, data_devolucao = ?
      WHERE id = ?
    ");
    mysqli_stmt_bind_param($stmt, "si", $hoje, $item_id);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($conn, "
      UPDATE livros
      SET qtd_disp = LEAST(qtd_total, qtd_disp + 1),
          disponivel = 1
      WHERE id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);

    $devolvidosAgora++;
  }

  mysqli_commit($conn);

  if ($devolvidosAgora === 0) flash_set('warning', 'Este empréstimo já estava todo devolvido.');
  else flash_set('success', 'Devolução registrada com sucesso!');

  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: listar.php");
  exit;
}
