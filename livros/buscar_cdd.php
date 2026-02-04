<?php
include("../conexao.php");

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
  exit;
}

$like = "%{$q}%";

$stmt = mysqli_prepare($conn, "
  SELECT id, codigo, descricao
  FROM cdd
  WHERE codigo LIKE ? OR descricao LIKE ?
  ORDER BY codigo ASC
  LIMIT 15
");
mysqli_stmt_bind_param($stmt, "ss", $like, $like);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

function esc($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

while ($row = mysqli_fetch_assoc($res)) {
  $id = (int)$row['id'];
  $text = $row['codigo'] . " - " . $row['descricao'];

  echo "
    <button type='button'
      class='list-group-item list-group-item-action'
      data-id='{$id}'
      data-text='".esc($text)."'>
      ".esc($text)."
    </button>
  ";
}
