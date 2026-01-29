<?php
include("../conexao.php");

mysqli_query($conn,"
INSERT INTO emprestimos (id_usuario,id_livro)
VALUES ({$_POST['id_usuario']},{$_POST['id_livro']})
");

mysqli_query($conn,"
UPDATE livros SET disponivel=0 WHERE id={$_POST['id_livro']}
");

header("Location: listar.php");
