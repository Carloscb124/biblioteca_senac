<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// apaga tudo da sessão
$_SESSION = [];

// apaga cookie de sessão (isso é o pulo do gato)
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

// destrói a sessão no servidor
session_destroy();

// opcional: mensagem
require_once(__DIR__ . "/../includes/flash.php");
flash_set("success", "Você saiu do sistema.");

// redireciona
header("Location: /biblioteca_senac/auth/login.php");
exit;
