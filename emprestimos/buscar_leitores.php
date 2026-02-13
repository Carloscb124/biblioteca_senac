<?php
include("../auth/auth_guard.php");
include("../conexao.php");

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) exit;

$like = "%" . $q . "%";

$stmt = mysqli_prepare($conn, "
  SELECT id, nome, cpf, email, telefone
  FROM usuarios
  WHERE nome LIKE ? OR cpf LIKE ? OR email LIKE ? OR telefone LIKE ?
  ORDER BY nome ASC
  LIMIT 8
");
mysqli_stmt_bind_param($stmt, "ssss", $like, $like, $like, $like);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

while ($u = mysqli_fetch_assoc($res)) {
  $id = (int)$u['id'];
  $nome = $u['nome'] ?? 'Leitor';
  $cpf = $u['cpf'] ?? '';
  $email = $u['email'] ?? '';
  $tel = $u['telefone'] ?? '';

  $txt = trim($nome . " | " . ($cpf ?: $email ?: $tel));
  $txtSafe = htmlspecialchars($txt);
  echo "<button type='button' class='list-group-item list-group-item-action'
          data-id='{$id}' data-text='{$txtSafe}'>
          {$txtSafe}
        </button>";
}
