<?php
// auth_guard.php
// - Mantém sessão
// - Centraliza regras de permissão (ADMIN / BIBLIOTECARIO)
// - NÃO depende do front-end; o bloqueio é no PHP (backend)

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

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
 * (Se tiver valores antigos tipo "funcionario", converte pra BIBLIOTECARIO)
 */
function auth_role(): string {
  $cargo = strtoupper(trim($_SESSION['auth']['cargo'] ?? ''));

  // compatibilidade com valores antigos
  if ($cargo === 'FUNCIONARIO' || $cargo === 'FUNCIONÁ RIO' || $cargo === 'FUNCIONÁRIO') {
    $cargo = 'BIBLIOTECARIO';
  }
  if ($cargo === 'BIBLIOTECARIO' || $cargo === 'ADMIN') return $cargo;

  // qualquer coisa fora do padrão vira bibliotecário por segurança
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
 * Permite ADMIN e BIBLIOTECARIO (qualquer um que opere o sistema)
 */
function require_staff() {
  require_login();

  // aqui já garante que auth_role() retorna algo válido
  $role = auth_role();
  if ($role !== 'ADMIN' && $role !== 'BIBLIOTECARIO') {
    http_response_code(403);
    echo "Acesso negado.";
    exit;
  }
}

/**
 * Bloqueia se não for ADMIN
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
 * BLOQUEIO para ações de exclusão:
 * Bibliotecário NÃO pode excluir
 */
function require_can_delete() {
  require_admin(); // excluir = só ADMIN
}
