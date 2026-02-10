<?php
include("../conexao.php");
include("../includes/flash.php");

// =======================
// 1) Inputs
// =======================
$id = (int)($_POST["id"] ?? 0);

$titulo    = trim($_POST['titulo'] ?? '');
$autor     = trim($_POST['autor'] ?? '');
$sinopse   = trim($_POST['sinopse'] ?? '');

$anoRaw    = $_POST['ano_publicacao'] ?? '';
$isbnRaw   = trim($_POST['ISBN'] ?? '');

$categoria  = (int)($_POST['categoria'] ?? 0);
$disponivel = (int)($_POST['disponivel'] ?? 1);

// Quantidade pra adicionar (opcional)
$qtd_add = (int)($_POST['qtd_add'] ?? 0);

// URL manual (opcional) - se vier vazia, mantém a atual
$capa_url = trim($_POST['capa_url'] ?? '');

// =======================
// 2) Normalizações
// =======================
$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) $ano = (int)$anoRaw;

// ISBN salva só dígitos
$isbn = preg_replace('/\D+/', '', $isbnRaw);

if ($qtd_add < 0) $qtd_add = 0;
$disponivel = ($disponivel === 1) ? 1 : 0;

// =======================
// 3) Validações
// =======================
if ($id < 1) {
  flash_set("danger", "ID inválido.");
  header("Location: listar.php"); exit;
}
if ($titulo === '') {
  flash_set("danger", "O título é obrigatório.");
  header("Location: editar.php?id=" . $id); exit;
}
if ($isbn === '' || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  flash_set("danger", "ISBN inválido. Use 10 ou 13 dígitos.");
  header("Location: editar.php?id=" . $id); exit;
}
if ($categoria < 1) {
  flash_set("danger", "Selecione uma categoria (CDD).");
  header("Location: editar.php?id=" . $id); exit;
}

// =======================
// 4) Upload de capa (se tiver)
// =======================
if (isset($_FILES["capa_arquivo"]) && $_FILES["capa_arquivo"]["error"] === UPLOAD_ERR_OK) {
  $tmp  = $_FILES["capa_arquivo"]["tmp_name"];
  $name = $_FILES["capa_arquivo"]["name"] ?? "capa";
  $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  $allowed = ["jpg","jpeg","png","webp","gif"];
  if (in_array($ext, $allowed, true)) {

    $dir = __DIR__ . "/../uploads/capas";
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    $filename = "capa_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $dest = $dir . "/" . $filename;

    if (@move_uploaded_file($tmp, $dest)) {
      $capa_url = "../uploads/capas/" . $filename;
    }
  }
}

$capaDb = ($capa_url !== '') ? $capa_url : null;

// =======================
// 5) UPDATE
// - Sinopse: se vier vazia, mantém a antiga
// - Capa: se vier vazia, mantém a antiga
// - qtd_add: soma no total e nos disponíveis
// =======================
$stmt = mysqli_prepare($conn, "
  UPDATE livros
  SET
    titulo = ?,
    autor = ?,

    sinopse = CASE
      WHEN ? IS NULL OR ? = '' THEN sinopse
      ELSE ?
    END,

    ano_publicacao = ?,
    ISBN = ?,
    categoria = ?,

    capa_url = CASE
      WHEN ? IS NULL OR ? = '' THEN capa_url
      ELSE ?
    END,

    qtd_total = qtd_total + ?,
    qtd_disp  = qtd_disp  + ?,
    disponivel = ?
  WHERE id = ?
");

// ✅ IMPORTANTÍSSIMO:
// Aqui temos 15 placeholders (?) no SQL.
// Então a string de tipos tem 15 letras.
// s=string, i=inteiro

mysqli_stmt_bind_param(
  $stmt,
  "sssssisiissiiii",
  $titulo,        // 1 s
  $autor,         // 2 s

  $sinopse,       // 3 s
  $sinopse,       // 4 s
  $sinopse,       // 5 s

  $ano,           // 6 i (pode ser null, mas funciona bem aqui)
  $isbn,          // 7 s
  $categoria,     // 8 i

  $capaDb,        // 9 s
  $capaDb,        // 10 s
  $capaDb,        // 11 s

  $qtd_add,       // 12 i
  $qtd_add,       // 13 i
  $disponivel,    // 14 i
  $id             // 15 i
);

if (mysqli_stmt_execute($stmt)) {
  flash_set("success", "Livro atualizado com sucesso!");
  header("Location: listar.php"); exit;
}

flash_set("danger", "Erro ao atualizar o livro.");
header("Location: editar.php?id=" . $id); exit;
