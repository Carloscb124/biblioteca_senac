<?php
$titulo_pagina = "Importar Livros (CSV)";
include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");

// Função para detetar se o ficheiro usa vírgula ou ponto e vírgula
function detectar_delimitador(string $linha): string
{
  return (substr_count($linha, ';') >= substr_count($linha, ',')) ? ';' : ',';
}

// Normaliza os nomes das colunas (remove acentos, espaços e põe em minúsculo)
function norm_col(string $s): string
{
  $s = trim(mb_strtolower($s));
  $s = str_replace(['-', ' '], '_', $s);
  $acentos = ['ã' => 'a', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'õ' => 'o', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c'];
  return strtr($s, $acentos);
}

// Mapeia o cabeçalho do CSV para as colunas do Banco de Dados
function map_header(array $headerRaw): array
{
  $map = [];
  foreach ($headerRaw as $idx => $h) {
    $k = norm_col((string)$h);
    // Associa nomes comuns do CSV ao campo correto do seu banco
    if (in_array($k, ['isbn', 'isbn13', 'isbn_13', 'livro_isbn'])) $k = 'isbn';
    if (in_array($k, ['titulo', 'livro_titulo', 'nome'])) $k = 'titulo';
    if (in_array($k, ['qtd', 'qtd_total', 'quantidade', 'total'])) $k = 'qtd_total';
    if (in_array($k, ['ano', 'ano_publicacao', 'publicacao'])) $k = 'ano_publicacao';
    if (in_array($k, ['autor', 'escritor'])) $k = 'autor';

    $map[$k] = $idx;
  }
  return $map;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['arquivo']['tmp_name'])) {
  $modo = $_POST['modo'] ?? 'update';
  $tmp = $_FILES['arquivo']['tmp_name'];
  $fh = fopen($tmp, 'r');

  $primeiraLinha = fgets($fh);
  $delim = detectar_delimitador($primeiraLinha);
  rewind($fh);

  $headerRaw = fgetcsv($fh, 0, $delim);
  $hmap = map_header($headerRaw);

  $novos = 0;
  $atualizados = 0;
  $ignorados = 0;

  while (($row = fgetcsv($fh, 0, $delim)) !== false) {
    // Validação de segurança para evitar o erro "Undefined array key"
    $isbn   = isset($hmap['isbn']) ? trim($row[$hmap['isbn']]) : '';
    $titulo = isset($hmap['titulo']) ? mysqli_real_escape_string($conn, $row[$hmap['titulo']]) : '';
    $autor  = isset($hmap['autor']) ? mysqli_real_escape_string($conn, $row[$hmap['autor']]) : 'Desconhecido';
    $ano    = isset($hmap['ano_publicacao']) ? (int)$row[$hmap['ano_publicacao']] : 0;

    // CORREÇÃO: Verifica se a coluna qtd_total existe no CSV, senão assume 1
    $qtd    = isset($hmap['qtd_total']) ? (int)$row[$hmap['qtd_total']] : 1;

    if (empty($isbn) || empty($titulo)) continue;

    // LÓGICA ANTI-DUPLICAÇÃO (ISBN)
    $check = mysqli_query($conn, "SELECT id FROM livros WHERE ISBN = '$isbn' LIMIT 1");

    if (mysqli_num_rows($check) > 0) {
      if ($modo === 'update') {
        // Atualiza o registo existente (incluindo a sua coluna qtd_total)
        $sql = "UPDATE livros SET titulo='$titulo', autor='$autor', ano_publicacao=$ano, qtd_total=$qtd, qtd_disp=$qtd WHERE ISBN='$isbn'";
        mysqli_query($conn, $sql);
        $atualizados++;
      } else {
        $ignorados++;
      }
    } else {
      // Insere novo livro
      $sql = "INSERT INTO livros (titulo, autor, ano_publicacao, ISBN, qtd_total, qtd_disp, disponivel) 
                    VALUES ('$titulo', '$autor', $ano, '$isbn', $qtd, $qtd, 1)";
      mysqli_query($conn, $sql);
      $novos++;
    }
  }
  fclose($fh);
  flash_set('success', "Importação concluída! Novos: $novos | Atualizados: $atualizados | Ignorados: $ignorados");
  header("Location: importar_livros.php");
  exit;
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Livros (CSV)</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="mt-4">
      <div class="mb-3">
        <label class="form-label fw-bold">1. Selecione o ficheiro CSV</label>
        <input type="file" name="arquivo" accept=".csv" class="form-control form-control-sm" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold">2. Ação para livros já cadastrados:</label>
        <select name="modo" class="form-select form-select-sm">
          <option value="update">Atualizar informações</option>
          <option value="skip">Ignorar</option>
        </select>
      </div>

      <button class="btn btn-pill btn-sm px-4" type="submit">
        <i class="bi bi-upload me-1"></i> Processar Importação
      </button>
    </form>
  </div>
</div>

<?php include("../../includes/footer.php"); ?>