<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// carrega as funções flash_set e flash_get
require_once(__DIR__ . "/flash.php");

if (!isset($titulo_pagina)) $titulo_pagina = "Biblioteca";
$base = "/biblioteca_senac";
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Detecta admin
$isAdmin = !empty($_SESSION['auth']) && strtoupper(trim($_SESSION['auth']['cargo'] ?? '')) === 'ADMIN';

// ===== PEGA APENAS O PRIMEIRO NOME =====
$nomeCompleto = $_SESSION['auth']['nome'] ?? 'Usuário';
$nomeCompleto = trim($nomeCompleto);
$primeiroNome = explode(' ', $nomeCompleto)[0];
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

  <nav class="navbar navbar-expand-lg header-clean">
    <div class="container">

      <a class="header-brand" href="<?= $base ?>/index.php">
        <span class="brand-icon">
          <i class="bi bi-book"></i>
        </span>
        <span class="brand-text">
          <strong>Biblioteca</strong>
          <span class="brand-sep">—</span>
          <span class="brand-sub">Sistema de Gestão</span>
        </span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="menu">
        <ul class="navbar-nav mx-auto header-menu">

          <li class="nav-item">
            <a class="nav-link <?= ($path === $base . '/index.php' || $path === $base . '/') ? 'active' : '' ?>"
              href="<?= $base ?>/index.php">
              <i class="bi bi-house"></i> Início
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link <?= (strpos($path, $base . '/livros') !== false) ? 'active' : '' ?>"
              href="<?= $base ?>/livros/listar.php">
              <i class="bi bi-book"></i> Acervo
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link <?= (strpos($path, $base . '/usuarios') !== false) ? 'active' : '' ?>"
              href="<?= $base ?>/usuarios/listar.php">
              <i class="bi bi-person"></i> Leitores
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link <?= (strpos($path, $base . '/emprestimos') !== false) ? 'active' : '' ?>"
              href="<?= $base ?>/emprestimos/listar.php">
              <i class="bi bi-arrow-repeat"></i> Empréstimos
            </a>
          </li>

          <li class="nav-item">
            <a class="nav-link <?= (strpos($path, $base . '/relatorios') !== false) ? 'active' : '' ?>"
              href="<?= $base ?>/relatorios/index.php">
              <i class="bi bi-graph-up"></i> Relatórios
            </a>
          </li>

          <?php if ($isAdmin): ?>
            <li class="nav-item">
              <a class="nav-link <?= (strpos($path, $base . '/funcionarios') !== false) ? 'active' : '' ?>"
                href="<?= $base ?>/funcionarios/listar.php">
                <i class="bi bi-person-badge"></i> Funcionários
              </a>
            </li>
          <?php endif; ?>

        </ul>

        <?php if (!empty($_SESSION['auth'])): ?>
          <div class="header-user">
            <div class="user-chip">
              <span class="user-dot"></span>
              <span class="user-name"><?= htmlspecialchars($primeiroNome) ?></span>
            </div>

            <a href="<?= $base ?>/auth/sair.php" class="logout-btn">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sair</span>
            </a>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </nav>

  <main class="app-main">

    <?php
    if (function_exists('flash_get')) {
      $flash = flash_get();
      if ($flash) {
        $type = $flash['type'] ?? 'info';
        $msg  = $flash['message'] ?? '';

        $allowed = ['success', 'danger', 'warning', 'info'];
        if (!in_array($type, $allowed, true)) $type = 'info';
    ?>
        <div class="container mt-3">
          <div class="alert alert-<?= htmlspecialchars($type) ?> alert-dismissible fade show flash-msg" role="alert">
            <?= nl2br(htmlspecialchars($msg)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
          </div>
        </div>
    <?php
      }
    }
    ?>