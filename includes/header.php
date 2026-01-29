<?php
if (!isset($titulo_pagina)) $titulo_pagina = "Biblioteca";
$base = "/biblioteca";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title><?= $titulo_pagina ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= $base ?>/index.php">
      <img src="<?= $base ?>/assets/book.png" style="height:32px">
      Biblioteca
    </a>

    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto gap-2">
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/usuarios/listar.php">Usuários</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/livros/listar.php">Livros</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/emprestimos/listar.php">Empréstimos</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="container my-4">
