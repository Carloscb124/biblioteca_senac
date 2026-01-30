<?php
include("../conexao.php");

$senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

mysqli_query($conn,"
INSERT INTO usuarios (nome,email,senha,perfil)
VALUES ('{$_POST['nome']}','{$_POST['email']}','$senha','{$_POST['perfil']}')
");

header("Location: listar.php");
?>