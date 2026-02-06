<?php
include("../conexao.php");

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) exit;

$qLike = "%" . $q . "%";
$qDigits = preg_replace('/\D+/', '', $q);
$qCpfLike = ($qDigits !== '') ? "%" . $qDigits . "%" : "%";

$stmt = mysqli_prepare($conn, "
  SELECT id, nome, cpf, email, telefone
  FROM usuarios
  WHERE ativo = 1
    AND (
      nome LIKE ?
      OR cpf LIKE ?
      OR email LIKE ?
      OR telefone LIKE ?
    )
  ORDER BY nome ASC
  LIMIT 12
");

mysqli_stmt_bind_param($stmt, "ssss", $qLike, $qCpfLike, $qLike, $qLike);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

while ($u = mysqli_fetch_assoc($res)) {
  $id = (int)$u['id'];
  $nome = htmlspecialchars($u['nome']);
  $cpf = htmlspecialchars($u['cpf'] ?? '');
  $email = trim($u['email'] ?? '');
  $tel = trim($u['telefone'] ?? '');

  $cont = [];
  if ($email !== '') $cont[] = $email;
  if ($tel !== '') $cont[] = $tel;
  $contTxt = $cont ? implode(" | ", array_map('htmlspecialchars', $cont)) : "Sem contato";

  $texto = "{$nome} (CPF: {$cpf}) • {$contTxt}";
  $textoEsc = htmlspecialchars($texto);
  echo "<button type='button' class='list-group-item list-group-item-action'
            data-id='{$id}' data-text='{$textoEsc}'>{$textoEsc}</button>";
}
