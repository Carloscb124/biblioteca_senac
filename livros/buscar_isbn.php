<?php
header("Content-Type: application/json; charset=UTF-8");

function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }

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

$data = [
  "ok" => false,
  "source" => null,
  "isbn" => $isbn,
  "titulo" => null,
  "autor" => null,
  "ano_publicacao" => null,
  "sinopse" => null,
  "capa_url" => null,
];

// 1) OpenLibrary ISBN
$ol = http_get_json("https://openlibrary.org/isbn/{$isbn}.json");
if ($ol) {
  $data["ok"] = true;
  $data["source"] = "openlibrary-isbn";
  $data["titulo"] = $ol["title"] ?? null;

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

  $data["capa_url"] = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false";
}

// 2) OpenLibrary Search API (plano B)
$needSearch = (
  !$data["ok"] ||
  empty($data["titulo"]) ||
  empty($data["autor"]) ||
  empty($data["sinopse"])
);

if ($needSearch) {
  // Pode usar isbn=... ou q=isbn:...
  $s = http_get_json("https://openlibrary.org/search.json?isbn={$isbn}");

  if ($s && !empty($s["docs"][0])) {
    $doc = $s["docs"][0];

    $data["ok"] = true;
    $data["source"] = $data["source"] ? ($data["source"] . "+ol-search") : "ol-search";

    if (empty($data["titulo"]) && !empty($doc["title"])) $data["titulo"] = $doc["title"];
    if (empty($data["autor"]) && !empty($doc["author_name"][0])) $data["autor"] = $doc["author_name"][0];
    if (empty($data["ano_publicacao"]) && !empty($doc["first_publish_year"])) $data["ano_publicacao"] = (int)$doc["first_publish_year"];

    // tenta pegar sinopse via "work"
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

    // capa via cover_i (melhor às vezes)
    if (!empty($doc["cover_i"])) {
      $data["capa_url"] = "https://covers.openlibrary.org/b/id/{$doc["cover_i"]}-L.jpg";
    }
  }
}

// 3) Google Books (último fallback)
$needGoogle = (
  !$data["ok"] ||
  empty($data["titulo"]) ||
  empty($data["autor"]) ||
  empty($data["sinopse"])
);

if ($needGoogle) {
  $gb = http_get_json("https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}");
  if ($gb && !empty($gb["items"][0]["volumeInfo"])) {
    $vi = $gb["items"][0]["volumeInfo"];

    $data["ok"] = true;
    $data["source"] = $data["source"] ? ($data["source"] . "+google") : "google";

    if (empty($data["titulo"]) && !empty($vi["title"])) $data["titulo"] = $vi["title"];
    if (empty($data["autor"]) && !empty($vi["authors"]) && is_array($vi["authors"])) $data["autor"] = implode(", ", $vi["authors"]);

    if (empty($data["ano_publicacao"]) && !empty($vi["publishedDate"])) {
      if (preg_match('/(1[5-9]\d{2}|20\d{2})/', (string)$vi["publishedDate"], $m)) {
        $data["ano_publicacao"] = (int)$m[0];
      }
    }

    if (empty($data["sinopse"]) && !empty($vi["description"])) {
      $data["sinopse"] = trim(strip_tags((string)$vi["description"]));
    }

    if (!empty($vi["imageLinks"]["thumbnail"])) $data["capa_url"] = $vi["imageLinks"]["thumbnail"];
  }
}

echo json_encode($data);
