<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$base = "/biblioteca_senac";

include("../conexao.php");
include_once __DIR__ . "/../includes/flash.php";
$flash = flash_get();

// verifica se já existe funcionário
$hasAny = false;
$res = mysqli_query($conn, "SELECT 1 FROM funcionarios LIMIT 1");
if ($res && mysqli_fetch_row($res)) $hasAny = true;

// se já existe, só ADMIN pode cadastrar
if ($hasAny) {
  include(__DIR__ . "/auth_guard.php");
  require_admin();
}

$old = $_SESSION['old_auth'] ?? ['nome'=>'','email'=>'','cargo'=>'BIBLIOTECARIO'];
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
  <link rel="stylesheet" href="<?= $base ?>/assets/css/auth.css">
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

      <?php if (!$hasAny): ?>
        <h2 class="auth-title">Criar administrador</h2>
        <div class="auth-sub">Primeiro acesso: crie o ADMIN inicial</div>
      <?php else: ?>
        <h2 class="auth-title">Cadastrar bibliotecário</h2>
        <div class="auth-sub">Somente ADMIN pode cadastrar funcionários</div>
      <?php endif; ?>

      <form action="cadastrar_salvar.php" method="post" autocomplete="off">

        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required placeholder="Nome completo"
                 value="<?= htmlspecialchars($old['nome'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required placeholder="seu@email.com"
                 value="<?= htmlspecialchars($old['email'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Senha</label>
          <input class="form-control" type="password" name="senha" required placeholder="••••••••">
          <div class="form-text">Mínimo 6 caracteres.</div>
        </div>

        <?php if (!$hasAny): ?>
          <input type="hidden" name="cargo" value="ADMIN">
        <?php else: ?>
          <input type="hidden" name="cargo" value="BIBLIOTECARIO">
          <div class="alert alert-info small">
            Será criado como <b>Bibliotecário</b>.
          </div>
        <?php endif; ?>

        <button class="auth-btn mt-2" type="submit">
          <i class="bi bi-check-lg"></i>
          Cadastrar
        </button>

        <div class="auth-links">
          <a href="login.php">Voltar para login</a>
        </div>

      </form>

    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
