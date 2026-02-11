<?php
include("../conexao.php");
header("Content-Type: application/json; charset=UTF-8");

/*
  buscar_cdd_isbn.php
  - Recebe: ?isbn=...
  - Usa buscar_isbn.php localmente
  - Compara assuntos + título + sinopse com cdd_keywords
  - Retorna o melhor CDD
*/

function onlyDigits($s) {
  return preg_replace('/\D+/', '', (string)$s);
}

$isbn = onlyDigits($_GET["isbn"] ?? "");

if ($isbn === "" || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  echo json_encode(["ok" => false, "error" => "ISBN inválido"]);
  exit;
}

/*
  Chama buscar_isbn.php diretamente (sem HTTP)
*/
ob_start();
$_GET["isbn"] = $isbn;
include(__DIR__ . "/buscar_isbn.php");
$json = ob_get_clean();

$book = json_decode($json, true);

if (!$book || empty($book["ok"])) {
  echo json_encode(["ok" => false, "error" => "Falha ao buscar dados do ISBN"]);
  exit;
}

/*
  Junta texto para comparação
*/
$blob = trim(
  ($book["titulo"] ?? "") . " " .
  ($book["assuntos"] ?? "") . " " .
  ($book["sinopse"] ?? "")
);

if ($blob === "") {
  echo json_encode(["ok" => false, "error" => "Sem texto para classificar"]);
  exit;
}

$blob = mb_strtolower($blob, "UTF-8");

/*
  Busca regras
*/
$res = mysqli_query($conn, "SELECT keyword, cdd_id, peso FROM cdd_keywords");

if (!$res) {
  echo json_encode(["ok" => false, "error" => "Tabela cdd_keywords não encontrada"]);
  exit;
}

$scores = [];

while ($row = mysqli_fetch_assoc($res)) {
  $kw = mb_strtolower((string)$row["keyword"], "UTF-8");
  $cddId = (int)$row["cdd_id"];
  $peso = (int)$row["peso"];

  if ($kw === "") continue;

  if (mb_strpos($blob, $kw) !== false) {
    $scores[$cddId] = ($scores[$cddId] ?? 0) + $peso;
  }
}

if (empty($scores)) {
  echo json_encode(["ok" => false, "error" => "Sem correspondência de CDD"]);
  exit;
}

/*
  Escolhe o melhor CDD
*/
arsort($scores);
$bestCddId = (int)array_key_first($scores);

/*
  Busca o texto do CDD
*/
$stmt = mysqli_prepare($conn, "SELECT id, codigo, descricao FROM cdd WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $bestCddId);
mysqli_stmt_execute($stmt);
$r2 = mysqli_stmt_get_result($stmt);
$cdd = mysqli_fetch_assoc($r2);

if (!$cdd) {
  echo json_encode(["ok" => false, "error" => "CDD não encontrado"]);
  exit;
}

$cddText = trim($cdd["codigo"] . " - " . $cdd["descricao"]);

echo json_encode([
  "ok" => true,
  "cdd_id" => (int)$cdd["id"],
  "cdd_text" => $cddText,
  "score" => (int)$scores[$bestCddId]
]);
