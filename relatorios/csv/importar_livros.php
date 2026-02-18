<?php
/* =========================================================
   importar_livros.php
   Importa livros via CSV e busca metadados por ISBN (API).
   Suporta quantidades do CSV:
   - Total (qtd_total)
   - Disponível (qtd_disp)
   ========================================================= */

$titulo_pagina = "Importar Livros (CSV + API)";
set_time_limit(600);

include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");

$flash = flash_get();

/* =========================
   FUNÇÕES DE API
========================= */

/**
 * Faz um GET e tenta retornar JSON em array.
 * Retorna null se falhar.
 */
function http_get_json(string $url): ?array {
  $opts = [
    "http" => [
      "header"  => "User-Agent: BibliotecaSystem/1.0\r\nAccept: application/json\r\n",
      "timeout" => 12,
    ]
  ];

  $context = stream_context_create($opts);
  $raw = @file_get_contents($url, false, $context);
  if ($raw === false || $raw === "") return null;

  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

/**
 * Busca metadados no Google Books por ISBN.
 */
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

/**
 * Busca metadados na OpenLibrary por ISBN.
 */
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
   HELPERS DE CSV
========================= */

/**
 * Detecta se o separador do CSV parece ser ; ou ,
 * baseado na primeira linha.
 */
function detectarSeparadorDoCSV(string $filePath): string {
  $h = @fopen($filePath, 'r');
  if (!$h) return ';';
  $linha = fgets($h);
  fclose($h);

  if ($linha === false) return ';';
  return (substr_count($linha, ';') > substr_count($linha, ',')) ? ';' : ',';
}

/**
 * Normaliza nome de coluna pra facilitar match:
 * - lowercase
 * - remove acentos
 * - remove espaços e símbolos
 */
function normalizarColuna(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = [
    'ã'=>'a','á'=>'a','à'=>'a','â'=>'a','ä'=>'a',
    'é'=>'e','ê'=>'e','ë'=>'e',
    'í'=>'i','ï'=>'i',
    'ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
    'ú'=>'u','ü'=>'u',
    'ç'=>'c'
  ];
  $s = strtr($s, $map);
  $s = str_replace(["\t"," ","-","_",".","/","\\","(",")","[","]"], "", $s);
  return $s;
}

/**
 * Encontra o índice de uma coluna no cabeçalho por palavras chave.
 * Retorna o índice (int) ou false.
 */
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

/**
 * Remove tudo que não é número.
 */
function soDigitos($v): string {
  return preg_replace('/\D+/', '', (string)$v);
}

/**
 * ISBN básico: 10 ou 13 dígitos.
 */
function pareceISBN($v): bool {
  $d = soDigitos($v);
  return (strlen($d) === 10 || strlen($d) === 13);
}

/**
 * Extrai ano de uma string qualquer.
 */
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
   IMPORTAÇÃO
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  /* ---------- Valida upload ---------- */
  if (!isset($_FILES['arquivo']) || empty($_FILES['arquivo']['tmp_name'])) {
    flash_set('danger', 'Selecione um arquivo CSV.');
    header('Location: importar_livros.php');
    exit;
  }

  if ($_FILES['arquivo']['error'] !== 0) {
    flash_set('danger', 'Erro ao enviar o arquivo CSV.');
    header('Location: importar_livros.php');
    exit;
  }

  $caminho = $_FILES['arquivo']['tmp_name'];
  $delim = detectarSeparadorDoCSV($caminho);

  $handle = fopen($caminho, 'r');
  if ($handle === false) {
    flash_set('danger', 'Não foi possível abrir o CSV.');
    header('Location: importar_livros.php');
    exit;
  }

  /* ---------- Cabeçalho ---------- */
  $cabecalho = fgetcsv($handle, 0, $delim);
  if (!$cabecalho) {
    fclose($handle);
    flash_set('danger', 'CSV inválido: não encontrei cabeçalho.');
    header('Location: importar_livros.php');
    exit;
  }

  /* ---------- Detecta colunas ---------- */

  // ISBN e quantidade genérica
  $idx_isbn = encontrarIndice($cabecalho, ['isbn', 'isbn13', 'ean', 'codigo', 'codigobarras', 'barras']);
  $idx_qtd  = encontrarIndice($cabecalho, ['qtd', 'quant', 'quantidade', 'estoque', 'exemplares']);

  // Colunas específicas do seu CSV: Total e Disponível
  // Sua normalização remove acentos, então "Disponível" vira "disponivel".
  $idx_total = encontrarIndice($cabecalho, ['total', 'qtdtotal', 'quantidadetotal', 'exemplarestotal']);
  $idx_disp  = encontrarIndice($cabecalho, ['disponivel', 'qtd_disp', 'qtddisp', 'quantidadedisponivel', 'exemplaresdisponiveis']);

  // Fallbacks de metadados caso API falhe
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

  /* ---------- Modo de import ---------- */
  // skip: soma quantidades, não mexe nos metadados
  // update: soma quantidades e atualiza metadados
  $modo = $_POST['modo'] ?? 'skip';
  if ($modo !== 'update' && $modo !== 'skip') $modo = 'skip';

  $stats = ['novos' => 0, 'atualizados' => 0, 'ignorados' => 0];
  $apiHits = 0;

  /* ---------- Prepared Statements ---------- */
  $stmtFind = mysqli_prepare($conn, "SELECT id FROM livros WHERE ISBN = ? LIMIT 1");

  // Insert com 11 placeholders, porque "disponivel" é fixo como 1
  $stmtInsert = mysqli_prepare($conn, "
    INSERT INTO livros
      (titulo, autor, ano_publicacao, disponivel, ISBN, capa_url, qtd_total, qtd_disp, categoria, sinopse, assuntos, editora)
    VALUES
      (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?)
  ");

  // Soma quantidades separadas: total e disponível
  $stmtSomaQtd = mysqli_prepare($conn, "
    UPDATE livros
    SET qtd_total  = qtd_total + ?,
        qtd_disp   = qtd_disp  + ?,
        disponivel = 1
    WHERE id = ?
  ");

  // Atualiza metadados se modo update
  $stmtUpdateMeta = mysqli_prepare($conn, "
    UPDATE livros
    SET titulo = ?,
        autor = ?,
        ano_publicacao = ?,
        capa_url = ?,
        categoria = ?,
        sinopse = ?,
        assuntos = ?,
        editora = ?
    WHERE id = ?
  ");

  if (!$stmtFind || !$stmtInsert || !$stmtSomaQtd || !$stmtUpdateMeta) {
    fclose($handle);
    flash_set('danger', 'Erro: não consegui preparar as queries. Verifique o banco e os campos.');
    header('Location: importar_livros.php');
    exit;
  }

  /* ---------- Transação ---------- */
  mysqli_begin_transaction($conn);

  try {
    while (($linha = fgetcsv($handle, 0, $delim)) !== false) {

      /* ===== 1) ISBN ===== */
      $isbn_clean = soDigitos($linha[$idx_isbn] ?? '');

      if ($isbn_clean === '' || !pareceISBN($isbn_clean)) {
        $stats['ignorados']++;
        continue;
      }

      /* ===== 2) QUANTIDADES (Total e Disponível) =====
         Regras:
         - tenta pegar Total e Disponível
         - se não tiver, cai no "qtd" genérico
         - se só tiver total, disponível vira total
         - disponível nunca pode ser maior que total
         - nunca deixa <= 0
      */
      $qtd_total = 0;
      $qtd_disp  = 0;

      if ($idx_total !== false && isset($linha[$idx_total])) {
        $qtd_total = (int)$linha[$idx_total];
      }
      if ($idx_disp !== false && isset($linha[$idx_disp])) {
        $qtd_disp = (int)$linha[$idx_disp];
      }

      // Fallback: se não achou "Total", tenta a coluna "qtd" genérica
      if ($qtd_total <= 0 && $idx_qtd !== false && isset($linha[$idx_qtd])) {
        $qtd_total = (int)$linha[$idx_qtd];
      }

      if ($qtd_total <= 0) $qtd_total = 1;

      // Se disponível vier vazio, assume igual ao total
      if ($qtd_disp <= 0) $qtd_disp = $qtd_total;

      // Nunca deixa disponível maior que total
      if ($qtd_disp > $qtd_total) $qtd_disp = $qtd_total;

      /* ===== 3) METADADOS (API -> fallback CSV) ===== */
      $dadosLivro = buscarMetadadosGoogle($isbn_clean);
      if (!$dadosLivro) $dadosLivro = buscarMetadadosOpenLib($isbn_clean);
      if ($dadosLivro && ($dadosLivro['fonte'] ?? '') !== 'CSV') $apiHits++;

      // Se API falhar, usa o que vier no CSV
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

      // Capa usando OpenLibrary
      $capa_url = "https://covers.openlibrary.org/b/isbn/" . $isbn_clean . "-L.jpg";

      // Normalizações e defaults
      $anoStr = extrairAno($dadosLivro['ano'] ?? null);
      $ano = (int)($anoStr ?? date('Y'));

      $titulo    = trim((string)($dadosLivro['titulo'] ?? 'Sem Título'));
      $autor     = trim((string)($dadosLivro['autor'] ?? 'Desconhecido'));
      $editora   = trim((string)($dadosLivro['editora'] ?? ''));
      $categoria = trim((string)($dadosLivro['categoria'] ?? 'Geral'));
      $assuntos  = trim((string)($dadosLivro['assuntos'] ?? ''));
      $sinopse   = trim((string)($dadosLivro['sinopse'] ?? ''));

      if ($titulo === '') $titulo = 'Sem Título';
      if ($autor === '')  $autor  = 'Desconhecido';

      /* ===== 4) Verifica se já existe ===== */
      mysqli_stmt_bind_param($stmtFind, "s", $isbn_clean);
      mysqli_stmt_execute($stmtFind);
      $res = mysqli_stmt_get_result($stmtFind);
      $row = $res ? mysqli_fetch_assoc($res) : null;

      if (!$row) {
        /* ===== 5A) INSERT (novo) =====
           IMPORTANTÍSSIMO:
           - SQL tem 11 placeholders
           - bind tem 11 variáveis
           - string de tipos tem 11 letras
           Tipos:
           s(s) = titulo, autor
           i     = ano
           s(s) = isbn, capa_url
           i(i) = qtd_total, qtd_disp
           s(s,s,s) = categoria, sinopse, assuntos, editora
        */
        mysqli_stmt_bind_param(
          $stmtInsert,
          "ssissiissss", // 11 tipos
          $titulo,
          $autor,
          $ano,
          $isbn_clean,
          $capa_url,
          $qtd_total,
          $qtd_disp,
          $categoria,
          $sinopse,
          $assuntos,
          $editora
        );

        if (mysqli_stmt_execute($stmtInsert)) $stats['novos']++;
        else $stats['ignorados']++;

      } else {
        /* ===== 5B) UPDATE (já existe) ===== */
        $id_livro = (int)$row['id'];

        // Soma quantidades separadas
        mysqli_stmt_bind_param($stmtSomaQtd, "iii", $qtd_total, $qtd_disp, $id_livro);
        if (!mysqli_stmt_execute($stmtSomaQtd)) {
          $stats['ignorados']++;
          continue;
        }

        // Atualiza metadados se o modo for update
        if ($modo === 'update') {
          mysqli_stmt_bind_param(
            $stmtUpdateMeta,
            "ssisssssi",
            $titulo,
            $autor,
            $ano,
            $capa_url,
            $categoria,
            $sinopse,
            $assuntos,
            $editora,
            $id_livro
          );
          mysqli_stmt_execute($stmtUpdateMeta);
        }

        $stats['atualizados']++;
      }
    }

    fclose($handle);
    mysqli_commit($conn);

    flash_set(
      'success',
      "Importação concluída! Novos: {$stats['novos']} | Atualizados: {$stats['atualizados']} | Ignorados: {$stats['ignorados']} | Consultas API: {$apiHits}"
    );
    header('Location: importar_livros.php');
    exit;

  } catch (Exception $e) {
    fclose($handle);
    mysqli_rollback($conn);
    flash_set('danger', 'Erro na importação: ' . $e->getMessage());
    header('Location: importar_livros.php');
    exit;
  }
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Livros (CSV + API)</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <?php if (!empty($flash)): ?>
      <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mt-3" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="alert alert-info mt-3">
      <i class="bi bi-info-circle me-2"></i>
      Esse import pode demorar porque busca dados por ISBN na WEB.
    </div>

    <form id="formImportLivros" method="post" enctype="multipart/form-data" class="mt-4">
      <div class="mb-3">
        <label class="form-label fw-bold">1. Selecione o arquivo CSV</label>
        <input type="file" name="arquivo" accept=".csv" class="form-control form-control-sm" required>
        <div class="form-text">
          Colunas recomendadas: ISBN, Total e Disponível. O resto ele tenta buscar nas APIs.
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">2. Se o ISBN já existir:</label>
        <select name="modo" class="form-select form-select-sm">
          <option value="skip">Só somar quantidade</option>
          <option value="update">Somar quantidade e atualizar dados</option>
        </select>
      </div>

      <button id="btnImport" class="btn btn-pill btn-sm px-4" type="submit">
        <i class="bi bi-upload me-1"></i> Processar Importação
      </button>
    </form>
  </div>
</div>

<!-- OVERLAY LOADING -->
<div id="loadingOverlay" style="
  position: fixed; inset: 0;
  background: rgba(0,0,0,.35);
  display: none;
  align-items: center; justify-content: center;
  z-index: 9999;
">
  <div style="
    background: #fff;
    border-radius: 18px;
    padding: 22px 24px;
    width: min(520px, 92vw);
    box-shadow: 0 20px 50px rgba(0,0,0,.25);
    border: 1px solid rgba(0,0,0,.08);
  ">
    <div class="d-flex align-items-center gap-3">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div>
        <div class="fw-bold" style="font-size: 16px;">Importando livros…</div>
        <div class="text-muted" style="font-size: 13px;">
          Buscando dados do ISBN na API. Em CSV grande isso pode demorar um pouco.
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const form = document.getElementById("formImportLivros");
  const overlay = document.getElementById("loadingOverlay");
  const btn = document.getElementById("btnImport");

  if (!form) return;

  form.addEventListener("submit", function(){
    if (btn) btn.disabled = true;
    if (overlay) overlay.style.display = "flex";
  });
})();
</script>

<?php include("../../includes/footer.php"); ?>
