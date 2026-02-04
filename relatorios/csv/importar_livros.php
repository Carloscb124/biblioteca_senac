<?php
$titulo_pagina = "Importar Livros (CSV)";
include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");


if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (!isset($_FILES["csv"]) || $_FILES["csv"]["error"] !== 0) {
    flash_set("danger", "Erro ao enviar o arquivo.");
    header("Location: importar_livros.php");
    exit;
  }

  $arquivo = $_FILES["csv"]["tmp_name"];

  if (($handle = fopen($arquivo, "r")) !== false) {

    $linha = 0;
    $inseridos = 0;
    $atualizados = 0;

    while (($dados = fgetcsv($handle, 0, ";")) !== false) {
      $linha++;

      if ($linha === 1) continue; // pula cabeçalho

      $titulo = trim($dados[0] ?? "");
      $autor  = trim($dados[1] ?? "");
      $ano    = (int)($dados[2] ?? 0);
      $isbn   = trim($dados[3] ?? "");
      $disp   = (int)($dados[4] ?? 1);

      if ($titulo === "") continue;

      // Se tiver ISBN, tenta atualizar por ISBN. Se não tiver, insere.
      if ($isbn !== "") {
        $stmt = mysqli_prepare($conn, "SELECT id FROM livros WHERE ISBN = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $isbn);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $existe = mysqli_fetch_assoc($res);

        if ($existe) {
          $stmt2 = mysqli_prepare($conn, "UPDATE livros SET titulo=?, autor=?, ano_publicacao=?, disponivel=? WHERE id=?");
          $idLivro = (int)$existe["id"];
          mysqli_stmt_bind_param($stmt2, "ssiii", $titulo, $autor, $ano, $disp, $idLivro);
          mysqli_stmt_execute($stmt2);
          $atualizados++;
          continue;
        }
      }

      $stmt3 = mysqli_prepare($conn, "INSERT INTO livros (titulo, autor, ano_publicacao, ISBN, disponivel) VALUES (?, ?, ?, ?, ?)");
      mysqli_stmt_bind_param($stmt3, "ssisi", $titulo, $autor, $ano, $isbn, $disp);
      if (mysqli_stmt_execute($stmt3)) $inseridos++;
    }

    fclose($handle);

    flash_set("success", "Importação concluída! Inseridos: $inseridos | Atualizados: $atualizados");
    header("Location: index.php");
    exit;
  }

  flash_set("danger", "Não foi possível ler o arquivo CSV.");
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

    <div class="alert alert-info">
      <div class="fw-bold mb-1">Formato esperado (separador “;”):</div>
      <code>titulo;autor;ano_publicacao;isbn;disponivel</code>
      <div class="small mt-2 text-muted">
        Dica: <b>disponivel</b> = 1 ou 0. Se o ISBN já existir, o livro será atualizado.
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
