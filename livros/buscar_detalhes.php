<?php
include("../conexao.php");
header("Content-Type: application/json; charset=UTF-8");

/*
  buscar_detalhes.php
  - Recebe: ?id=...
  - Retorna JSON do livro para modal/detalhes
*/

$id = (int)($_GET["id"] ?? 0);
if ($id < 1) {
  echo json_encode(["ok" => false, "error" => "ID inválido"]);
  exit;
}

$stmt = mysqli_prepare($conn, "
  SELECT
    id, titulo, autor, editora, ano_publicacao, ISBN, categoria,
    sinopse, assuntos, capa_url, qtd_total, qtd_disp, disponivel
  FROM livros
  WHERE id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$l = mysqli_fetch_assoc($res);

if (!$l) {
  echo json_encode(["ok" => false, "error" => "Livro não encontrado"]);
  exit;
}

$isbnDigits = preg_replace('/\D+/', '', (string)($l["ISBN"] ?? ""));

// capa fallback
$capa = trim((string)($l["capa_url"] ?? ""));
if ($capa === "" && $isbnDigits !== "") {
  $capa = "https://covers.openlibrary.org/b/isbn/{$isbnDigits}-L.jpg?default=false";
}

echo json_encode([
  "ok" => true,
  "book" => [
    "id" => (int)$l["id"],
    "titulo" => (string)$l["titulo"],
    "autor" => (string)($l["autor"] ?? ""),
    "editora" => (string)($l["editora"] ?? ""),
    "ano_publicacao" => $l["ano_publicacao"],
    "isbn" => (string)($l["ISBN"] ?? ""),
    "categoria" => $l["categoria"],
    "sinopse" => (string)($l["sinopse"] ?? ""),
    "assuntos" => (string)($l["assuntos"] ?? ""),
    "capa_url" => $capa,
    "qtd_total" => (int)($l["qtd_total"] ?? 0),
    "qtd_disp" => (int)($l["qtd_disp"] ?? 0),
    "disponivel" => ((int)($l["disponivel"] ?? 0) === 1),
  ]
]);
