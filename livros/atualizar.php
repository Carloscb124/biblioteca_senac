<?php
include("../conexao.php");
include("../includes/flash.php");

/*
  atualizar.php
  - Atualiza livro existente
  - Mantém editora/sinopse/assuntos/capa se vierem vazios
  - qtd_add soma em qtd_total e qtd_disp
*/

$id = (int)($_POST["id"] ?? 0);

$titulo   = trim($_POST['titulo'] ?? '');
$autor    = trim($_POST['autor'] ?? '');
$editora  = trim($_POST['editora'] ?? '');
$sinopse  = trim($_POST['sinopse'] ?? '');
$assuntos = trim($_POST['assuntos'] ?? '');

$anoRaw   = $_POST['ano_publicacao'] ?? '';
$isbnRaw  = trim($_POST['ISBN'] ?? '');

$categoria  = (int)($_POST['categoria'] ?? 0);
$disponivel = (int)($_POST['disponivel'] ?? 1);

$qtd_add  = (int)($_POST['qtd_add'] ?? 0);

$capa_url = trim($_POST['capa_url'] ?? '');

// Normalizações
$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) $ano = (int)$anoRaw;

$isbn = preg_replace('/\D+/', '', $isbnRaw);

if ($qtd_add < 0) $qtd_add = 0;
$disponivel = ($disponivel === 1) ? 1 : 0;

// Validações
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

// Upload de capa (opcional)
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

// UPDATE com “mantém se vier vazio”
$stmt = mysqli_prepare($conn, "
  UPDATE livros
  SET
    titulo = ?,
    autor = ?,

    editora = CASE
      WHEN ? IS NULL OR ? = '' THEN editora
      ELSE ?
    END,

    sinopse = CASE
      WHEN ? IS NULL OR ? = '' THEN sinopse
      ELSE ?
    END,

    assuntos = CASE
      WHEN ? IS NULL OR ? = '' THEN assuntos
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

/*
  Ordem dos ? (21):
  1 titulo (s)
  2 autor (s)

  3-5 editora (s,s,s)
  6-8 sinopse (s,s,s)
  9-11 assuntos (s,s,s)

  12 ano_publicacao (i)
  13 ISBN (s)
  14 categoria (i)

  15-17 capa_url (s,s,s)

  18 qtd_add (i)
  19 qtd_add (i)
  20 disponivel (i)
  21 id (i)

  String de tipos (21 chars):
  11x s, depois i, depois s, depois i, depois 3x s, depois 4x i
  => "sssssssssssisisssiiii"
*/

mysqli_stmt_bind_param(
  $stmt,
  "sssssssssssisisssiiii",
  $titulo,      // 1
  $autor,       // 2

  $editora,     // 3
  $editora,     // 4
  $editora,     // 5

  $sinopse,     // 6
  $sinopse,     // 7
  $sinopse,     // 8

  $assuntos,    // 9
  $assuntos,    // 10
  $assuntos,    // 11

  $ano,         // 12
  $isbn,        // 13
  $categoria,   // 14

  $capaDb,      // 15
  $capaDb,      // 16
  $capaDb,      // 17

  $qtd_add,     // 18
  $qtd_add,     // 19
  $disponivel,  // 20
  $id           // 21
);

if (mysqli_stmt_execute($stmt)) {
  flash_set("success", "Livro atualizado com sucesso!");
  header("Location: listar.php"); exit;
}

flash_set("danger", "Erro ao atualizar o livro.");
header("Location: editar.php?id=" . $id); exit;
