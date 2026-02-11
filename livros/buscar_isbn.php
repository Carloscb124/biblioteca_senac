<?php
header("Content-Type: application/json; charset=UTF-8");

/*
  buscar_isbn.php
  - Recebe: ?isbn=...
  - Retorna JSON com:
    titulo, autor, ano_publicacao, sinopse, assuntos, editora, capa_url
  - Fontes:
    1) OpenLibrary (isbn)
    2) OpenLibrary Search (isbn)
    3) Google Books (isbn) usando API KEY
*/

function onlyDigits($s) {
  return preg_replace('/\D+/', '', (string)$s);
}

function http_get_json(string $url): ?array {
  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "timeout" => 8,
      "header" => "User-Agent: Biblioteca/1.0\r\nAccept: application/json\r\n"
    ]
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false || trim($raw) === "") return null;

  $json = json_decode($raw, true);
  return is_array($json) ? $json : null;
}

$isbn = onlyDigits($_GET["isbn"] ?? "");
if ($isbn === "" || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  echo json_encode(["ok" => false, "error" => "ISBN inválido."]);
  exit;
}

// Chave do Google Books
$apiKey = "AIzaSyC0XMLoWNignDCZF5IuCpTR95ffZZMGBxU";

$data = [
  "ok" => false,
  "source" => null,
  "isbn" => $isbn,
  "titulo" => null,
  "autor" => null,
  "ano_publicacao" => null,
  "sinopse" => null,
  "assuntos" => null,
  "editora" => null,
  "capa_url" => null,
];

/* =========================
   1) OpenLibrary (ISBN)
   ========================= */
$ol = http_get_json("https://openlibrary.org/isbn/{$isbn}.json");
if ($ol) {
  $data["ok"] = true;
  $data["source"] = "openlibrary-isbn";

  if (!empty($ol["title"])) $data["titulo"] = $ol["title"];

  if (!empty($ol["authors"][0]["key"])) {
    $aKey = $ol["authors"][0]["key"];
    $a = http_get_json("https://openlibrary.org{$aKey}.json");
    if ($a && !empty($a["name"])) $data["autor"] = $a["name"];
  }

  if (!empty($ol["publish_date"])) {
    if (preg_match('/(1[5-9]\d{2}|20\d{2})/', (string)$ol["publish_date"], $m)) {
      $data["ano_publicacao"] = (int)$m[0];
    }
  }

  if (!empty($ol["description"])) {
    if (is_array($ol["description"]) && !empty($ol["description"]["value"])) {
      $data["sinopse"] = trim((string)$ol["description"]["value"]);
    } elseif (is_string($ol["description"])) {
      $data["sinopse"] = trim($ol["description"]);
    }
  }

  // OpenLibrary ISBN às vezes traz "subjects"
  if (!empty($ol["subjects"]) && is_array($ol["subjects"])) {
    $subs = array_slice($ol["subjects"], 0, 10);
    $data["assuntos"] = implode(", ", array_map("strval", $subs));
  }

  // Editora (publishers)
  if (!empty($ol["publishers"]) && is_array($ol["publishers"])) {
    $data["editora"] = (string)$ol["publishers"][0];
  }

  // Capa por ISBN
  $data["capa_url"] = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false";
}

/* =========================
   2) OpenLibrary Search
   ========================= */
$needSearch = (
  !$data["ok"] ||
  empty($data["titulo"]) ||
  empty($data["autor"]) ||
  empty($data["assuntos"]) ||
  empty($data["editora"]) ||
  empty($data["sinopse"])
);

if ($needSearch) {
  $s = http_get_json("https://openlibrary.org/search.json?isbn={$isbn}");
  if ($s && !empty($s["docs"][0])) {
    $doc = $s["docs"][0];

    $data["ok"] = true;
    $data["source"] = $data["source"] ? ($data["source"] . "+ol-search") : "ol-search";

    if (empty($data["titulo"]) && !empty($doc["title"])) $data["titulo"] = $doc["title"];
    if (empty($data["autor"]) && !empty($doc["author_name"][0])) $data["autor"] = $doc["author_name"][0];
    if (empty($data["ano_publicacao"]) && !empty($doc["first_publish_year"])) $data["ano_publicacao"] = (int)$doc["first_publish_year"];

    // Assuntos
    if (empty($data["assuntos"]) && !empty($doc["subject"]) && is_array($doc["subject"])) {
      $subs = array_slice($doc["subject"], 0, 10);
      $data["assuntos"] = implode(", ", array_map("strval", $subs));
    }

    // Editora
    if (empty($data["editora"]) && !empty($doc["publisher"][0])) {
      $data["editora"] = (string)$doc["publisher"][0];
    }

    // Sinopse pelo Work (quando disponível)
    if (empty($data["sinopse"]) && !empty($doc["key"])) {
      $work = http_get_json("https://openlibrary.org{$doc["key"]}.json");
      if ($work && !empty($work["description"])) {
        if (is_array($work["description"]) && !empty($work["description"]["value"])) {
          $data["sinopse"] = trim((string)$work["description"]["value"]);
        } elseif (is_string($work["description"])) {
          $data["sinopse"] = trim($work["description"]);
        }
      }
    }

    // Capa por cover_i
    if (!empty($doc["cover_i"])) {
      $data["capa_url"] = "https://covers.openlibrary.org/b/id/{$doc["cover_i"]}-L.jpg";
    }
  }
}

/* =========================
   3) Google Books (API key)
   ========================= */
$needGoogle = (
  !$data["ok"] ||
  empty($data["titulo"]) ||
  empty($data["autor"]) ||
  empty($data["sinopse"]) ||
  empty($data["assuntos"]) ||
  empty($data["editora"])
);

if ($needGoogle) {
  $urlGoogle = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}&key={$apiKey}";
  $gb = http_get_json($urlGoogle);

  if ($gb && !empty($gb["items"][0]["volumeInfo"])) {
    $vi = $gb["items"][0]["volumeInfo"];

    $data["ok"] = true;
    $data["source"] = $data["source"] ? ($data["source"] . "+google") : "google";

    if (empty($data["titulo"]) && !empty($vi["title"])) $data["titulo"] = $vi["title"];
    if (empty($data["autor"]) && !empty($vi["authors"]) && is_array($vi["authors"])) {
      $data["autor"] = implode(", ", $vi["authors"]);
    }

    if (empty($data["ano_publicacao"]) && !empty($vi["publishedDate"])) {
      if (preg_match('/(1[5-9]\d{2}|20\d{2})/', (string)$vi["publishedDate"], $m)) {
        $data["ano_publicacao"] = (int)$m[0];
      }
    }

    if (empty($data["sinopse"]) && !empty($vi["description"])) {
      $data["sinopse"] = trim(strip_tags((string)$vi["description"]));
    }

    // Assuntos (Google usa "categories")
    if (empty($data["assuntos"]) && !empty($vi["categories"]) && is_array($vi["categories"])) {
      $cats = array_slice($vi["categories"], 0, 10);
      $data["assuntos"] = implode(", ", array_map("strval", $cats));
    }

    // Editora
    if (empty($data["editora"]) && !empty($vi["publisher"])) {
      $data["editora"] = (string)$vi["publisher"];
    }

    if (!empty($vi["imageLinks"]["thumbnail"])) {
      $data["capa_url"] = str_replace("http://", "https://", $vi["imageLinks"]["thumbnail"]);
    }
  }
}

echo json_encode($data);
