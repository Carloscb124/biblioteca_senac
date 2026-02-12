<?php
header("Content-Type: application/json; charset=UTF-8");

/*
  buscar_isbn.php
  - Recebe: ?isbn=...
  - Retorna JSON com:
    ok, titulo, autor, ano_publicacao, sinopse, assuntos, editora, capa_url
  - Fontes:
    1) OpenLibrary (isbn)
    2) OpenLibrary Search (isbn)
    3) Google Books (isbn) usando API KEY
*/

function onlyDigits($s) {
  return preg_replace('/\D+/', '', (string)$s);
}

function httpGetJson($url, $timeout = 10) {
  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "header" => "User-Agent: BibliotecaSENAC/1.0\r\n",
      "timeout" => $timeout
    ]
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;

  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}

$isbn = onlyDigits($_GET["isbn"] ?? "");

if ($isbn === "" || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  echo json_encode(["ok" => false, "error" => "ISBN inválido"]);
  exit;
}

// Estrutura padrão de resposta
$data = [
  "ok" => false,
  "isbn" => $isbn,
  "titulo" => "",
  "autor" => "",
  "ano_publicacao" => "",
  "sinopse" => "",
  "assuntos" => "",
  "editora" => "",
  "capa_url" => ""
];

/* =========================
   1) OpenLibrary (ISBN direto)
   ========================= */
$ol = httpGetJson("https://openlibrary.org/isbn/$isbn.json");

if ($ol) {
  $data["ok"] = true;
  $data["titulo"] = $ol["title"] ?? $data["titulo"];

  // Ano (extrai 4 dígitos)
  if (!empty($ol["publish_date"]) && preg_match('/(\d{4})/', $ol["publish_date"], $m)) {
    $data["ano_publicacao"] = $m[1];
  }

  // Editora
  if (!empty($ol["publishers"][0])) {
    $data["editora"] = (string)$ol["publishers"][0];
  }

  // Autor (precisa buscar pelo key)
  if (!empty($ol["authors"][0]["key"])) {
    $a = httpGetJson("https://openlibrary.org" . $ol["authors"][0]["key"] . ".json");
    if ($a && !empty($a["name"])) $data["autor"] = (string)$a["name"];
  }

  // Assuntos
  if (!empty($ol["subjects"]) && is_array($ol["subjects"])) {
    $data["assuntos"] = implode(", ", array_slice(array_map("strval", $ol["subjects"]), 0, 12));
  }

  // Sinopse
  if (!empty($ol["description"])) {
    $data["sinopse"] = is_array($ol["description"])
      ? ($ol["description"]["value"] ?? "")
      : (string)$ol["description"];
  }

  // Capa
  $data["capa_url"] = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false";
}

/* =========================
   2) OpenLibrary Search (fallback)
   ========================= */
if (!$data["ok"] || $data["titulo"] === "") {
  $ols = httpGetJson("https://openlibrary.org/search.json?isbn=$isbn");

  if ($ols && !empty($ols["docs"][0])) {
    $doc = $ols["docs"][0];
    $data["ok"] = true;

    if (empty($data["titulo"]) && !empty($doc["title"])) $data["titulo"] = (string)$doc["title"];
    if (empty($data["autor"]) && !empty($doc["author_name"][0])) $data["autor"] = (string)$doc["author_name"][0];
    if (empty($data["ano_publicacao"]) && !empty($doc["first_publish_year"])) $data["ano_publicacao"] = (string)$doc["first_publish_year"];
    if (empty($data["assuntos"]) && !empty($doc["subject"])) {
      $data["assuntos"] = implode(", ", array_slice(array_map("strval", $doc["subject"]), 0, 12));
    }
    if (empty($data["capa_url"]) && !empty($doc["cover_i"])) {
      $data["capa_url"] = "https://covers.openlibrary.org/b/id/" . $doc["cover_i"] . "-L.jpg";
    }
  }
}

/* =========================
   3) Google Books (fallback)
   =========================
   Se você tiver API KEY, coloque aqui. Se não tiver, ainda pode funcionar sem,
   mas às vezes limita.
*/
$GOOGLE_BOOKS_KEY = ""; // <-- se tiver, coloca aqui

if (!$data["ok"] || $data["titulo"] === "") {
  $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:$isbn";
  if ($GOOGLE_BOOKS_KEY !== "") $url .= "&key=" . urlencode($GOOGLE_BOOKS_KEY);

  $gb = httpGetJson($url);

  if ($gb && !empty($gb["items"][0]["volumeInfo"])) {
    $vi = $gb["items"][0]["volumeInfo"];
    $data["ok"] = true;

    if (empty($data["titulo"]) && !empty($vi["title"])) $data["titulo"] = (string)$vi["title"];
    if (empty($data["autor"]) && !empty($vi["authors"])) $data["autor"] = implode(", ", array_map("strval", $vi["authors"]));
    if (empty($data["editora"]) && !empty($vi["publisher"])) $data["editora"] = (string)$vi["publisher"];

    if (empty($data["ano_publicacao"]) && !empty($vi["publishedDate"]) && preg_match('/(\d{4})/', $vi["publishedDate"], $m)) {
      $data["ano_publicacao"] = $m[1];
    }

    if (empty($data["sinopse"]) && !empty($vi["description"])) $data["sinopse"] = (string)$vi["description"];

    if (empty($data["assuntos"]) && !empty($vi["categories"])) {
      $data["assuntos"] = implode(", ", array_map("strval", $vi["categories"]));
    }

    if (!empty($vi["imageLinks"]["thumbnail"])) {
      $data["capa_url"] = str_replace("http://", "https://", $vi["imageLinks"]["thumbnail"]);
    }
  }
}

echo json_encode($data);
