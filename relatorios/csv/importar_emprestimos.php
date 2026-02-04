<?php
$titulo_pagina = "Importar Empréstimos (CSV)";
include_once __DIR__ . "/../../auth/auth_guard.php";
include_once __DIR__ . "/../../conexao.php";
include_once __DIR__ . "/../../includes/header.php";

/**
 * Converte data do CSV (YYYY-MM-DD) ou "-" ou vazio => null
 */
function csv_date_or_null(string $v): ?string {
  $v = trim($v);
  if ($v === "" || $v === "-") return null;
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
  return $v;
}

/**
 * Atualiza a disponibilidade do livro com base em empréstimos abertos.
 * Se existir qualquer empréstimo devolvido=0 para o livro => disponivel=0, senão => 1
 */
function atualizar_disponibilidade_livro(mysqli $conn, int $idLivro): void {
  $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM emprestimos WHERE id_livro = ? AND devolvido = 0");
  mysqli_stmt_bind_param($stmt, "i", $idLivro);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_assoc($res);
  $abertos = (int)($row['c'] ?? 0);

  $novoDisp = ($abertos > 0) ? 0 : 1;
  $stmtUp = mysqli_prepare($conn, "UPDATE livros SET disponivel = ? WHERE id = ?");
  mysqli_stmt_bind_param($stmtUp, "ii", $novoDisp, $idLivro);
  mysqli_stmt_execute($stmtUp);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // modo: pular duplicados ou atualizar
  $modo = $_POST['modo'] ?? 'pular';
  if ($modo !== 'pular' && $modo !== 'atualizar') $modo = 'pular';

  if (!isset($_FILES["csv"]) || $_FILES["csv"]["error"] !== 0) {
    flash_set("danger", "Erro ao enviar o arquivo.");
    header("Location: importar_emprestimos.php");
    exit;
  }

  $arquivo = $_FILES["csv"]["tmp_name"];

  if (($handle = fopen($arquivo, "r")) === false) {
    flash_set("danger", "Não foi possível ler o arquivo CSV.");
    header("Location: importar_emprestimos.php");
    exit;
  }

  $linha = 0;
  $inseridos = 0;
  $atualizados = 0;
  $pulados = 0;
  $erros = [];

  while (($dados = fgetcsv($handle, 0, ";")) !== false) {
    $linha++;

    // pula cabeçalho
    if ($linha === 1) continue;

    $usuario_email  = trim($dados[0] ?? "");
    $livro_isbn     = trim($dados[1] ?? "");
    $data_emp       = csv_date_or_null((string)($dados[2] ?? ""));
    $data_prev      = csv_date_or_null((string)($dados[3] ?? ""));
    $data_dev       = csv_date_or_null((string)($dados[4] ?? ""));
    $devolvido      = (int)($dados[5] ?? 0);

    if ($usuario_email === "" || $livro_isbn === "") {
      $erros[] = "Linha $linha: faltou usuario_email ou livro_isbn.";
      continue;
    }

    if ($data_emp === null) {
      $data_emp = date("Y-m-d"); // se não vier, usa hoje
    }

    $devolvido = ($devolvido === 1) ? 1 : 0;

    // acha usuário pelo email
    $stmtU = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtU, "s", $usuario_email);
    mysqli_stmt_execute($stmtU);
    $resU = mysqli_stmt_get_result($stmtU);
    $u = mysqli_fetch_assoc($resU);

    if (!$u) {
      $erros[] = "Linha $linha: usuário não encontrado ($usuario_email).";
      continue;
    }

    // acha livro pelo ISBN
    $stmtL = mysqli_prepare($conn, "SELECT id FROM livros WHERE ISBN = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtL, "s", $livro_isbn);
    mysqli_stmt_execute($stmtL);
    $resL = mysqli_stmt_get_result($stmtL);
    $l = mysqli_fetch_assoc($resL);

    if (!$l) {
      $erros[] = "Linha $linha: livro não encontrado (ISBN $livro_isbn).";
      continue;
    }

    $idUsuario = (int)$u["id"];
    $idLivro   = (int)$l["id"];

    // verifica se já existe (chave: id_usuario + id_livro + data_emprestimo)
    $stmtE = mysqli_prepare($conn, "
      SELECT id
      FROM emprestimos
      WHERE id_usuario = ? AND id_livro = ? AND data_emprestimo = ?
      LIMIT 1
    ");
    mysqli_stmt_bind_param($stmtE, "iis", $idUsuario, $idLivro, $data_emp);
    mysqli_stmt_execute($stmtE);
    $resE = mysqli_stmt_get_result($stmtE);
    $existente = mysqli_fetch_assoc($resE);

    if ($existente) {
      $idEmp = (int)$existente['id'];

      if ($modo === 'atualizar') {
        // atualiza campos do empréstimo existente
        $stmtUpEmp = mysqli_prepare($conn, "
          UPDATE emprestimos
          SET data_prevista = ?, data_devolucao = ?, devolvido = ?
          WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmtUpEmp, "ssii", $data_prev, $data_dev, $devolvido, $idEmp);

        if (!mysqli_stmt_execute($stmtUpEmp)) {
          $erros[] = "Linha $linha: erro ao atualizar empréstimo (#$idEmp).";
          continue;
        }

        atualizar_disponibilidade_livro($conn, $idLivro);
        $atualizados++;
      } else {
        $pulados++;
      }

      continue;
    }

    // insere empréstimo novo
    $stmtI = mysqli_prepare($conn, "
      INSERT INTO emprestimos (id_usuario, id_livro, data_emprestimo, data_prevista, data_devolucao, devolvido)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmtI, "iisssi", $idUsuario, $idLivro, $data_emp, $data_prev, $data_dev, $devolvido);

    if (!mysqli_stmt_execute($stmtI)) {
      $erros[] = "Linha $linha: erro ao inserir empréstimo.";
      continue;
    }

    atualizar_disponibilidade_livro($conn, $idLivro);
    $inseridos++;
  }

  fclose($handle);

  // mensagem final
  $msgBase = "Importação concluída! Inseridos: $inseridos | Atualizados: $atualizados | Pulados: $pulados.";

  if (!empty($erros)) {
    $preview = array_slice($erros, 0, 6);
    $msg = $msgBase . " Avisos: " . implode(" | ", $preview);
    if (count($erros) > 6) $msg .= " | ...";
    flash_set("warning", $msg);
  } else {
    flash_set("success", $msgBase);
  }

  header("Location: index.php");
  exit;
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Empréstimos (CSV)</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="alert alert-info">
      <div class="fw-bold mb-1">Formato esperado (separador “;”):</div>
      <code>usuario_email;livro_isbn;data_emprestimo;data_prevista;data_devolucao;devolvido</code>
      <div class="small mt-2 text-muted">
        Datas no formato <b>YYYY-MM-DD</b>. <b>devolvido</b> = 1 ou 0.
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-12 col-md-7">
          <label class="form-label fw-bold">Arquivo CSV</label>
          <input type="file" name="csv" class="form-control" accept=".csv" required>
        </div>

        <div class="col-12 col-md-5">
          <label class="form-label fw-bold">Modo de importação</label>
          <select name="modo" class="form-select" required>
            <option value="pular">Se já existir: Pular</option>
            <option value="atualizar">Se já existir: Atualizar com dados do CSV</option>
          </select>
          <div class="form-text">A verificação usa: usuário + livro + data do empréstimo.</div>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-pill" type="submit">
          <i class="bi bi-upload"></i>
          Importar
        </button>
      </div>
    </form>
  </div>
</div>

<?php include_once __DIR__ . "/../../includes/footer.php"; ?>
