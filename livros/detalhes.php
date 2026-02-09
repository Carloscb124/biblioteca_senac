<?php
// livros/detalhes.php
// Retorna JSON com infos do livro (DB) + sinopse (API) sem salvar nada no banco.

include("../auth/auth_guard.php");
include("../conexao.php");

header("Content-Type: application/json; charset=UTF-8");

function only_digits($v) { return preg_replace('/\D+/', '', (string)$v); }

function http_get_json($url, &$debug = null) {
  if (function_exists("curl_init")) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_TIMEOUT => 8,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_HTTPHEADER => ["User-Agent: BibliotecaSenac/1.0"]
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) { $debug = "cURL: " . curl_error($ch); curl_close($ch); return null; }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 400) { $debug = "HTTP: $code"; return null; }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
  }

  $ctx = stream_context_create([
    "http" => ["timeout" => 8, "ignore_errors" => true, "header" => "User-Agent: BibliotecaSenac/1.0\r\n"],
    "ssl"  => ["verify_peer" => false, "verify_peer_name" => false]
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) { $debug = "file_get_contents falhou"; return null; }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

function json_out($ok, $payload = []) {
  echo json_encode(array_merge(["ok" => (bool)$ok], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== Entrada =====
$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) json_out(false, ["message" => "ID inválido."]);

// ===== Busca no banco =====
// Ajuste os nomes das colunas se forem diferentes no seu projeto:
// titulo, autor, ano_publicacao, ISBN, capa_url, disponivel, qtd_total, qtd_disponivel, categoria
$sql = "
  SELECT
    l.id,
    l.titulo,
    l.autor,
    l.ano_publicacao,
    l.ISBN,
    l.capa_url,
    l.disponivel,
    l.qtd_total,
    l.qtd_disponivel,
    c.codigo AS cdd_codigo,
    c.descricao AS cdd_descricao
  FROM livros l
  LEFT JOIN cdd c ON c.id = l.categoria
  WHERE l.id = ?
  LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);

if (!$row) json_out(false, ["message" => "Livro não encontrado."]);

// ===== Sinopse via API (não salva) =====
$isbn = only_digits($row["ISBN"] ?? "");
$sinopse = null;
$fonte = null;
$debug = null;

// 1) OpenLibrary: works + description
if ($isbn !== "" && (strlen($isbn) === 10 || strlen($isbn) === 13)) {
  $urlOL = "https://openlibrary.org/isbn/" . rawurlencode($isbn) . ".json";
  $ol = http_get_json($urlOL, $debug);

  if (is_array($ol)) {
    // description pode vir string ou objeto { value: "..." }
    if (!empty($ol["description"])) {
      if (is_array($ol["description"]) && !empty($ol["description"]["value"])) {
        $sinopse = trim((string)$ol["description"]["value"]);
      } elseif (is_string($ol["description"])) {
        $sinopse = trim($ol["description"]);
      }
      if ($sinopse) $fonte = "openlibrary";
    }
  }
}

// 2) Fallback Google Books: description
if (!$sinopse && $isbn !== "" && (strlen($isbn) === 10 || strlen($isbn) === 13)) {
  $urlGB = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . rawurlencode($isbn);
  $gb = http_get_json($urlGB, $debug);

  if (is_array($gb) && !empty($gb["items"][0]["volumeInfo"]["description"])) {
    $sinopse = trim((string)$gb["items"][0]["volumeInfo"]["description"]);
    if ($sinopse) $fonte = "google";
  }
}

json_out(true, [
  "book" => [
    "id" => (int)$row["id"],
    "titulo" => (string)$row["titulo"],
    "autor" => (string)($row["autor"] ?? ""),
    "ano" => (string)($row["ano_publicacao"] ?? ""),
    "isbn" => (string)($row["ISBN"] ?? ""),
    "capa_url" => (string)($row["capa_url"] ?? ""),
    "disponivel" => (int)($row["disponivel"] ?? 1),
    "qtd_total" => (int)($row["qtd_total"] ?? 1),
    "qtd_disponivel" => (int)($row["qtd_disponivel"] ?? 0),
    "cdd" => trim((string)($row["cdd_codigo"] ?? "")) ? (($row["cdd_codigo"] ?? "") . " - " . ($row["cdd_descricao"] ?? "")) : "-"
  ],
  "sinopse" => $sinopse,          // pode ser null
  "source" => $fonte,             // openlibrary | google | null
  "debug" => null                 // deixa null pra não vazar info no front
]);
