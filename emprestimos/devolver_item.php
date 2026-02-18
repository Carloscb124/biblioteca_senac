<?php
$titulo_pagina = "Devolver item";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$id_item = (int)($_GET['item_id'] ?? 0);
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if ($id_item <= 0) {
  if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => false, 'msg' => 'Item inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  flash_set('danger', 'Item inválido.');
  header("Location: listar.php");
  exit;
}

mysqli_begin_transaction($conn);

try {
  // trava o item
  $stmt = mysqli_prepare($conn, "
    SELECT ei.id, ei.emprestimo_id, ei.id_livro, ei.devolvido
    FROM emprestimo_itens ei
    WHERE ei.id = ?
    LIMIT 1 FOR UPDATE
  ");
  mysqli_stmt_bind_param($stmt, "i", $id_item);
  mysqli_stmt_execute($stmt);
  $item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  if (!$item) throw new Exception("Item não encontrado.");

  if ((int)$item['devolvido'] === 1) {
    mysqli_commit($conn);

    if ($isAjax) {
      header("Content-Type: application/json; charset=UTF-8");
      echo json_encode(['ok' => true, 'msg' => 'Item já estava devolvido.'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    flash_set('info', 'Item já estava devolvido.');
    header("Location: listar.php");
    exit;
  }

  // marca item como devolvido
  $stmtU = mysqli_prepare($conn, "
    UPDATE emprestimo_itens
    SET devolvido = 1, data_devolucao = CURDATE()
    WHERE id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmtU, "i", $id_item);
  mysqli_stmt_execute($stmtU);

  $livroId = (int)$item['id_livro'];

  // devolve pro estoque, mas sem ultrapassar qtd_total
  $stmtL = mysqli_prepare($conn, "
    UPDATE livros
    SET qtd_disp = LEAST(qtd_disp + 1, qtd_total)
    WHERE id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmtL, "i", $livroId);
  mysqli_stmt_execute($stmtL);

  mysqli_commit($conn);

  if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => true, 'msg' => 'Livro devolvido!'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  flash_set('success', 'Livro devolvido!');
  header("Location: listar.php");
  exit;

} catch (Exception $ex) {
  mysqli_rollback($conn);

  if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => false, 'msg' => $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }

  flash_set('danger', 'Erro ao devolver: ' . $ex->getMessage());
  header("Location: listar.php");
  exit;
}
