<?php
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$item_id = (int)($_GET['item_id'] ?? 0);
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($item_id <= 0) {
  if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => false, 'msg' => 'Item inválido.']);
    exit;
  }
  flash_set('danger', 'Item inválido.');
  header("Location: listar.php");
  exit;
}

mysqli_begin_transaction($conn);

try {
  // trava item
  $stmt = mysqli_prepare($conn, "
    SELECT id, id_livro, devolvido, IFNULL(perdido,0) AS perdido
    FROM emprestimo_itens
    WHERE id = ?
    LIMIT 1 FOR UPDATE
  ");
  mysqli_stmt_bind_param($stmt, "i", $item_id);
  mysqli_stmt_execute($stmt);
  $it = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  if (!$it) throw new Exception("Item não encontrado.");
  if ((int)$it['perdido'] === 1) throw new Exception("Item já estava perdido.");
  if ((int)$it['devolvido'] === 1) throw new Exception("Item já foi devolvido.");

  $livro_id = (int)$it['id_livro'];

  // trava livro e pega os números
  $stmtL = mysqli_prepare($conn, "
    SELECT id, qtd_total, qtd_disp
    FROM livros
    WHERE id = ?
    LIMIT 1 FOR UPDATE
  ");
  mysqli_stmt_bind_param($stmtL, "i", $livro_id);
  mysqli_stmt_execute($stmtL);
  $livro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtL));

  if (!$livro) throw new Exception("Livro não encontrado.");

  // marca item como perdido e encerrado
  $stmtU = mysqli_prepare($conn, "
    UPDATE emprestimo_itens
    SET perdido = 1,
        devolvido = 1,
        data_devolucao = CURDATE(),
        data_perdido = CURDATE()
    WHERE id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmtU, "i", $item_id);
  mysqli_stmt_execute($stmtU);

  // recalcula no PHP pra não depender de pegadinhas do MySQL em SET
  $qtd_total_atual = (int)$livro['qtd_total'];
  $qtd_disp_atual  = (int)$livro['qtd_disp'];

  $novo_total = max($qtd_total_atual - 1, 0);

  // disponível nunca pode ser maior que total
  $novo_disp = min($qtd_disp_atual, $novo_total);

  $stmtUp = mysqli_prepare($conn, "
    UPDATE livros
    SET qtd_total = ?, qtd_disp = ?
    WHERE id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmtUp, "iii", $novo_total, $novo_disp, $livro_id);
  mysqli_stmt_execute($stmtUp);

  mysqli_commit($conn);

  if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => true, 'msg' => 'Livro marcado como perdido.']);
    exit;
  }

  flash_set('warning', 'Livro marcado como perdido.');
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);

  if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    exit;
  }

  flash_set('danger', 'Erro: ' . $e->getMessage());
  header("Location: listar.php");
  exit;
}
