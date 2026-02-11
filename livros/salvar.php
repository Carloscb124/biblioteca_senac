<?php
include("../conexao.php");
include("../includes/flash.php");

/*
  salvar.php
  - Cria livro novo
  - Se ISBN já existir (UNIQUE): soma exemplares em qtd_total e qtd_disp
  - Salva: titulo, autor, editora, ano_publicacao, ISBN, capa_url, categoria, sinopse, assuntos
*/

$titulo   = trim($_POST['titulo'] ?? '');
$autor    = trim($_POST['autor'] ?? '');
$editora  = trim($_POST['editora'] ?? '');
$sinopse  = trim($_POST['sinopse'] ?? '');
$assuntos = trim($_POST['assuntos'] ?? '');

$anoRaw   = $_POST['ano_publicacao'] ?? '';
$isbnRaw  = trim($_POST['ISBN'] ?? '');

$qtd_add  = (int)($_POST['qtd_total'] ?? 1);
$categoria = (int)($_POST['categoria'] ?? 0);

$capa_url = trim($_POST['capa_url'] ?? '');

// Normalizações
$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) $ano = (int)$anoRaw;

$isbn = preg_replace('/\D+/', '', $isbnRaw);

if ($qtd_add < 1) $qtd_add = 1;

// Validações
if ($titulo === '') {
  flash_set("danger", "O título é obrigatório.");
  header("Location: cadastrar.php"); exit;
}
if ($isbn === '' || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  flash_set("danger", "ISBN inválido. Use 10 ou 13 dígitos.");
  header("Location: cadastrar.php"); exit;
}
if ($categoria < 1) {
  flash_set("danger", "Selecione uma categoria (CDD).");
  header("Location: cadastrar.php"); exit;
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

mysqli_begin_transaction($conn);

try {

  /*
    IMPORTANTE:
    - Esse INSERT pressupõe que ISBN é UNIQUE na tabela livros.
    - disponivel fica sempre 1 no cadastro.
    - qtd_total e qtd_disp sobem junto quando é livro novo ou quando soma exemplares.

    Placeholders (?) no VALUES:
      1 titulo
      2 autor
      3 editora
      4 ano_publicacao
      5 ISBN
      6 capa_url
      7 categoria
      8 sinopse
      9 assuntos
      10 qtd_total
      11 qtd_disp
  */

  $stmt = mysqli_prepare($conn, "
    INSERT INTO livros
      (titulo, autor, editora, ano_publicacao, ISBN, capa_url, categoria, sinopse, assuntos, qtd_total, qtd_disp, disponivel)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
      titulo = VALUES(titulo),
      autor  = VALUES(autor),

      editora = CASE
        WHEN VALUES(editora) IS NULL OR VALUES(editora) = '' THEN editora
        ELSE VALUES(editora)
      END,

      ano_publicacao = VALUES(ano_publicacao),
      categoria = VALUES(categoria),

      sinopse = CASE
        WHEN VALUES(sinopse) IS NULL OR VALUES(sinopse) = '' THEN sinopse
        ELSE VALUES(sinopse)
      END,

      assuntos = CASE
        WHEN VALUES(assuntos) IS NULL OR VALUES(assuntos) = '' THEN assuntos
        ELSE VALUES(assuntos)
      END,

      capa_url = CASE
        WHEN VALUES(capa_url) IS NULL OR VALUES(capa_url) = '' THEN capa_url
        ELSE VALUES(capa_url)
      END,

      qtd_total = qtd_total + VALUES(qtd_total),
      qtd_disp  = qtd_disp  + VALUES(qtd_disp),
      disponivel = 1
  ");

  /*
    Tipos (11):
      titulo(s), autor(s), editora(s), ano(i),
      isbn(s), capa_url(s), categoria(i),
      sinopse(s), assuntos(s),
      qtd_total(i), qtd_disp(i)

    => "sssississii"
  */

  mysqli_stmt_bind_param(
    $stmt,
    "sssississii",
    $titulo,
    $autor,
    $editora,
    $ano,
    $isbn,
    $capaDb,
    $categoria,
    $sinopse,
    $assuntos,
    $qtd_add,
    $qtd_add
  );

  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);

  flash_set("success", "Livro salvo com sucesso!");
  header("Location: listar.php"); exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  flash_set("danger", "Erro ao salvar: " . $e->getMessage());
  header("Location: cadastrar.php"); exit;
}
