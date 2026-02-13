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
    SELECT e.data_prevista,
           SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) AS abertos
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    WHERE e.id = ?
    GROUP BY e.id
    FOR UPDATE
  ");
  mysqli_stmt_bind_param($stmt, "i", $id);
  mysqli_stmt_execute($stmt);
  $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

  if (!$r) throw new Exception("Empréstimo não encontrado.");
  if ((int)$r['abertos'] <= 0) throw new Exception("Não dá pra renovar: empréstimo já devolvido.");

  $hoje = new DateTime(date('Y-m-d'));
  $prev = !empty($r['data_prevista']) ? new DateTime($r['data_prevista']) : null;

  $base = ($prev && $prev > $hoje) ? $prev : $hoje;
  $base->modify('+7 days');
  $nova = $base->format('Y-m-d');

  $stmt = mysqli_prepare($conn, "UPDATE emprestimos SET data_prevista = ? WHERE id = ?");
  mysqli_stmt_bind_param($stmt, "si", $nova, $id);
  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);
  flash_set('success', "Renovado! Nova data prevista: {$nova}");
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: listar.php");
  exit;
}
