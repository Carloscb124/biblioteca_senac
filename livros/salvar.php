<?php
include("../conexao.php");
include("../includes/flash.php");

// =======================
// 1) Lê inputs
// =======================
$titulo    = trim($_POST['titulo'] ?? '');
$autor     = trim($_POST['autor'] ?? '');
$anoRaw    = $_POST['ano_publicacao'] ?? '';
$isbnRaw   = trim($_POST['ISBN'] ?? '');
$qtd_add   = (int)($_POST['qtd_total'] ?? 1);
$categoria = (int)($_POST['categoria'] ?? 0);

// NOVO: sinopse
$sinopse = trim($_POST['sinopse'] ?? '');

// capa_url pode vir da API (hidden) ou URL manual
$capa_url = trim($_POST['capa_url'] ?? '');

// =======================
// 2) Normalizações
// =======================
$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) $ano = (int)$anoRaw;

// ISBN salva só dígitos
$isbn = preg_replace('/\D+/', '', $isbnRaw);

// =======================
// 3) Validações básicas
// =======================
if ($titulo === '') {
  flash_set('danger', 'O título é obrigatório.');
  header("Location: cadastrar.php"); exit;
}
if ($isbn === '' || (strlen($isbn) !== 10 && strlen($isbn) !== 13)) {
  flash_set('danger', 'ISBN inválido. Use 10 ou 13 dígitos.');
  header("Location: cadastrar.php"); exit;
}
if ($qtd_add < 1) {
  flash_set('danger', 'A quantidade de exemplares deve ser no mínimo 1.');
  header("Location: cadastrar.php"); exit;
}
if ($categoria < 1) {
  flash_set('danger', 'Selecione uma categoria (CDD).');
  header("Location: cadastrar.php"); exit;
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

mysqli_begin_transaction($conn);

try {

  // =======================
  // 5) Insert + se ISBN já existe, soma quantidade
  // =======================
  $stmt = mysqli_prepare($conn, "
    INSERT INTO livros
      (titulo, autor, ano_publicacao, ISBN, capa_url, categoria, sinopse, qtd_total, qtd_disp, disponivel)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
      titulo = VALUES(titulo),
      autor  = VALUES(autor),
      ano_publicacao = VALUES(ano_publicacao),
      categoria = VALUES(categoria),

      sinopse = CASE
        WHEN VALUES(sinopse) IS NULL OR VALUES(sinopse) = '' THEN sinopse
        ELSE VALUES(sinopse)
      END,

      capa_url = CASE
        WHEN VALUES(capa_url) IS NULL OR VALUES(capa_url) = '' THEN capa_url
        ELSE VALUES(capa_url)
      END,

      qtd_total = qtd_total + VALUES(qtd_total),
      qtd_disp  = qtd_disp  + VALUES(qtd_disp),
      disponivel = 1
  ");

  // ✅ ATENÇÃO AQUI: 9 placeholders => 9 tipos => 9 variáveis
  // titulo(s), autor(s), ano(i), isbn(s), capa(s), categoria(i), sinopse(s), qtd_total(i), qtd_disp(i)
  mysqli_stmt_bind_param(
    $stmt,
    "ssissisii",
    $titulo,
    $autor,
    $ano,
    $isbn,
    $capaDb,
    $categoria,
    $sinopse,
    $qtd_add,
    $qtd_add
  );

  mysqli_stmt_execute($stmt);
  mysqli_commit($conn);

  flash_set('success', 'Livro cadastrado / exemplares adicionados com sucesso!');
  header("Location: listar.php"); exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  flash_set('danger', 'Erro ao salvar: ' . $e->getMessage());
  header("Location: cadastrar.php"); exit;
}
