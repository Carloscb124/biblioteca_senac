<?php
include("../conexao.php");
header("Content-Type: application/json; charset=UTF-8");

/*
  buscar_cdd_isbn.php
  - Recebe: ?isbn=...
  - Busca dados do livro chamando buscar_isbn.php (include + ob_start)
  - Normaliza texto (remove acentos) pra keywords baterem
  - Faz score usando cdd_keywords (keyword + peso + bônus no título)
  - Resolve o CDD na tabela cdd:
      1) tenta por id (cdd.id)
      2) se não achar, tenta por codigo (cdd.codigo)
  - Retorna:
      ok, cdd_id (id REAL da tabela cdd), cdd_text ("codigo - descricao"), score, top3
*/

/* ===== helper próprio com nome único (pra não bater com buscar_isbn.php) ===== */
function isbn_digits($s) {
  return preg_replace('/\D+/', '', (string)$s);
}

/* Normaliza: minúsculo + sem acento + só letras/números/espaço */
function norm_txt($s) {
  $s = mb_strtolower((string)$s, "UTF-8");

  $t = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $s);
  if ($t !== false) $s = $t;

  $s = preg_replace('/[^a-z0-9]+/i', ' ', $s);
  $s = preg_replace('/\s+/', ' ', trim($s));
  return $s;
}

$isbn = isbn_digits($_GET["isbn"] ?? "");

if ($isbn === "" || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  echo json_encode(["ok" => false, "error" => "ISBN inválido"]);
  exit;
}

/* =========================
   1) Busca dados do ISBN (captura o JSON do buscar_isbn.php)
   ========================= */
ob_start();
$_GET["isbn"] = $isbn;
include(__DIR__ . "/buscar_isbn.php");
$json = ob_get_clean();

$book = json_decode($json, true);

if (!$book || empty($book["ok"])) {
  echo json_encode([
    "ok" => false,
    "error" => "Falha ao buscar dados do ISBN",
    "debug" => ["raw" => $json]
  ]);
  exit;
}

$titulo   = (string)($book["titulo"] ?? "");
$assuntos = (string)($book["assuntos"] ?? "");
$sinopse  = (string)($book["sinopse"] ?? "");

/* Texto base pro classificador */
$blob = trim($titulo . " " . $assuntos . " " . $sinopse);

if ($blob === "") {
  echo json_encode(["ok" => false, "error" => "Sem texto para classificar"]);
  exit;
}

$blobNorm   = norm_txt($blob);
$tituloNorm = norm_txt($titulo);

/* =========================
   2) Carrega regras e calcula score
   ========================= */
$res = mysqli_query($conn, "SELECT keyword, cdd_id, peso FROM cdd_keywords");
if (!$res) {
  echo json_encode(["ok" => false, "error" => "Tabela cdd_keywords não encontrada"]);
  exit;
}

$scores = [];

while ($row = mysqli_fetch_assoc($res)) {
  $kw = norm_txt((string)$row["keyword"]);

  // IMPORTANTÍSSIMO:
  // Esse cdd_id pode ser:
  // - id real da tabela cdd (ex: 15)
  // - ou o código CDD (ex: 813, 658, 920)
  $cddKey = trim((string)$row["cdd_id"]);
  $peso = (int)$row["peso"];

  if ($kw === "" || $cddKey === "") continue;

  if (strpos($blobNorm, $kw) !== false) {
    $scores[$cddKey] = ($scores[$cddKey] ?? 0) + $peso;

    // bônus no título (título pesa mais)
    if ($tituloNorm !== "" && strpos($tituloNorm, $kw) !== false) {
      $scores[$cddKey] += (int)ceil($peso * 0.7);
    }
  }
}

if (empty($scores)) {
  echo json_encode([
    "ok" => false,
    "error" => "Sem correspondência de CDD",
    "debug" => [
      "titulo" => $titulo,
      "assuntos" => $assuntos
    ]
  ]);
  exit;
}

/* =========================
   3) Escolhe melhor CDD
   ========================= */
arsort($scores);
$bestKey = (string)array_key_first($scores);
$bestScore = (int)$scores[$bestKey];

/* =========================
   4) Resolve na tabela cdd
   ========================= */
$cdd = null;

/* 4.1) tenta por ID (se for número) */
if (ctype_digit($bestKey)) {
  $id = (int)$bestKey;
  $stmt = mysqli_prepare($conn, "SELECT id, codigo, descricao FROM cdd WHERE id = ? LIMIT 1");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    $cdd = mysqli_fetch_assoc($r) ?: null;
  }
}

/* 4.2) se não achou por id, tenta por codigo */
if (!$cdd) {
  $codigo = $bestKey; // pode ser "813" etc
  $stmt2 = mysqli_prepare($conn, "SELECT id, codigo, descricao FROM cdd WHERE codigo = ? LIMIT 1");
  if ($stmt2) {
    mysqli_stmt_bind_param($stmt2, "s", $codigo);
    mysqli_stmt_execute($stmt2);
    $r2 = mysqli_stmt_get_result($stmt2);
    $cdd = mysqli_fetch_assoc($r2) ?: null;
  }
}

if (!$cdd) {
  echo json_encode([
    "ok" => false,
    "error" => "CDD não encontrado na tabela cdd",
    "debug" => [
      "best_key" => $bestKey,
      "best_score" => $bestScore,
      "top3" => array_slice($scores, 0, 3, true)
    ]
  ]);
  exit;
}

$cddText = trim($cdd["codigo"] . " - " . $cdd["descricao"]);

echo json_encode([
  "ok" => true,
  "cdd_id" => (int)$cdd["id"],     // SEMPRE ID real da tabela cdd
  "cdd_text" => $cddText,
  "score" => $bestScore,
  "top3" => array_slice($scores, 0, 3, true)
]);
