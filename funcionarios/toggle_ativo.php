<?php
require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");

$id = (int)($_POST['id'] ?? 0);
$novo = (int)($_POST['novo'] ?? -1);
$meuId = (int)($_SESSION['auth']['id'] ?? 0);

if ($id <= 0 || ($novo !== 0 && $novo !== 1)) {
  header("Location: listar.php");
  exit;
}

if ($id === $meuId) {
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "UPDATE funcionarios SET ativo = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, "ii", $novo, $id);
mysqli_stmt_execute($stmt);

header("Location: listar.php");
exit;
