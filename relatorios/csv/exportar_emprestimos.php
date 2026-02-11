<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

// Configura cabeçalhos para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=emprestimos.csv');

$saida = fopen("php://output", "w");

// 1. RESOLVE ACENTOS: Adiciona o BOM UTF-8 para o Excel
fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho
fputcsv($saida, [
  "id", "usuario_nome", "usuario_email", "livro_titulo", "livro_isbn", "data_emprestimo", "data_prevista", "data_devolucao", "devolvido"
], ";");

$sql = "
SELECT
  e.id, u.nome AS usuario_nome, u.email AS usuario_email, l.titulo AS livro_titulo, l.ISBN AS livro_isbn,
  e.data_emprestimo, e.data_prevista, e.data_devolucao, e.devolvido
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN livros l ON l.id = e.id_livro
ORDER BY e.id DESC
";

$r = mysqli_query($conn, $sql);

while ($e = mysqli_fetch_assoc($r)) {
  // 2. RESOLVE ISBN: Adiciona uma aspa simples (') no início para o Excel manter como texto
  $isbn_formatado = "\t" . $e["livro_isbn"]; 

  fputcsv($saida, [
    $e["id"],
    $e["usuario_nome"],
    $e["usuario_email"],
    $e["livro_titulo"],
    $isbn_formatado, // Agora o Excel não converterá para 9,78E+12
    $e["data_emprestimo"],
    $e["data_prevista"],
    $e["data_devolucao"],
    $e["devolvido"] ? 'Sim' : 'Não',
  ], ";");
}

fclose($saida);
exit;