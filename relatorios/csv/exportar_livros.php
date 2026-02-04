<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=livros.csv');

$saida = fopen("php://output", "w");

// cabeçalho
fputcsv($saida, [
  "id",
  "titulo",
  "autor",
  "ano_publicacao",
  "isbn",
  "qtd_total",
  "qtd_disp",
  "disponivel",
  "categoria_codigo",
  "categoria_descricao"
], ";");

$sql = "
  SELECT
    l.id,
    l.titulo,
    l.autor,
    l.ano_publicacao,
    l.ISBN,
    l.qtd_total,
    l.qtd_disp,
    l.disponivel,
    c.codigo AS categoria_codigo,
    c.descricao AS categoria_descricao
  FROM livros l
  LEFT JOIN cdd c ON c.id = l.categoria
  ORDER BY l.id DESC
";

$r = mysqli_query($conn, $sql);

while ($l = mysqli_fetch_assoc($r)) {
  fputcsv($saida, [
    $l["id"],
    $l["titulo"],
    $l["autor"],
    $l["ano_publicacao"],
    $l["ISBN"],
    $l["qtd_total"],
    $l["qtd_disp"],
    $l["disponivel"],
    $l["categoria_codigo"],
    $l["categoria_descricao"]
  ], ";");
}

fclose($saida);
exit;
