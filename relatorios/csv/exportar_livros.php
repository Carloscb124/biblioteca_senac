<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=livros.csv');

$saida = fopen("php://output", "w");

// cabeçalho
fputcsv($saida, ["id", "titulo", "autor", "ano_publicacao", "isbn", "disponivel"], ";");

$sql = "SELECT id, titulo, autor, ano_publicacao, ISBN, disponivel FROM livros ORDER BY id DESC";
$r = mysqli_query($conn, $sql);

while ($l = mysqli_fetch_assoc($r)) {
  fputcsv($saida, [
    $l["id"],
    $l["titulo"],
    $l["autor"],
    $l["ano_publicacao"],
    $l["ISBN"],
    $l["disponivel"]
  ], ";");
}

fclose($saida);
exit;
