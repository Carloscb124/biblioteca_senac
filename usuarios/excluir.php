<?php
include("../conexao.php");
mysqli_query($conn,"DELETE FROM usuarios WHERE id=".$_GET['id']);
header("Location: listar.php");
?>