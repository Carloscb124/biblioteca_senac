<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=emprestimos.csv');

$saida = fopen("php://output", "w");

// cabeçalho
fputcsv($saida, [
  "id",
  "usuario_nome",
  "usuario_email",
  "livro_titulo",
  "livro_isbn",
  "data_emprestimo",
  "data_prevista",
  "data_devolucao",
  "devolvido"
], ";");

$sql = "
SELECT
  e.id,
  u.nome AS usuario_nome,
  u.email AS usuario_email,
  l.titulo AS livro_titulo,
  l.ISBN AS livro_isbn,
  e.data_emprestimo,
  e.data_prevista,
  e.data_devolucao,
  e.devolvido
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN livros l ON l.id = e.id_livro
ORDER BY e.id DESC
";

$r = mysqli_query($conn, $sql);

while ($e = mysqli_fetch_assoc($r)) {
  fputcsv($saida, [
    $e["id"],
    $e["usuario_nome"],
    $e["usuario_email"],
    $e["livro_titulo"],
    $e["livro_isbn"],
    $e["data_emprestimo"],
    $e["data_prevista"],
    $e["data_devolucao"],
    $e["devolvido"],
  ], ";");
}

fclose($saida);
exit;
