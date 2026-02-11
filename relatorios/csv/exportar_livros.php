<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

// Configura cabeçalhos para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=livros_biblioteca.csv');

$saida = fopen("php://output", "w");

// 1. RESOLVE ACENTOS: Adiciona o BOM UTF-8 para o Excel reconhecer "Título", "Acervo", etc.
fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeçalho do CSV
fputcsv($saida, [
  "ID", "Título", "Autor", "Ano", "ISBN", "Total", "Disponível", "Status", "Cod_CDD", "Categoria"
], ";");

$sql = "SELECT l.id, l.titulo, l.autor, l.ano_publicacao, l.ISBN, l.qtd_total, l.qtd_disp, l.disponivel, 
               c.codigo AS categoria_codigo, c.descricao AS categoria_descricao
        FROM livros l
        LEFT JOIN cdd c ON c.id = l.categoria
        ORDER BY l.id DESC";

$r = mysqli_query($conn, $sql);

while ($l = mysqli_fetch_assoc($r)) {
  
  // 2. RESOLVE ISBN: Adiciona uma tabulação antes do número. 
  // Isso força o Excel a tratar o campo como TEXTO e não como número científico.
  $isbn_texto = "\t" . $l["ISBN"];

  fputcsv($saida, [
    $l["id"], 
    $l["titulo"], 
    $l["autor"], 
    $l["ano_publicacao"], 
    $isbn_texto, // <--- Aqui está o truque para o ISBN não virar 9,78E+12
    $l["qtd_total"], 
    $l["qtd_disp"], 
    $l["disponivel"] ? 'Ativo' : 'Inativo',
    $l["categoria_codigo"], 
    $l["categoria_descricao"]
  ], ";");
}

fclose($saida);
exit;