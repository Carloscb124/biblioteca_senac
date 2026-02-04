<?php
$titulo_pagina = "Importar Empréstimos (CSV)";
include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");


function csv_date_or_null(string $v): ?string {
  $v = trim($v);
  if ($v === "" || $v === "-" ) return null;
  // espera YYYY-MM-DD
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
  return $v;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!isset($_FILES["csv"]) || $_FILES["csv"]["error"] !== 0) {
    flash_set("danger", "Erro ao enviar o arquivo.");
    header("Location: importar_emprestimos.php");
    exit;
  }

  $arquivo = $_FILES["csv"]["tmp_name"];

  if (($handle = fopen($arquivo, "r")) !== false) {

    $linha = 0;
    $inseridos = 0;
    $erros = [];

    while (($dados = fgetcsv($handle, 0, ";")) !== false) {
      $linha++;

      if ($linha === 1) continue; // cabeçalho

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
      $stmtL = mysqli_prepare($conn, "SELECT id, disponivel FROM livros WHERE ISBN = ? LIMIT 1");
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

      if ($data_emp === null) {
        // se não vier data, usa hoje
        $data_emp = date("Y-m-d");
      }

      $devolvido = ($devolvido === 1) ? 1 : 0;

      // insere empréstimo
      $stmtI = mysqli_prepare($conn, "
        INSERT INTO emprestimos (id_usuario, id_livro, data_emprestimo, data_prevista, data_devolucao, devolvido)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      mysqli_stmt_bind_param($stmtI, "iisssi", $idUsuario, $idLivro, $data_emp, $data_prev, $data_dev, $devolvido);

      if (!mysqli_stmt_execute($stmtI)) {
        $erros[] = "Linha $linha: erro ao inserir empréstimo.";
        continue;
      }

      // regra simples: se devolvido = 0, marca livro indisponível. Se devolvido = 1, marca disponível.
      $novoDisp = ($devolvido === 1) ? 1 : 0;
      $stmtUp = mysqli_prepare($conn, "UPDATE livros SET disponivel = ? WHERE id = ?");
      mysqli_stmt_bind_param($stmtUp, "ii", $novoDisp, $idLivro);
      mysqli_stmt_execute($stmtUp);

      $inseridos++;
    }

    fclose($handle);

    if (!empty($erros)) {
      // Mostra os primeiros pra não virar textão infinito
      $preview = array_slice($erros, 0, 6);
      $msg = "Importação concluída com avisos. Inseridos: $inseridos. Problemas: " . implode(" | ", $preview);
      if (count($erros) > 6) $msg .= " | ...";
      flash_set("warning", $msg);
    } else {
      flash_set("success", "Importação concluída! Inseridos: $inseridos.");
    }

    header("Location: index.php");
    exit;
  }

  flash_set("danger", "Não foi possível ler o arquivo CSV.");
  header("Location: importar_emprestimos.php");
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
      <div class="mb-3">
        <label class="form-label fw-bold">Arquivo CSV</label>
        <input type="file" name="csv" class="form-control" accept=".csv" required>
      </div>

      <button class="btn btn-pill" type="submit">
        <i class="bi bi-upload"></i>
        Importar
      </button>
    </form>
  </div>
</div>

<?php include("../../includes/footer.php"); ?>
