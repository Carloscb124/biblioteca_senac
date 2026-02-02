<?php
include("../conexao.php");
include("../includes/flash.php");

// Pega e limpa dados do POST
$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$cargo = $_POST['cargo'] ?? 'funcionario';

// Guarda dados (exceto senha) para repopular o form em caso de erro
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['old_auth'] = [
  'nome' => $nome,
  'email' => $email,
  'cargo' => $cargo
];

// Validações
if ($nome === '' || $email === '' || $senha === '') {
  flash_set('danger', 'Preencha nome, email e senha.');
  header("Location: cadastrar.php");
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  flash_set('danger', 'Email inválido.');
  header("Location: cadastrar.php");
  exit;
}

if (strlen($senha) < 6) {
  flash_set('danger', 'A senha deve ter pelo menos 6 caracteres.');
  header("Location: cadastrar.php");
  exit;
}

/**
 * Segurança:
 * Não deixe qualquer pessoa escolher "admin" no cadastro público.
 * Aqui forçamos sempre para "funcionario".
 * Depois, você promove um funcionário para admin direto no banco ou em tela restrita.
 */
$cargo = 'funcionario';

// Hash da senha
$hash = password_hash($senha, PASSWORD_DEFAULT);

// INSERT seguro
$stmt = mysqli_prepare($conn, "
  INSERT INTO funcionarios (nome, email, senha, cargo, ativo)
  VALUES (?, ?, ?, ?, 1)
");
mysqli_stmt_bind_param($stmt, "ssss", $nome, $email, $hash, $cargo);

if (mysqli_stmt_execute($stmt)) {
  unset($_SESSION['old_auth']);
  flash_set('success', 'Cadastro realizado! Agora você pode entrar.');
  header("Location: login.php");
  exit;
}

// Tratamento de erro (email duplicado geralmente é 1062)
$errno = mysqli_errno($conn);
if ($errno === 1062) {
  flash_set('warning', 'Esse email já está cadastrado.');
} else {
  flash_set('danger', 'Erro ao cadastrar. Tente novamente.');
}

header("Location: cadastrar.php");
exit;
