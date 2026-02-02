<?php
// Tela de login (sem navbar)
if (session_status() === PHP_SESSION_NONE) session_start();

$base = "/biblioteca_senac";

include_once __DIR__ . "/../includes/flash.php";
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Acessar Sistema - Biblioteca</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/auth.css">


</head>

<body>
  <div class="auth-wrap">

    <!-- LADO ESQUERDO (marca) -->
    <div class="auth-left">
      <div class="auth-brand">
        <div class="logo"><i class="bi bi-book"></i></div>
        <h1>Biblioteca</h1>
        <p>Sistema completo para gestão de acervo, membros e empréstimos de livros.</p>

        <div class="auth-sep"></div>

        <div class="auth-feature">
          <i class="bi bi-bookmark-check"></i>
          <span>Controle total do seu acervo</span>
        </div>
      </div>
    </div>

    <!-- LADO DIREITO (form) -->
    <div class="auth-right">
      <div class="auth-card">

        <?php if ($flash) { ?>
          <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php } ?>

        <h2 class="auth-title">Acessar Sistema</h2>
        <div class="auth-sub">Entre com suas credenciais</div>

        <form action="entrar.php" method="post" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required placeholder="seu@email.com">
          </div>

          <div class="mb-3">
            <label class="form-label">Senha</label>
            <input class="form-control" type="password" name="senha" required placeholder="••••••••">
          </div>

          <button class="auth-btn" type="submit">Entrar</button>

          <div class="auth-sep"></div>

          <div class="auth-links">
            Ainda não tem conta? <a href="<?= $base ?>/auth/cadastrar.php">Cadastre-se</a>
          </div>
        </form>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
