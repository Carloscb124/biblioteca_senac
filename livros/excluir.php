<?php
include("../conexao.php");

$id = (int)($_GET['id'] ?? 0);

// Se o livro estiver ligado a empréstimos, apagar pode dar erro por causa da FK.
// Então a gente tenta e, se der erro, manda uma mensagem simples.
$ok = mysqli_query($conn, "DELETE FROM livros WHERE id=$id");

if (!$ok) {
  // Se quiser, você pode melhorar essa mensagem depois.
  // Agora é só pra não dar tela branca.
  header("Location: listar.php?erro=nao_deu");
  exit;
}

header("Location: listar.php");
exit;
