<?php

if (session_status() === PHP_SESSION_NONE) session_start();

$base = "/biblioteca_senac";

include_once __DIR__ . "/../includes/flash.php";
$flash = flash_get();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <title>Cadastrar - Biblioteca</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css">

</head>

<body>
  <div class="auth-wrap">

    <!-- LADO ESQUERDO -->
    <div class="auth-left">
      <div class="auth-brand">
        <div class="logo"><i class="bi bi-book"></i></div>
        <h1>Biblioteca</h1>
        <p>Sistema completo para gestão de acervo, membros e empréstimos de livros.</p>

        <div class="auth-sep"></div>

        <div class="auth-feature">
          <i class="bi bi-check2-circle"></i>
          <span>Controle total do seu acervo</span>
        </div>
      </div>
    </div>

    <!-- LADO DIREITO -->
    <div class="auth-right">
      <div class="auth-card">

        <?php if ($flash) { ?>
          <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php } ?>

        <h2 class="auth-title">Criar conta</h2>
        <div class="auth-sub">Cadastre um funcionário para acessar o sistema</div>

        <form action="cadastrar_salvar.php" method="post" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Nome</label>
            <input class="form-control" name="nome" required placeholder="Seu nome completo">
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" required placeholder="seu@email.com">
          </div>

          <div class="mb-3">
            <label class="form-label">Senha</label>
            <input class="form-control" type="password" name="senha" required placeholder="••••••••">
          </div>

          <div class="mb-3">
            <label class="form-label">Cargo</label>
            <select class="form-select" name="cargo">
              <option value="funcionario" selected>Funcionário</option>
              <option value="admin">Administrador</option>
            </select>
          </div>

          <button class="auth-btn" type="submit">
            <i class="bi bi-plus-lg"></i>
            Cadastrar-se
          </button>

          <div class="auth-links">
            Já tem conta? <a href="<?= $base ?>/auth/login.php">Entrar</a>
          </div>
        </form>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>