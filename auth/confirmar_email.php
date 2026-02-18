<?php
// auth/confirmar_email.php
// Tela onde o usuário digita o código de 6 dígitos

if (session_status() === PHP_SESSION_NONE) session_start();

$base = "/biblioteca_senac";

include_once __DIR__ . "/../includes/flash.php";
$flash = flash_get();

$email = trim($_GET["email"] ?? "");
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Confirmar Email - Biblioteca</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/auth.css">
</head>

<body>
  <div class="auth-wrap">

    <!-- LADO ESQUERDO -->
    <div class="auth-left">
      <div class="auth-brand">
        <div class="logo"><i class="bi bi-envelope-check"></i></div>
        <h1>Confirmação</h1>
        <p>Digite o código enviado para seu email.</p>

        <div class="auth-sep"></div>

        <div class="auth-feature">
          <i class="bi bi-shield-check"></i>
          <span>Isso evita cadastros fake e bagunça no sistema</span>
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

        <h2 class="auth-title">Confirmar email</h2>
        <div class="auth-sub">
          Enviamos um código de <b>6 dígitos</b> para: <br>
          <b><?= htmlspecialchars($email) ?></b>
        </div>

        <form action="confirmar_email_salvar.php" method="post" autocomplete="off">
          <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

          <div class="mb-3 mt-3">
            <label class="form-label">Código</label>
            <input
              class="form-control"
              name="codigo"
              required
              inputmode="numeric"
              maxlength="6"
              pattern="\d{6}"
              placeholder="Ex: 123456"
            >
            <div class="form-text">O código expira em 15 minutos.</div>
          </div>

          <button class="auth-btn" type="submit">
            <i class="bi bi-check2-circle"></i>
            Confirmar
          </button>

          <div class="auth-links mt-2">
            <a href="reenviar_codigo.php?email=<?= urlencode($email) ?>">Reenviar código</a>
            <span class="mx-2">•</span>
            <a href="login.php">Voltar pro login</a>
          </div>
        </form>

      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
