<?php 

    $servidor = "localhost";
    $usuario = "senac";
    $senha = "Senac2026";
    $banco = "biblioteca_senac";

    $conn = new mysqli($servidor, $usuario, $senha, $banco);

    if ($conn->connect_error){
        
        die("Falha de conexão" .$conn->connect_error);
        
    }

?>