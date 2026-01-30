<?php
if (!isset($titulo_pagina)) $titulo_pagina = "Biblioteca";
$base = "/biblioteca_senac"; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($titulo_pagina) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">
</head>

<body class="app-body">

<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= $base ?>/index.php">
      <i class="bi bi-book-half brand-icon"></i>
        Biblioteca
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav ms-auto gap-lg-3">
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/index.php">Início</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/livros/listar.php">Livros</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/usuarios/listar.php">Usuários</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/emprestimos/listar.php">Empréstimos</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="app-main">
