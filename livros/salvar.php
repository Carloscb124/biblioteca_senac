<?php
include("../conexao.php");
include("../includes/flash.php");

$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbn   = trim($_POST['ISBN'] ?? '');

// quantos exemplares está adicionando
$qtd_add = (int)($_POST['qtd_total'] ?? 1);

// categoria (CDD) vem como id no hidden
$categoria = (int)($_POST['categoria'] ?? 0);
$categoria = ($categoria > 0) ? $categoria : null;

$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) {
  $ano = (int)$anoRaw;
}

if ($titulo === '') {
  flash_set('danger', 'O título é obrigatório.');
  header("Location: cadastrar.php");
  exit;
}

if ($isbn === '') {
  flash_set('danger', 'O ISBN é obrigatório.');
  header("Location: cadastrar.php");
  exit;
}

if ($qtd_add < 1) {
  flash_set('danger', 'A quantidade de exemplares deve ser no mínimo 1.');
  header("Location: cadastrar.php");
  exit;
}

mysqli_begin_transaction($conn);

try {
  // Se já existir o ISBN, soma exemplares e atualiza dados
  $stmt = mysqli_prepare($conn, "
    INSERT INTO livros (titulo, autor, ano_publicacao, ISBN, categoria, qtd_total, qtd_disp, disponivel)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
      titulo = VALUES(titulo),
      autor  = VALUES(autor),
      ano_publicacao = VALUES(ano_publicacao),
      categoria = VALUES(categoria),
      qtd_total = qtd_total + VALUES(qtd_total),
      qtd_disp  = qtd_disp  + VALUES(qtd_disp),
      disponivel = 1
  ");

  // ao cadastrar: qtd_total = qtd_add e qtd_disp = qtd_add
  mysqli_stmt_bind_param(
    $stmt,
    "ssissii",
    $titulo,
    $autor,
    $ano,
    $isbn,
    $categoria,
    $qtd_add,
    $qtd_add
  );

  mysqli_stmt_execute($stmt);
  mysqli_commit($conn);

  flash_set('success', 'Exemplares adicionados com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  flash_set('danger', 'Erro ao salvar: ' . $e->getMessage());
  header("Location: cadastrar.php");
  exit;
}
?>