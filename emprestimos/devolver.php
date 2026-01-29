<?php
include("../conexao.php");

$id = $_GET['id'];

$e = mysqli_fetch_assoc(
  mysqli_query($conn,"SELECT id_livro FROM emprestimos WHERE id=$id")
);

mysqli_query($conn,"
UPDATE emprestimos SET devolvido=1,data_devolucao=CURDATE() WHERE id=$id
");

mysqli_query($conn,"
UPDATE livros SET disponivel=1 WHERE id={$e['id_livro']}
");

header("Location: listar.php");
