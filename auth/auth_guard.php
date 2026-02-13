<?php
// auth_guard.php
// - Mantém sessão
// - Centraliza regras de permissão (ADMIN / BIBLIOTECARIO)
// - Bloqueia acesso automaticamente
// - Impede cache de páginas protegidas

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// impede cache de páginas após logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$base = "/biblioteca_senac";

/**
 * Garante que o usuário está logado
 */
function require_login() {
  global $base;

  if (empty($_SESSION['auth'])) {
    header("Location: {$base}/auth/login.php");
    exit;
  }
}

/**
 * Retorna o cargo/role atual, normalizado.
 */
function auth_role(): string {
  $cargo = strtoupper(trim($_SESSION['auth']['cargo'] ?? ''));

  // compatibilidade com valores antigos
  if ($cargo === 'FUNCIONARIO' || $cargo === 'FUNCIONÁ RIO' || $cargo === 'FUNCIONÁRIO') {
    $cargo = 'BIBLIOTECARIO';
  }

  if ($cargo === 'ADMIN' || $cargo === 'BIBLIOTECARIO') {
    return $cargo;
  }

  // fallback seguro
  return 'BIBLIOTECARIO';
}

/**
 * Helpers
 */
function is_admin(): bool {
  return auth_role() === 'ADMIN';
}

function is_bibliotecario(): bool {
  return auth_role() === 'BIBLIOTECARIO';
}

/**
 * Permite ADMIN e BIBLIOTECARIO
 */
function require_staff() {
  require_login();

  $role = auth_role();
  if ($role !== 'ADMIN' && $role !== 'BIBLIOTECARIO') {
    http_response_code(403);
    echo "Acesso negado.";
    exit;
  }
}

/**
 * Somente ADMIN
 */
function require_admin() {
  require_login();

  if (!is_admin()) {
    http_response_code(403);
    echo "Acesso negado. Somente administrador.";
    exit;
  }
}

/**
 * Bloqueio para exclusão
 */
function require_can_delete() {
  require_admin();
}

/* =========================
   BLOQUEIO AUTOMÁTICO
   Qualquer página que incluir
   este arquivo já exige login
========================= */
require_staff();
