<?php
include("../conexao.php");
include("../includes/flash.php");

// ============ Lê inputs ============
$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbnRaw = trim($_POST['ISBN'] ?? '');
$qtd_add = (int)($_POST['qtd_total'] ?? 1);
$categoria = (int)($_POST['categoria'] ?? 0);

// capa_url pode vir da API (hidden) ou da URL manual
$capa_url = trim($_POST['capa_url'] ?? '');

// ============ Normalizações ============
$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) $ano = (int)$anoRaw;

// ISBN: salva só dígitos (remove traços/pontos)
$isbn = preg_replace('/\D+/', '', $isbnRaw);

// ============ Validações ============
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

// ============ Upload de capa (se tiver) ============
// Se enviar arquivo, salva em /uploads/capas e usa isso como capa_url.
// Banco guarda só o caminho (leve).
if (isset($_FILES["capa_arquivo"]) && $_FILES["capa_arquivo"]["error"] === UPLOAD_ERR_OK) {
  $tmp = $_FILES["capa_arquivo"]["tmp_name"];
  $name = $_FILES["capa_arquivo"]["name"] ?? "capa";
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

  // extensions básicas
  $allowed = ["jpg","jpeg","png","webp","gif"];
  if (in_array($ext, $allowed, true)) {
    $dir = __DIR__ . "/../uploads/capas";
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    $filename = "capa_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    $dest = $dir . "/" . $filename;

    if (@move_uploaded_file($tmp, $dest)) {
      // caminho relativo pro site (ajuste se seu $base for diferente)
      $capa_url = "../uploads/capas/" . $filename;
    }
  }
}

mysqli_begin_transaction($conn);

try {
  /*
    Estratégia:
    - ISBN continua sendo único (um registro por obra)
    - Se cadastrar de novo com mesmo ISBN, soma exemplares:
      qtd_total += qtd_add
      qtd_disp  += qtd_add
  */
  $stmt = mysqli_prepare($conn, "
    INSERT INTO livros (titulo, autor, ano_publicacao, ISBN, capa_url, categoria, qtd_total, qtd_disp, disponivel)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
      titulo = VALUES(titulo),
      autor  = VALUES(autor),
      ano_publicacao = VALUES(ano_publicacao),
      categoria = VALUES(categoria),
      -- se vier uma capa nova, atualiza; se vier vazio, mantém a antiga
      capa_url = CASE
        WHEN VALUES(capa_url) IS NULL OR VALUES(capa_url) = '' THEN capa_url
        ELSE VALUES(capa_url)
      END,
      qtd_total = qtd_total + VALUES(qtd_total),
      qtd_disp  = qtd_disp  + VALUES(qtd_disp),
      disponivel = 1
  ");

  // ao cadastrar: qtd_total=qtd_add e qtd_disp=qtd_add
  $capaDb = ($capa_url !== '') ? $capa_url : null;

  mysqli_stmt_bind_param(
    $stmt,
    "ssissiii",
    $titulo,
    $autor,
    $ano,
    $isbn,
    $capaDb,
    $categoria,
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
