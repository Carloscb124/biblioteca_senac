<?php
$titulo_pagina = "Importar Livros (CSV + API)";
set_time_limit(600);

include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");

/* =========================
   APIs
========================= */
function http_get_json(string $url): ?array {
  $opts = [
    "http" => [
      "header" => "User-Agent: BibliotecaSystem/1.0\r\n",
      "timeout" => 12,
    ]
  ];
  $context = stream_context_create($opts);
  $raw = @file_get_contents($url, false, $context);
  if ($raw === false || $raw === "") return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

function buscarMetadadosGoogle(string $isbn): ?array {
  $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:" . urlencode($isbn);
  $data = http_get_json($url);
  if (!$data || !isset($data['items'][0]['volumeInfo'])) return null;

  $info = $data['items'][0]['volumeInfo'];
  $categorias = isset($info['categories']) ? implode(", ", $info['categories']) : null;

  return [
    'titulo'    => $info['title'] ?? null,
    'autor'     => isset($info['authors']) ? implode(", ", $info['authors']) : null,
    'ano'       => isset($info['publishedDate']) ? substr((string)$info['publishedDate'], 0, 4) : null,
    'editora'   => $info['publisher'] ?? null,
    'categoria' => $categorias,
    'assuntos'  => $categorias,
    'sinopse'   => isset($info['description']) ? substr((string)$info['description'], 0, 2000) : null,
    'fonte'     => 'Google'
  ];
}

function buscarMetadadosOpenLib(string $isbn): ?array {
  $url = "https://openlibrary.org/api/books?bibkeys=ISBN:" . urlencode($isbn) . "&format=json&jscmd=data";
  $data = http_get_json($url);
  if (!$data) return null;

  $key = "ISBN:" . $isbn;
  if (!isset($data[$key])) return null;

  $info = $data[$key];

  $autores = [];
  if (isset($info['authors'])) {
    foreach ($info['authors'] as $a) {
      if (!empty($a['name'])) $autores[] = $a['name'];
    }
  }

  $subjects = [];
  if (isset($info['subjects'])) {
    foreach ($info['subjects'] as $s) {
      if (!empty($s['name'])) $subjects[] = $s['name'];
    }
  }

  $ano = null;
  if (!empty($info['publish_date']) && preg_match('/\b(\d{4})\b/', (string)$info['publish_date'], $m)) {
    $ano = $m[1];
  }

  return [
    'titulo'    => $info['title'] ?? null,
    'autor'     => !empty($autores) ? implode(", ", $autores) : null,
    'ano'       => $ano,
    'editora'   => isset($info['publishers'][0]['name']) ? $info['publishers'][0]['name'] : null,
    'categoria' => !empty($subjects) ? $subjects[0] : null,
    'assuntos'  => !empty($subjects) ? implode(", ", array_slice($subjects, 0, 12)) : null,
    'sinopse'   => null,
    'fonte'     => 'OpenLibrary'
  ];
}

/* =========================
   CSV helpers
========================= */
function detectarSeparadorDoCSV(string $filePath): string {
  $h = @fopen($filePath, 'r');
  if (!$h) return ';';
  $linha = fgets($h);
  fclose($h);
  if ($linha === false) return ';';
  return (substr_count($linha, ';') > substr_count($linha, ',')) ? ';' : ',';
}

function normalizarColuna(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = ['ã'=>'a','á'=>'a','à'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
  $s = strtr($s, $map);
  $s = str_replace(["\t"," ","-","_",".","/","\\","(",")","[","]"], "", $s);
  return $s;
}

function encontrarIndice(array $cabecalho, array $palavras_chave) {
  $cab = array_map('normalizarColuna', $cabecalho);
  foreach ($palavras_chave as $p) {
    $p = normalizarColuna((string)$p);
    foreach ($cab as $idx => $col) {
      if ($col !== '' && strpos($col, $p) !== false) return $idx;
    }
  }
  return false;
}

function soDigitos($v): string {
  return preg_replace('/\D+/', '', (string)$v);
}

function pareceISBN($v): bool {
  $d = soDigitos($v);
  return (strlen($d) === 10 || strlen($d) === 13);
}

function extrairAno($v): ?string {
  $v = trim((string)$v);
  if ($v === '') return null;
  if (!preg_match('/\b(\d{4})\b/', $v, $m)) return null;
  $ano = (int)$m[1];
  $anoAtual = (int)date('Y');
  if ($ano < 1500 || $ano > $anoAtual + 1) return null;
  return (string)$ano;
}

/* =========================
   Importação
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['arquivo']['tmp_name'])) {
  $caminho = $_FILES['arquivo']['tmp_name'];
  $delim = detectarSeparadorDoCSV($caminho);

  $handle = fopen($caminho, 'r');
  if ($handle === false) {
    flash_set('danger', 'Não foi possível abrir o CSV.');
    header('Location: importar_livros.php');
    exit;
  }

  $cabecalho = fgetcsv($handle, 0, $delim);
  if (!$cabecalho) {
    fclose($handle);
    flash_set('danger', 'CSV inválido: não encontrei cabeçalho.');
    header('Location: importar_livros.php');
    exit;
  }

  // Colunas importantes
  $idx_isbn = encontrarIndice($cabecalho, ['isbn', 'isbn13', 'ean', 'codigo', 'codigobarras', 'barras']);
  $idx_qtd  = encontrarIndice($cabecalho, ['qtd', 'quant', 'quantidade', 'estoque', 'exemplares']);

  // Fallbacks se a API falhar
  $idx_titulo    = encontrarIndice($cabecalho, ['titulo', 'nome', 'title']);
  $idx_autor     = encontrarIndice($cabecalho, ['autor', 'authors', 'author']);
  $idx_ano       = encontrarIndice($cabecalho, ['ano', 'anopublicacao', 'publicacao', 'publisheddate']);
  $idx_editora   = encontrarIndice($cabecalho, ['editora', 'publisher']);
  $idx_categoria = encontrarIndice($cabecalho, ['categoria', 'genero', 'assunto', 'category']);
  $idx_assuntos  = encontrarIndice($cabecalho, ['assuntos', 'subjects']);
  $idx_sinopse   = encontrarIndice($cabecalho, ['sinopse', 'descricao', 'description']);

  if ($idx_isbn === false) {
    fclose($handle);
    flash_set('danger', 'Erro: coluna ISBN não encontrada no CSV.');
    header('Location: importar_livros.php');
    exit;
  }

  $modo = $_POST['modo'] ?? 'skip'; // update | skip
  $stats = ['novos' => 0, 'atualizados' => 0, 'ignorados' => 0];

  while (($linha = fgetcsv($handle, 0, $delim)) !== false) {
    $isbn_raw = $linha[$idx_isbn] ?? '';
    $isbn_clean = soDigitos($isbn_raw);

    $qtd = ($idx_qtd !== false && isset($linha[$idx_qtd])) ? (int)$linha[$idx_qtd] : 1;
    if ($qtd <= 0) $qtd = 1;

    if ($isbn_clean === '' || !pareceISBN($isbn_clean)) {
      $stats['ignorados']++;
      continue;
    }

    // 1) API (Google -> OpenLib)
    $dadosLivro = buscarMetadadosGoogle($isbn_clean);
    if (!$dadosLivro) $dadosLivro = buscarMetadadosOpenLib($isbn_clean);

    // 2) Fallback CSV se API falhar
    if (!$dadosLivro) {
      $dadosLivro = [
        'titulo'    => ($idx_titulo !== false && isset($linha[$idx_titulo])) ? $linha[$idx_titulo] : 'Livro sem Título',
        'autor'     => ($idx_autor  !== false && isset($linha[$idx_autor]))  ? $linha[$idx_autor]  : 'Desconhecido',
        'ano'       => ($idx_ano    !== false && isset($linha[$idx_ano]))    ? extrairAno($linha[$idx_ano]) : null,
        'editora'   => ($idx_editora!== false && isset($linha[$idx_editora]))? $linha[$idx_editora] : null,
        'categoria' => ($idx_categoria !== false && isset($linha[$idx_categoria])) ? $linha[$idx_categoria] : null,
        'assuntos'  => ($idx_assuntos !== false && isset($linha[$idx_assuntos])) ? $linha[$idx_assuntos] : null,
        'sinopse'   => ($idx_sinopse !== false && isset($linha[$idx_sinopse])) ? $linha[$idx_sinopse] : null,
        'fonte'     => 'CSV'
      ];
    }

    // Capa sempre OpenLibrary
    $capa_url = "https://covers.openlibrary.org/b/isbn/" . $isbn_clean . "-L.jpg";

    // Normalizações e defaults
    $anoStr = extrairAno($dadosLivro['ano'] ?? null);
    $ano = (int)($anoStr ?? date('Y'));

    $titulo    = mysqli_real_escape_string($conn, trim((string)($dadosLivro['titulo'] ?? 'Sem Título')));
    $autor     = mysqli_real_escape_string($conn, trim((string)($dadosLivro['autor'] ?? 'Desconhecido')));
    $editora   = mysqli_real_escape_string($conn, trim((string)($dadosLivro['editora'] ?? '')));
    $categoria = mysqli_real_escape_string($conn, trim((string)($dadosLivro['categoria'] ?? 'Geral')));
    $assuntos  = mysqli_real_escape_string($conn, trim((string)($dadosLivro['assuntos'] ?? '')));
    $sinopse   = mysqli_real_escape_string($conn, trim((string)($dadosLivro['sinopse'] ?? '')));
    $capa      = mysqli_real_escape_string($conn, $capa_url);
    $isbn_sql  = mysqli_real_escape_string($conn, $isbn_clean);

    if ($titulo === '') $titulo = 'Sem Título';
    if ($autor === '')  $autor  = 'Desconhecido';

    // Existe?
    $check = $conn->query("SELECT id FROM livros WHERE ISBN = '$isbn_sql' LIMIT 1");

    if ($check && $check->num_rows == 0) {
      // Novo livro
      $sql = "
        INSERT INTO livros
          (titulo, autor, ano_publicacao, disponivel, ISBN, capa_url, qtd_total, qtd_disp, categoria, sinopse, assuntos, editora)
        VALUES
          ('$titulo', '$autor', '$ano', 1, '$isbn_sql', '$capa', $qtd, $qtd, '$categoria', '$sinopse', '$assuntos', '$editora')
      ";

      if ($conn->query($sql)) $stats['novos']++;
      else $stats['ignorados']++;

    } else {
      // Já existe: soma quantidade sempre
      $row = $check ? $check->fetch_assoc() : null;
      $id_livro = (int)($row['id'] ?? 0);

      if ($id_livro <= 0) {
        $stats['ignorados']++;
        continue;
      }

      $conn->query("
        UPDATE livros
        SET qtd_total = qtd_total + $qtd,
            qtd_disp  = qtd_disp  + $qtd,
            disponivel = 1
        WHERE id = $id_livro
      ");

      // Se escolher update, atualiza metadados também
      if ($modo === 'update') {
        $conn->query("
          UPDATE livros
          SET titulo = '$titulo',
              autor = '$autor',
              ano_publicacao = '$ano',
              capa_url = '$capa',
              categoria = '$categoria',
              sinopse = '$sinopse',
              assuntos = '$assuntos',
              editora = '$editora'
          WHERE id = $id_livro
        ");
      }

      $stats['atualizados']++;
    }
  }

  fclose($handle);

  flash_set('success', "Importação concluída! Novos: {$stats['novos']} | Atualizados: {$stats['atualizados']} | Ignorados: {$stats['ignorados']}");
  header('Location: importar_livros.php');
  exit;
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Livros (CSV + API)</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="mt-4">
      <div class="mb-3">
        <label class="form-label fw-bold">1. Selecione o arquivo CSV</label>
        <input type="file" name="arquivo" accept=".csv" class="form-control form-control-sm" required>
        <div class="form-text">
          Colunas recomendadas: ISBN e QTD. O resto ele tenta buscar nas APIs.
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">2. Se o ISBN já existir:</label>
        <select name="modo" class="form-select form-select-sm">
          <option value="skip">Só somar quantidade</option>
          <option value="update">Somar quantidade e atualizar dados</option>
        </select>
      </div>

      <button class="btn btn-pill btn-sm px-4" type="submit">
        <i class="bi bi-upload me-1"></i> Processar Importação
      </button>
    </form>
  </div>
</div>

<?php include("../../includes/footer.php"); ?>
