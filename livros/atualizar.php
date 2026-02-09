<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST["id"] ?? 0);

$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbnRaw = trim($_POST['ISBN'] ?? '');
$categoria = (int)($_POST['categoria'] ?? 0);
$disponivel = (int)($_POST['disponivel'] ?? 1);

// hidden (API) ou URL manual
$capa_url = trim($_POST['capa_url'] ?? '');

// Normaliza
$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) $ano = (int)$anoRaw;
$isbn = preg_replace('/\D+/', '', $isbnRaw);

if ($id < 1) {
  flash_set("danger", "ID inválido.");
  header("Location: listar.php"); exit;
}
if ($titulo === '') {
  flash_set('danger', 'O título é obrigatório.');
  header("Location: editar.php?id=" . $id); exit;
}
if ($isbn === '' || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  flash_set('danger', 'ISBN inválido. Use 10 ou 13 dígitos.');
  header("Location: editar.php?id=" . $id); exit;
}
if ($categoria < 1) {
  flash_set('danger', 'Selecione uma categoria (CDD).');
  header("Location: editar.php?id=" . $id); exit;
}
$disponivel = ($disponivel === 1) ? 1 : 0;

// Upload de capa (se tiver) -> vira capa_url
if (isset($_FILES["capa_arquivo"]) && $_FILES["capa_arquivo"]["error"] === UPLOAD_ERR_OK) {
  $tmp = $_FILES["capa_arquivo"]["tmp_name"];
  $name = $_FILES["capa_arquivo"]["name"] ?? "capa";
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

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

// Se capa_url vier vazia, não apaga a atual (mantém)
$stmt = mysqli_prepare($conn, "
  UPDATE livros
  SET
    titulo = ?,
    autor = ?,
    ano_publicacao = ?,
    ISBN = ?,
    categoria = ?,
    capa_url = CASE
      WHEN ? IS NULL OR ? = '' THEN capa_url
      ELSE ?
    END,
    disponivel = ?
  WHERE id = ?
");

$capaDb = ($capa_url !== '') ? $capa_url : null;

mysqli_stmt_bind_param(
  $stmt,
  "ssisiissii",
  $titulo,
  $autor,
  $ano,
  $isbn,
  $categoria,
  $capaDb, $capaDb, $capaDb,
  $disponivel,
  $id
);

if (mysqli_stmt_execute($stmt)) {
  flash_set("success", "Livro atualizado com sucesso!");
  header("Location: listar.php"); exit;
}

flash_set("danger", "Erro ao atualizar o livro.");
header("Location: editar.php?id=" . $id); exit;
