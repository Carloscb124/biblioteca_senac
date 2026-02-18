<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

// Cabeçalhos para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=emprestimos.csv');

$saida = fopen("php://output", "w");

// BOM UTF-8 (Excel)
fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho CSV
fputcsv($saida, [
  "emprestimo_id",
  "item_id",
  "usuario_nome",
  "usuario_cpf",
  "usuario_email",
  "livro_titulo",
  "livro_isbn",
  "data_emprestimo",
  "data_prevista",
  "data_devolucao",
  "devolvido",
  "perdido",
  "cancelado"
], ";");

$sql = "
SELECT
  e.id AS emprestimo_id,
  ei.id AS item_id,
  u.nome AS usuario_nome,
  u.cpf  AS usuario_cpf,
  u.email AS usuario_email,
  l.titulo AS livro_titulo,
  l.ISBN AS livro_isbn,
  e.data_emprestimo,
  e.data_prevista,
  ei.data_devolucao,
  ei.devolvido,
  ei.perdido,
  e.cancelado
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
JOIN livros l ON l.id = ei.id_livro
ORDER BY e.id DESC, ei.id DESC
";

$r = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($r)) {
  // Excel não quebrar ISBN em 9,78E+12
  $isbn_formatado = "\t" . ($row["livro_isbn"] ?? "");

  fputcsv($saida, [
    $row["emprestimo_id"],
    $row["item_id"],
    $row["usuario_nome"],
    $row["usuario_cpf"],
    $row["usuario_email"],
    $row["livro_titulo"],
    $isbn_formatado,
    $row["data_emprestimo"],
    $row["data_prevista"],
    $row["data_devolucao"],
    ((int)$row["devolvido"] === 1) ? "Sim" : "Não",
    ((int)$row["perdido"] === 1) ? "Sim" : "Não",
    ((int)$row["cancelado"] === 1) ? "Sim" : "Não",
  ], ";");
}

fclose($saida);
exit;
