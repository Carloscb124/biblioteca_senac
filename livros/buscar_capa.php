<?php
// livros/buscar_capa.php
// Objetivo: dado um ISBN, devolver uma URL de capa SEM salvar no banco.
// Estratégia robusta:
// 1) OpenLibrary (capa direta por ISBN) com default=false (404 quando não existe)
// 2) Google Books (varre items até achar imageLinks) + tenta melhorar qualidade
// 3) Valida acessibilidade com HEAD -> GET leve (Range) -> GET sem Range (fallback)

include("../auth/auth_guard.php");

header("Content-Type: application/json; charset=UTF-8");

function only_digits($v) {
  return preg_replace('/\D+/', '', (string)$v);
}

function json_out($ok, $url = null, $source = null, $msg = null, $debug = null) {
  echo json_encode([
    "ok" => (bool)$ok,
    "url" => $url,
    "source" => $source,
    "message" => $msg,
    "debug" => $debug,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function has_curl() {
  return function_exists("curl_init");
}

function curl_request($url, $method = "GET", $headers = [], $timeout = 10, $range = null, &$info = null, &$err = null) {
  $info = [];
  $err = null;

  if (!has_curl()) return false;

  $ch = curl_init($url);

  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);      // ok em dev/xampp
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
    "User-Agent: BibliotecaSenac/1.0",
    "Accept: */*"
  ], $headers));

  if ($method === "HEAD") {
    curl_setopt($ch, CURLOPT_NOBODY, true);
  } else {
    curl_setopt($ch, CURLOPT_NOBODY, false);
  }

  if ($range !== null) {
    curl_setopt($ch, CURLOPT_RANGE, $range); // ex: "0-2048"
  }

  $body = curl_exec($ch);

  if ($body === false) {
    $err = curl_error($ch);
    curl_close($ch);
    return false;
  }

  $info = [
    "http_code" => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
    "content_type" => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
    "final_url" => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
  ];

  curl_close($ch);
  return $body;
}

/**
 * Checa se uma imagem existe e é acessível.
 * Estratégia:
 * - HEAD (rápido)
 * - GET com Range curto (não baixa tudo)
 * - GET sem Range (se servidor implicar com Range)
 *
 * Aceita:
 * - 200 / 206 como OK
 * - 3xx como OK (com follow)
 * - 416 como OK (range não satisfaz, mas geralmente o recurso existe)
 */
function image_exists($url, &$debug = null) {
  $debug = null;

  // 1) cURL
  if (has_curl()) {
    // HEAD
    $info = $err = null;
    curl_request($url, "HEAD", [], 8, null, $info, $err);
    $code = (int)($info["http_code"] ?? 0);

    if ($code >= 200 && $code < 400) return true;

    // GET com Range curto
    $info2 = $err2 = null;
    $body = curl_request($url, "GET", [], 8, "0-2048", $info2, $err2);

    if ($body === false) {
      $debug = "cURL GET(range) error: " . ($err2 ?: "unknown");
      return false;
    }

    $code2 = (int)($info2["http_code"] ?? 0);

    if ($code2 === 200 || $code2 === 206 || $code2 === 416) return true;

    // GET sem Range (fallback final)
    $info3 = $err3 = null;
    $body2 = curl_request($url, "GET", [], 8, null, $info3, $err3);

    if ($body2 === false) {
      $debug = "cURL GET(no-range) error: " . ($err3 ?: "unknown");
      return false;
    }

    $code3 = (int)($info3["http_code"] ?? 0);
    if ($code3 === 200) return true;

    $debug = "HTTP codes: head={$code}, range={$code2}, no-range={$code3}";
    return false;
  }

  // 2) fallback sem cURL (depende de allow_url_fopen)
  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "timeout" => 10,
      "ignore_errors" => true,
      "header" => "User-Agent: BibliotecaSenac/1.0\r\nAccept: */*\r\n"
    ],
    "ssl" => [
      "verify_peer" => false,
      "verify_peer_name" => false,
    ]
  ]);

  $fp = @fopen($url, "r", false, $ctx);
  if (!$fp) {
    $debug = "fopen falhou (allow_url_fopen pode estar OFF).";
    return false;
  }
  @fread($fp, 1);
  @fclose($fp);

  global $http_response_header;
  $first = is_array($http_response_header) ? ($http_response_header[0] ?? "") : "";

  if (strpos($first, "200") !== false || strpos($first, "206") !== false || strpos($first, "416") !== false) {
    return true;
  }

  $debug = "HTTP: " . $first;
  return false;
}

/**
 * GET JSON (Google Books) - com cURL quando possível.
 */
function get_json($url, &$debug = null) {
  $debug = null;

  if (has_curl()) {
    $info = $err = null;
    $raw = curl_request($url, "GET", ["Accept: application/json"], 12, null, $info, $err);

    if ($raw === false) {
      $debug = "cURL JSON error: " . ($err ?: "unknown");
      return null;
    }

    $code = (int)($info["http_code"] ?? 0);
    if ($code < 200 || $code >= 400) {
      $debug = "HTTP code: " . $code;
      return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      $debug = "JSON inválido (decode falhou).";
      return null;
    }
    return $data;
  }

  $ctx = stream_context_create([
    "http" => [
      "timeout" => 12,
      "ignore_errors" => true,
      "header" => "User-Agent: BibliotecaSenac/1.0\r\nAccept: application/json\r\n"
    ],
    "ssl" => [
      "verify_peer" => false,
      "verify_peer_name" => false,
    ]
  ]);

  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) {
    $debug = "file_get_contents falhou (allow_url_fopen pode estar OFF).";
    return null;
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    $debug = "JSON inválido (decode falhou).";
    return null;
  }
  return $data;
}

/**
 * Tenta melhorar qualidade do thumbnail do Google:
 * - força https
 * - tenta aumentar zoom (quando existe)
 */
function improve_google_thumb($url) {
  if (!$url) return $url;
  $url = preg_replace('/^http:/i', 'https:', $url);

  // tenta subir zoom (quando existe)
  if (preg_match('/([&?])zoom=\d/i', $url)) {
    $url = preg_replace('/([&?])zoom=\d/i', '$1zoom=2', $url);
  }

  return $url;
}

/**
 * No Google Books, às vezes o primeiro item não tem capa.
 * Essa função varre todos os items até achar thumbnail/smallThumbnail.
 */
function find_google_image_url($data, &$pickedIndex = null) {
  $pickedIndex = null;
  if (!is_array($data) || empty($data["items"]) || !is_array($data["items"])) return null;

  foreach ($data["items"] as $i => $it) {
    $info = $it["volumeInfo"] ?? null;
    if (!is_array($info)) continue;

    $img = $info["imageLinks"] ?? null;
    if (!is_array($img)) continue;

    $u = $img["thumbnail"] ?? ($img["smallThumbnail"] ?? null);
    if ($u) {
      $pickedIndex = $i;
      return $u;
    }
  }

  return null;
}

// ===== Entrada =====
$isbn = only_digits($_GET["isbn"] ?? "");
if ($isbn === "" || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  json_out(false, null, null, "ISBN inválido (use 10 ou 13 dígitos).");
}

// ===== 1) OpenLibrary primeiro =====
$debugImg = null;
$open = "https://covers.openlibrary.org/b/isbn/" . rawurlencode($isbn) . "-L.jpg?default=false";

if (image_exists($open, $debugImg)) {
  json_out(true, $open, "openlibrary", null, null);
}

// ===== 2) Google Books fallback =====
$debugJson = null;
$api = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . rawurlencode($isbn);
$data = get_json($api, $debugJson);

if (!$data) {
  json_out(false, null, null, "Falha ao consultar Google Books.", $debugJson ?: $debugImg);
}

$picked = null;
$rawUrl = find_google_image_url($data, $picked);

if (!$rawUrl) {
  // às vezes não vem imageLinks, mas tem industryIdentifiers etc. Sem capa mesmo.
  json_out(false, null, null, "Google Books não retornou imagem.", [
    "google_debug" => $debugJson,
    "openlibrary_debug" => $debugImg,
    "total_items" => (int)($data["totalItems"] ?? 0),
  ]);
}

$url = improve_google_thumb($rawUrl);

// valida acessibilidade da URL melhorada
$debugImg2 = null;
if (image_exists($url, $debugImg2)) {
  json_out(true, $url, "google", null, [
    "picked_item_index" => $picked,
    "original_url" => $rawUrl,
  ]);
}

// se a melhorada falhar, tenta a original (sem mexer no zoom, etc.)
$debugImg3 = null;
$rawUrl2 = preg_replace('/^http:/i', 'https:', $rawUrl);
if ($rawUrl2 !== $url && image_exists($rawUrl2, $debugImg3)) {
  json_out(true, $rawUrl2, "google", null, [
    "picked_item_index" => $picked,
    "original_url" => $rawUrl,
    "note" => "Usando URL original porque a melhorada falhou.",
  ]);
}

json_out(false, null, null, "Imagem retornada mas não acessível.", [
  "picked_item_index" => $picked,
  "url_tested" => $url,
  "debug_img" => $debugImg2 ?: $debugImg3,
  "google_debug" => $debugJson,
  "openlibrary_debug" => $debugImg
]);