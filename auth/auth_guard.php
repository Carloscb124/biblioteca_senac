<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['auth'])) {
  header("Location: /biblioteca_senac/auth/login.php");
  exit;
}
