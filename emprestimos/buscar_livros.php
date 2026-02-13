<?php
include("../auth/auth_guard.php");
include("../conexao.php");

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) exit;

$like = "%" . $q . "%";

$stmt = mysqli_prepare($conn, "
  SELECT id, titulo, autor, ISBN, qtd_disp
  FROM livros
  WHERE qtd_disp > 0
    AND (disponivel = 1)
    AND (titulo LIKE ? OR autor LIKE ? OR ISBN LIKE ?)
  ORDER BY titulo ASC
  LIMIT 8
");
mysqli_stmt_bind_param($stmt, "sss", $like, $like, $like);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

while ($l = mysqli_fetch_assoc($res)) {
  $id = (int)$l['id'];
  $t  = $l['titulo'] ?? 'Livro';
  $a  = $l['autor'] ?? '';
  $isbn = $l['ISBN'] ?? '';
  $disp = (int)($l['qtd_disp'] ?? 0);

  $txt = "{$t} | {$a} | ISBN: {$isbn} | Disp: {$disp}";
  $safe = htmlspecialchars($txt);

  echo "<button type='button' class='list-group-item list-group-item-action'
          data-id='{$id}' data-text='{$safe}'>
          {$safe}
        </button>";
}
