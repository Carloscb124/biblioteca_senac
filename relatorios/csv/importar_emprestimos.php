<?php
$titulo_pagina = "Importar Empréstimos (CSV)";
include_once __DIR__ . "/../../auth/auth_guard.php";
include_once __DIR__ . "/../../conexao.php";
include_once __DIR__ . "/../../includes/header.php";
include_once __DIR__ . "/../../includes/flash.php";

function detectar_delim(string $file): string {
  $h = @fopen($file, "r");
  if (!$h) return ';';
  $linha = fgets($h);
  fclose($h);
  if ($linha === false) return ';';
  return (substr_count($linha, ';') >= substr_count($linha, ',')) ? ';' : ',';
}

function norm_col(string $s): string {
  $s = trim(mb_strtolower($s, 'UTF-8'));
  $map = ['ã'=>'a','á'=>'a','à'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ü'=>'u','ç'=>'c'];
  $s = strtr($s, $map);
  $s = str_replace(["\t"," ","-","_",".","/","\\","(",")","[","]"], "", $s);
  return $s;
}

function find_idx(array $header, array $keys) {
  $h = array_map('norm_col', $header);
  foreach ($keys as $k) {
    $k = norm_col((string)$k);
    foreach ($h as $i => $col) {
      if ($col !== '' && strpos($col, $k) !== false) return $i;
    }
  }
  return false;
}

function so_digitos(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

function is_date_ymd($v): bool {
  $v = trim((string)$v);
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv']) && !empty($_FILES['csv']['tmp_name'])) {
  $arquivo = $_FILES['csv']['tmp_name'];
  $delim = detectar_delim($arquivo);

  $handle = fopen($arquivo, "r");
  if (!$handle) {
    flash_set('danger', 'Não foi possível abrir o CSV.');
    header("Location: importar_emprestimos.php");
    exit;
  }

  $cab = fgetcsv($handle, 0, $delim);
  if (!$cab) {
    fclose($handle);
    flash_set('danger', 'CSV inválido: não encontrei cabeçalho.');
    header("Location: importar_emprestimos.php");
    exit;
  }

  // Colunas principais: CPF + ISBN
  $idx_cpf  = find_idx($cab, ['cpf','cpfusuario','cpf_leitor','leitorcpf','usuario_cpf']);
  $idx_isbn = find_idx($cab, ['isbn','livro_isbn','isbn13','ean','codigo','codigobarras','barras']);

  // Datas / status
  $idx_data_emp  = find_idx($cab, ['dataemprestimo','emprestimo','data_emprestimo']);
  $idx_prev      = find_idx($cab, ['dataprevista','prevista','data_prevista','vencimento']);
  $idx_dev       = find_idx($cab, ['datadevolucao','devolucao','data_devolucao']);
  $idx_devolvido = find_idx($cab, ['devolvido','statusdevolvido']);
  $idx_perdido   = find_idx($cab, ['perdido','statusperdido']);
  $idx_cancelado = find_idx($cab, ['cancelado']);

  if ($idx_cpf === false || $idx_isbn === false) {
    fclose($handle);
    flash_set('danger', 'Erro: o CSV precisa ter colunas de CPF e ISBN.');
    header("Location: importar_emprestimos.php");
    exit;
  }

  $inseridos = 0;
  $ignorados = 0;
  $nao_encontrados = 0;

  mysqli_begin_transaction($conn);

  try {
    // prepared: achar usuario por cpf (normalizando . e - e espaços)
    $sqlUser = "
      SELECT id
      FROM usuarios
      WHERE REPLACE(REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ',''), '/', '') = ?
      LIMIT 1
    ";
    $stmtUser = mysqli_prepare($conn, $sqlUser);

    // prepared: achar livro por ISBN
    $stmtLivro = mysqli_prepare($conn, "SELECT id FROM livros WHERE ISBN = ? LIMIT 1");

    // prepared: criar emprestimo
    $stmtEmp = mysqli_prepare($conn, "
      INSERT INTO emprestimos (id_usuario, data_emprestimo, data_prevista, cancelado)
      VALUES (?, ?, ?, ?)
    ");

    // prepared: criar item
    $stmtItem = mysqli_prepare($conn, "
      INSERT INTO emprestimo_itens (emprestimo_id, id_livro, data_devolucao, devolvido, perdido, data_perdido)
      VALUES (?, ?, ?, ?, ?, ?)
    ");

    // prepared: anti-duplicação (item idêntico)
    $stmtDup = mysqli_prepare($conn, "
      SELECT ei.id
      FROM emprestimos e
      JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
      WHERE e.id_usuario = ?
        AND ei.id_livro = ?
        AND e.data_emprestimo = ?
        AND IFNULL(e.data_prevista,'') = IFNULL(?, '')
        AND IFNULL(ei.data_devolucao,'') = IFNULL(?, '')
        AND ei.devolvido = ?
        AND IFNULL(ei.perdido,0) = ?
        AND IFNULL(e.cancelado,0) = ?
      LIMIT 1
    ");

    while (($linha = fgetcsv($handle, 0, $delim)) !== false) {
      $cpf_raw  = trim((string)($linha[$idx_cpf] ?? ''));
      $isbn_raw = trim((string)($linha[$idx_isbn] ?? ''));

      $cpf  = so_digitos($cpf_raw);
      $isbn = so_digitos($isbn_raw);

      if ($cpf === '' || $isbn === '') {
        $ignorados++;
        continue;
      }

      // datas default
      $data_emp = ($idx_data_emp !== false && isset($linha[$idx_data_emp]) && is_date_ymd($linha[$idx_data_emp]))
        ? trim($linha[$idx_data_emp])
        : date('Y-m-d');

      $data_prev = ($idx_prev !== false && isset($linha[$idx_prev]) && is_date_ymd($linha[$idx_prev]))
        ? trim($linha[$idx_prev])
        : date('Y-m-d', strtotime($data_emp . ' +7 days'));

      $data_dev = ($idx_dev !== false && isset($linha[$idx_dev]) && is_date_ymd($linha[$idx_dev]))
        ? trim($linha[$idx_dev])
        : null;

      // flags
      $devolvido = 0;
      if ($idx_devolvido !== false && isset($linha[$idx_devolvido])) {
        $v = mb_strtolower(trim((string)$linha[$idx_devolvido]), 'UTF-8');
        $devolvido = ($v === 'sim' || $v === '1' || $v === 'true' || $v === 'devolvido') ? 1 : 0;
      } else {
        // se tem data_devolucao, assume devolvido
        if ($data_dev) $devolvido = 1;
      }

      $perdido = 0;
      if ($idx_perdido !== false && isset($linha[$idx_perdido])) {
        $v = mb_strtolower(trim((string)$linha[$idx_perdido]), 'UTF-8');
        $perdido = ($v === 'sim' || $v === '1' || $v === 'true' || $v === 'perdido') ? 1 : 0;
      }

      $cancelado = 0;
      if ($idx_cancelado !== false && isset($linha[$idx_cancelado])) {
        $v = mb_strtolower(trim((string)$linha[$idx_cancelado]), 'UTF-8');
        $cancelado = ($v === 'sim' || $v === '1' || $v === 'true') ? 1 : 0;
      }

      // se perdido, geralmente já “encerra”
      $data_perdido = null;
      if ($perdido === 1) {
        $data_perdido = $data_dev ?: date('Y-m-d');
        $devolvido = 1;
        if (!$data_dev) $data_dev = $data_perdido;
      }

      // usuario
      mysqli_stmt_bind_param($stmtUser, "s", $cpf);
      mysqli_stmt_execute($stmtUser);
      $resU = mysqli_stmt_get_result($stmtUser);
      $u = mysqli_fetch_assoc($resU);

      // livro
      mysqli_stmt_bind_param($stmtLivro, "s", $isbn);
      mysqli_stmt_execute($stmtLivro);
      $resL = mysqli_stmt_get_result($stmtLivro);
      $l = mysqli_fetch_assoc($resL);

      if (!$u || !$l) {
        $nao_encontrados++;
        continue;
      }

      $idU = (int)$u['id'];
      $idL = (int)$l['id'];

      // anti-duplicação
      $prevParam = $data_prev ?: null;
      mysqli_stmt_bind_param(
        $stmtDup,
        "iisssiii",
        $idU,
        $idL,
        $data_emp,
        $prevParam,
        $data_dev,
        $devolvido,
        $perdido,
        $cancelado
      );
      mysqli_stmt_execute($stmtDup);
      $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDup));
      if ($dup) {
        $ignorados++;
        continue;
      }

      // cria emprestimo (um por linha, simples e confiável)
      mysqli_stmt_bind_param($stmtEmp, "issi", $idU, $data_emp, $data_prev, $cancelado);
      if (!mysqli_stmt_execute($stmtEmp)) {
        $ignorados++;
        continue;
      }
      $empId = (int)mysqli_insert_id($conn);

      // cria item
      mysqli_stmt_bind_param($stmtItem, "iisiis", $empId, $idL, $data_dev, $devolvido, $perdido, $data_perdido);
      if (!mysqli_stmt_execute($stmtItem)) {
        $ignorados++;
        continue;
      }

      $inseridos++;
    }

    fclose($handle);
    mysqli_commit($conn);

    flash_set('success', "Importação finalizada. Inseridos: $inseridos | Ignorados: $ignorados | Não encontrados (CPF/ISBN): $nao_encontrados");
    header("Location: importar_emprestimos.php");
    exit;

  } catch (Exception $e) {
    fclose($handle);
    mysqli_rollback($conn);
    flash_set('danger', 'Erro na importação: ' . $e->getMessage());
    header("Location: importar_emprestimos.php");
    exit;
  }
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Histórico (CSV)</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="alert alert-info mt-3">
      <i class="bi bi-info-circle me-2"></i>
      O sistema valida se o <strong>CPF do leitor</strong> e o <strong>ISBN</strong> existem antes de importar.
      <br>
      Dica: exporte um CSV pelo sistema e use como modelo.
    </div>

    <form method="post" enctype="multipart/form-data" class="mt-3">
      <div class="mb-3">
        <label class="form-label fw-bold small">Arquivo de Empréstimos (CSV)</label>
        <input type="file" name="csv" class="form-control form-control-sm" accept=".csv" required>
      </div>

      <button type="submit" class="btn btn-pill btn-sm px-4">
        <i class="bi bi-upload me-1"></i> Processar Ficheiro
      </button>
    </form>
  </div>
</div>

<?php include_once __DIR__ . "/../../includes/footer.php"; ?>
