<?php
// livros/buscar_capa.php
// Objetivo: dado um ISBN, devolver uma URL de capa SEM salvar no banco.
// Estratégia: OpenLibrary primeiro, depois Google Books (fallback).

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
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/**
 * Checa se uma imagem existe e é acessível.
 * - Tenta HEAD (rápido)
 * - Se o servidor não curtir HEAD, tenta GET leve (baixando pouquíssimo)
 */
function image_exists($url, &$debug = null) {
  $debug = null;

  // Preferência: cURL (mais confiável no Windows/XAMPP)
  if (function_exists("curl_init")) {
    // 1) HEAD
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: BibliotecaSenac/1.0"]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 200 && $code < 400) return true;

    // Alguns hosts dão 403/405 pra HEAD. Então tenta GET bem leve.
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "User-Agent: BibliotecaSenac/1.0",
      // Range ajuda a não baixar o arquivo inteiro, mas se o servidor bloquear,
      // ele ainda pode responder 200 e a gente aceita.
      "Range: bytes=0-1024",
    ]);
    $body = curl_exec($ch);

    if ($body === false) {
      $debug = "cURL GET error: " . curl_error($ch);
      curl_close($ch);
      return false;
    }

    $code2 = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 200/206 = ok. 416 às vezes aparece quando Range não é aceito em recursos pequenos,
    // mas nesses casos normalmente a imagem existe. Aqui preferi NÃO aceitar 416.
    return ($code2 === 200 || $code2 === 206);
  }

  // Fallback sem cURL (depende de allow_url_fopen)
  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "timeout" => 8,
      "ignore_errors" => true,
      "header" => "User-Agent: BibliotecaSenac/1.0\r\n"
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

  // aceita 200/206
  if (strpos($first, "200") !== false || strpos($first, "206") !== false) return true;

  $debug = "HTTP: " . $first;
  return false;
}

/**
 * GET JSON (Google Books) - com cURL quando possível.
 */
function get_json($url, &$debug = null) {
  $debug = null;

  if (function_exists("curl_init")) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: BibliotecaSenac/1.0"]);
    $raw = curl_exec($ch);

    if ($raw === false) {
      $debug = "cURL JSON error: " . curl_error($ch);
      curl_close($ch);
      return null;
    }

    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 400) {
      $debug = "HTTP code: " . $code;
      return null;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
  }

  $ctx = stream_context_create([
    "http" => [
      "timeout" => 10,
      "ignore_errors" => true,
      "header" => "User-Agent: BibliotecaSenac/1.0\r\n"
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
  return is_array($data) ? $data : null;
}

// ===== Entrada =====
$isbn = only_digits($_GET["isbn"] ?? "");
if ($isbn === "" || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  json_out(false, null, null, "ISBN inválido (use 10 ou 13 dígitos).");
}

// ===== 1) OpenLibrary primeiro =====
$debugImg = null;
// default=false faz o OpenLibrary retornar 404 quando não existe (em vez de imagem padrão)
$open = "https://covers.openlibrary.org/b/isbn/" . rawurlencode($isbn) . "-L.jpg?default=false";

if (image_exists($open, $debugImg)) {
  json_out(true, $open, "openlibrary", null, null);
}

// ===== 2) Google Books fallback =====
$debugJson = null;
$api = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . rawurlencode($isbn);
$data = get_json($api, $debugJson);

if (!$data || empty($data["items"][0]["volumeInfo"])) {
  json_out(false, null, null, "Sem resultados no Google Books.", $debugJson ?: $debugImg);
}

$info = $data["items"][0]["volumeInfo"];
$img  = $info["imageLinks"] ?? null;

if (!$img) {
  json_out(false, null, null, "Google Books não retornou imagem.", $debugJson ?: $debugImg);
}

$url = $img["thumbnail"] ?? $img["smallThumbnail"] ?? null;
if (!$url) {
  json_out(false, null, null, "Link de imagem indisponível.", $debugJson ?: $debugImg);
}

// força https (o Google às vezes devolve http)
$url = preg_replace('/^http:/i', 'https:', $url);

// valida acessibilidade
$debugImg2 = null;
if (image_exists($url, $debugImg2)) {
  json_out(true, $url, "google", null, null);
}

json_out(false, null, null, "Imagem retornada mas não acessível.", $debugImg2 ?: $debugJson ?: $debugImg);
