<?php
include("../conexao.php");

$id     = (int)($_POST['id'] ?? 0);
$titulo = mysqli_real_escape_string($conn, $_POST['titulo']);
$autor  = mysqli_real_escape_string($conn, $_POST['autor'] ?? '');
$ano    = isset($_POST['ano_publicacao']) && $_POST['ano_publicacao'] !== '' ? (int)$_POST['ano_publicacao'] : "NULL";
$isbn   = mysqli_real_escape_string($conn, $_POST['ISBN'] ?? '');
$disp   = isset($_POST['disponivel']) ? (int)$_POST['disponivel'] : 1;

$sql = "UPDATE livros SET
          titulo='$titulo',
          autor=" . ($autor === '' ? "NULL" : "'$autor'") . ",
          ano_publicacao=$ano,
          ISBN=" . ($isbn === '' ? "NULL" : "'$isbn'") . ",
          disponivel=$disp
        WHERE id=$id";

mysqli_query($conn, $sql);

header("Location: listar.php");
exit;
