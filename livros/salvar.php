<?php
include("../conexao.php");

$titulo = mysqli_real_escape_string($conn, $_POST['titulo']);
$autor  = mysqli_real_escape_string($conn, $_POST['autor'] ?? '');
$ano    = isset($_POST['ano_publicacao']) && $_POST['ano_publicacao'] !== '' ? (int)$_POST['ano_publicacao'] : "NULL";
$isbn   = mysqli_real_escape_string($conn, $_POST['ISBN'] ?? '');

// ano_publicacao pode ser NULL, então montamos a query com carinho
$sql = "INSERT INTO livros (titulo, autor, ano_publicacao, ISBN)
        VALUES ('$titulo', " . ($autor === '' ? "NULL" : "'$autor'") . ", $ano, " . ($isbn === '' ? "NULL" : "'$isbn'") . ")";

mysqli_query($conn, $sql);

header("Location: listar.php");
exit;
