<?php
include("../conexao.php");

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) exit;

$qLike = "%" . $q . "%";

$stmt = mysqli_prepare($conn, "
  SELECT id, titulo, autor, ISBN, qtd_disp, qtd_total
  FROM livros
  WHERE qtd_disp > 0
    AND (
      titulo LIKE ?
      OR autor LIKE ?
      OR ISBN LIKE ?
    )
  ORDER BY titulo ASC
  LIMIT 12
");

mysqli_stmt_bind_param($stmt, "sss", $qLike, $qLike, $qLike);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

while ($l = mysqli_fetch_assoc($res)) {
  $id = (int)$l['id'];
  $titulo = htmlspecialchars($l['titulo']);
  $autor  = trim($l['autor'] ?? '');
  $isbn   = trim($l['ISBN'] ?? '');

  $disp = (int)$l['qtd_disp'];
  $tot  = (int)$l['qtd_total'];

  $extra = [];
  if ($autor !== '') $extra[] = "Autor: " . $autor;
  if ($isbn !== '')  $extra[] = "ISBN: " . $isbn;
  $extraTxt = $extra ? " • " . htmlspecialchars(implode(" | ", $extra)) : "";

  $texto = "{$titulo}{$extraTxt} ({$disp}/{$tot} disponíveis)";
  $textoEsc = htmlspecialchars($texto);

  echo "<button type='button' class='list-group-item list-group-item-action'
            data-id='{$id}' data-text='{$textoEsc}'>{$textoEsc}</button>";
}
