<?php
$titulo_pagina = "Importar Livros (CSV)";
include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php"); // já carrega flash no seu sistema

function detectar_delimitador(string $linha): string {
  return (substr_count($linha, ';') >= substr_count($linha, ',')) ? ';' : ',';
}

function norm_col(string $s): string {
  $s = trim(mb_strtolower($s));
  $s = str_replace(['-', ' '], '_', $s);
  $s = str_replace(['ã','á','à','â','ä'], 'a', $s);
  $s = str_replace(['é','è','ê','ë'], 'e', $s);
  $s = str_replace(['í','ì','î','ï'], 'i', $s);
  $s = str_replace(['õ','ó','ò','ô','ö'], 'o', $s);
  $s = str_replace(['ú','ù','û','ü'], 'u', $s);
  $s = str_replace(['ç'], 'c', $s);
  return $s;
}

function map_header(array $headerRaw): array {
  $map = [];
  foreach ($headerRaw as $idx => $h) {
    $k = norm_col((string)$h);

    // aliases comuns
    if ($k === 'isbn' || $k === 'isbn13' || $k === 'isbn_13') $k = 'isbn';
    if ($k === 'ano' || $k === 'ano_publicacao' || $k === 'ano_de_publicacao') $k = 'ano_publicacao';
    if ($k === 'disponivel' || $k === 'disponibilidade') $k = 'disponivel';

    if ($k === 'qtd' || $k === 'quantidade' || $k === 'quantidade_total') $k = 'qtd_total';
    if ($k === 'qtd_total' || $k === 'qtd_tot') $k = 'qtd_total';
    if ($k === 'qtd_disp' || $k === 'qtd_disponivel' || $k === 'disponiveis') $k = 'qtd_disp';

    // categoria / cdd
    if ($k === 'categoria' || $k === 'categoria_id') $k = 'categoria';
    if ($k === 'categoria_codigo' || $k === 'cdd_codigo' || $k === 'codigo_cdd' || $k === 'cdd') $k = 'categoria_codigo';
    if ($k === 'categoria_descricao' || $k === 'cdd_descricao' || $k === 'descricao_cdd' || $k === 'descricao') $k = 'categoria_descricao';

    $map[$k] = $idx;
  }
  return $map;
}

function pick(array $row, array $hmap, string $key): string {
  if (!isset($hmap[$key])) return '';
  return trim((string)($row[$hmap[$key]] ?? ''));
}

function parse_cdd_code_from_text(string $s): string {
  // aceita "004 - Ciência..." ou "004"
  $s = trim($s);
  if ($s === '') return '';
  $parts = explode('-', $s, 2);
  return trim($parts[0]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $modo = $_POST['modo'] ?? 'update'; // update | skip
  if ($modo !== 'update' && $modo !== 'skip') $modo = 'update';

  if (empty($_FILES['arquivo']['tmp_name'])) {
    flash_set('danger', 'Selecione um arquivo CSV.');
    header("Location: importar_livros.php");
    exit;
  }

  $tmp = $_FILES['arquivo']['tmp_name'];
  $fh = fopen($tmp, 'r');
  if (!$fh) {
    flash_set('danger', 'Não foi possível ler o arquivo.');
    header("Location: importar_livros.php");
    exit;
  }

  $firstLine = fgets($fh);
  if ($firstLine === false) {
    fclose($fh);
    flash_set('danger', 'Arquivo vazio.');
    header("Location: importar_livros.php");
    exit;
  }

  $delim = detectar_delimitador($firstLine);
  $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine); // remove BOM
  $header = str_getcsv(trim($firstLine), $delim);
  $hmap = map_header($header);

  // obrigatórias mínimas (pra manter flexível)
  $obrig = ['titulo','autor','ano_publicacao','isbn'];
  $temTudo = true;
  foreach ($obrig as $col) {
    if (!array_key_exists($col, $hmap)) { $temTudo = false; break; }
  }

  if (!$temTudo) {
    fclose($fh);
    flash_set('danger', 'Cabeçalho inválido. Precisa ter pelo menos: titulo,autor,ano_publicacao,ISBN. (Outras: qtd_total,qtd_disp,categoria_codigo,categoria_descricao,disponivel)');
    header("Location: importar_livros.php");
    exit;
  }

  // prepareds
  $stmtFindByIsbn = mysqli_prepare($conn, "SELECT id FROM livros WHERE ISBN = ? LIMIT 1");
  $stmtFindByAlt  = mysqli_prepare($conn, "SELECT id FROM livros WHERE titulo = ? AND autor = ? AND ano_publicacao = ? LIMIT 1");

  $stmtFindCddByCodigo = mysqli_prepare($conn, "SELECT id FROM cdd WHERE codigo = ? LIMIT 1");
  $stmtInsertCdd = mysqli_prepare($conn, "INSERT INTO cdd (codigo, descricao) VALUES (?, ?)");

  $stmtInsert = mysqli_prepare($conn, "
    INSERT INTO livros (titulo, autor, ano_publicacao, ISBN, categoria, qtd_total, qtd_disp, disponivel)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
  ");

  $stmtUpdate = mysqli_prepare($conn, "
    UPDATE livros
    SET titulo = ?, autor = ?, ano_publicacao = ?, ISBN = ?, categoria = ?, qtd_total = ?, qtd_disp = ?, disponivel = ?
    WHERE id = ?
  ");

  $importados = 0;
  $atualizados = 0;
  $ignorados = 0;
  $erros = 0;

  while (($row = fgetcsv($fh, 0, $delim)) !== false) {

    // ignora linha vazia
    $joined = trim(implode('', $row));
    if ($joined === '') { continue; }

    $titulo = pick($row, $hmap, 'titulo');
    $autor  = pick($row, $hmap, 'autor');
    $anoRaw = pick($row, $hmap, 'ano_publicacao');
    $isbn   = pick($row, $hmap, 'isbn');

    $dispRaw = pick($row, $hmap, 'disponivel');

    $qtdTotalRaw = pick($row, $hmap, 'qtd_total');
    $qtdDispRaw  = pick($row, $hmap, 'qtd_disp');

    $catIdRaw = pick($row, $hmap, 'categoria');
    $catCodigoRaw = pick($row, $hmap, 'categoria_codigo');
    $catDescRaw   = pick($row, $hmap, 'categoria_descricao');

    if ($titulo === '' || $isbn === '') { $erros++; continue; }

    $ano = ($anoRaw === '' ? 0 : (int)$anoRaw);

    // quantidades
    $qtd_total = ($qtdTotalRaw === '' ? 1 : (int)$qtdTotalRaw);
    if ($qtd_total < 1) $qtd_total = 1;

    $qtd_disp = null;
    if ($qtdDispRaw !== '') {
      $qtd_disp = (int)$qtdDispRaw;
      if ($qtd_disp < 0) $qtd_disp = 0;
      if ($qtd_disp > $qtd_total) $qtd_disp = $qtd_total;
    }

    // disponivel
    $disp = null;
    if ($dispRaw !== '') {
      $disp = ((int)$dispRaw === 1) ? 1 : 0;
    }

    // categoria (id ou codigo)
    $categoria = null;

    if ($catIdRaw !== '' && is_numeric($catIdRaw) && (int)$catIdRaw > 0) {
      $categoria = (int)$catIdRaw;
    } else {
      $codigo = parse_cdd_code_from_text($catCodigoRaw);
      if ($codigo !== '') {
        mysqli_stmt_bind_param($stmtFindCddByCodigo, "s", $codigo);
        mysqli_stmt_execute($stmtFindCddByCodigo);
        $resC = mysqli_stmt_get_result($stmtFindCddByCodigo);
        $c = mysqli_fetch_assoc($resC);

        if ($c) {
          $categoria = (int)$c['id'];
        } else {
          // cria no CDD se tiver descrição
          $desc = $catDescRaw !== '' ? $catDescRaw : 'Categoria importada';
          mysqli_stmt_bind_param($stmtInsertCdd, "ss", $codigo, $desc);
          if (mysqli_stmt_execute($stmtInsertCdd)) {
            $categoria = (int)mysqli_insert_id($conn);
          }
        }
      }
    }

    // se não veio qtd_disp, define por regra simples
    if ($qtd_disp === null) {
      if ($disp !== null) {
        $qtd_disp = ($disp === 1) ? $qtd_total : 0;
      } else {
        $qtd_disp = $qtd_total;
      }
    }

    // se não veio disponivel, calcula pelo qtd_disp
    if ($disp === null) {
      $disp = ($qtd_disp > 0) ? 1 : 0;
    }

    // acha existente
    $idExistente = null;

    if ($isbn !== '') {
      mysqli_stmt_bind_param($stmtFindByIsbn, "s", $isbn);
      mysqli_stmt_execute($stmtFindByIsbn);
      $res = mysqli_stmt_get_result($stmtFindByIsbn);
      $ex = mysqli_fetch_assoc($res);
      if ($ex) $idExistente = (int)$ex['id'];
    } else {
      mysqli_stmt_bind_param($stmtFindByAlt, "ssi", $titulo, $autor, $ano);
      mysqli_stmt_execute($stmtFindByAlt);
      $res = mysqli_stmt_get_result($stmtFindByAlt);
      $ex = mysqli_fetch_assoc($res);
      if ($ex) $idExistente = (int)$ex['id'];
    }

    if ($idExistente !== null) {
      if ($modo === 'skip') {
        $ignorados++;
        continue;
      }

      mysqli_stmt_bind_param(
        $stmtUpdate,
        "ssissiiii",
        $titulo,
        $autor,
        $ano,
        $isbn,
        $categoria,
        $qtd_total,
        $qtd_disp,
        $disp,
        $idExistente
      );

      if (mysqli_stmt_execute($stmtUpdate)) $atualizados++;
      else $erros++;

      continue;
    }

    // insert
    mysqli_stmt_bind_param(
      $stmtInsert,
      "ssissiii",
      $titulo,
      $autor,
      $ano,
      $isbn,
      $categoria,
      $qtd_total,
      $qtd_disp,
      $disp
    );

    if (mysqli_stmt_execute($stmtInsert)) $importados++;
    else $erros++;
  }

  fclose($fh);

  flash_set(
    'success',
    "Importação concluída: $importados novos, $atualizados atualizados, $ignorados ignorados, $erros com erro."
  );
  header("Location: importar_livros.php");
  exit;
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Livros (CSV)</h2>
      <a class="btn btn-pill" href="index.php">
        <i class="bi bi-arrow-left"></i> Voltar
      </a>
    </div>

    <p class="text-muted mb-3">
      Aceita CSV com <strong>;</strong> ou <strong>,</strong> e colunas em qualquer ordem.<br>
      Mínimo: <code>titulo,autor,ano_publicacao,ISBN</code><br>
      Extras suportados: <code>qtd_total,qtd_disp,disponivel,categoria_codigo,categoria_descricao,categoria</code>
    </p>

    <form method="post" enctype="multipart/form-data" class="form-grid">
      <div class="mb-3">
        <label class="form-label">Arquivo CSV</label>
        <input type="file" name="arquivo" accept=".csv,text/csv" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Se o livro já existir...</label>
        <select name="modo" class="form-select">
          <option value="update" selected>Atualizar com os dados do CSV</option>
          <option value="skip">Ignorar e manter como está</option>
        </select>
        <small class="text-muted">A verificação usa ISBN. Se não tiver ISBN, usa título + autor + ano.</small>
      </div>

      <button class="btn btn-brand" type="submit">
        <i class="bi bi-upload"></i> Importar
      </button>
    </form>
  </div>
</div>

<?php include("../../includes/footer.php"); ?>
