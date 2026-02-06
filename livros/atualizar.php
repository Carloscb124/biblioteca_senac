<?php
include("../conexao.php");
include("../includes/flash.php");

$id = (int)($_POST['id'] ?? 0);

$titulo = trim($_POST['titulo'] ?? '');
$autor  = trim($_POST['autor'] ?? '');
$anoRaw = $_POST['ano_publicacao'] ?? '';
$isbn   = trim($_POST['ISBN'] ?? '');
$qtd_total_novo = (int)($_POST['qtd_total'] ?? 1);

// categoria (CDD) vem como id no hidden
$categoria = (int)($_POST['categoria'] ?? 0);
$categoria = ($categoria > 0) ? $categoria : null;

$ano = null;
if ($anoRaw !== '' && is_numeric($anoRaw)) {
  $ano = (int)$anoRaw;
}

if ($id <= 0) {
  flash_set('danger', 'Livro inválido.');
  header("Location: listar.php");
  exit;
}

if ($titulo === '') {
  flash_set('danger', 'O título é obrigatório.');
  header("Location: editar.php?id=$id");
  exit;
}

if ($qtd_total_novo < 1) {
  flash_set('danger', 'A quantidade de exemplares deve ser no mínimo 1.');
  header("Location: editar.php?id=$id");
  exit;
}

mysqli_begin_transaction($conn);

try {
  // Pega quantidades atuais (trava a linha)
  $stmt0 = mysqli_prepare($conn, "SELECT qtd_total, qtd_disp FROM livros WHERE id = ? FOR UPDATE");
  mysqli_stmt_bind_param($stmt0, "i", $id);
  mysqli_stmt_execute($stmt0);
  $res0 = mysqli_stmt_get_result($stmt0);
  $cur = mysqli_fetch_assoc($res0);

  if (!$cur) {
    mysqli_rollback($conn);
    flash_set('danger', 'Livro não encontrado.');
    header("Location: listar.php");
    exit;
  }

  $qtd_total_atual = (int)$cur['qtd_total'];
  $qtd_disp_atual  = (int)$cur['qtd_disp'];
  $emprestados = max(0, $qtd_total_atual - $qtd_disp_atual);

  // Não deixa reduzir abaixo do que já está emprestado
  if ($qtd_total_novo < $emprestados) {
    mysqli_rollback($conn);
    flash_set('danger', "Não dá pra reduzir o total para {$qtd_total_novo}. Existem {$emprestados} exemplar(es) emprestado(s) agora.");
    header("Location: editar.php?id=$id");
    exit;
  }

  // Ajusta disponíveis
  if ($qtd_total_novo > $qtd_total_atual) {
    $delta = $qtd_total_novo - $qtd_total_atual;
    $qtd_disp_novo = $qtd_disp_atual + $delta; // novos exemplares entram disponíveis
  } else {
    $qtd_disp_novo = min($qtd_disp_atual, $qtd_total_novo);
  }

  if ($qtd_disp_novo < 0) $qtd_disp_novo = 0;
  if ($qtd_disp_novo > $qtd_total_novo) $qtd_disp_novo = $qtd_total_novo;

  $disponivel = ($qtd_disp_novo > 0) ? 1 : 0;

  $stmt = mysqli_prepare($conn, "
    UPDATE livros
    SET titulo = ?,
        autor = ?,
        ano_publicacao = ?,
        ISBN = ?,
        categoria = ?,
        qtd_total = ?,
        qtd_disp = ?,
        disponivel = ?
    WHERE id = ?
  ");

  mysqli_stmt_bind_param(
    $stmt,
    "ssisiiiii",
    $titulo,
    $autor,
    $ano,
    $isbn,
    $categoria,
    $qtd_total_novo,
    $qtd_disp_novo,
    $disponivel,
    $id
  );

  mysqli_stmt_execute($stmt);

  mysqli_commit($conn);
  flash_set('success', 'Livro atualizado com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Throwable $e) {
  mysqli_rollback($conn);
  $errno = mysqli_errno($conn);

  if ($errno === 1062) {
    flash_set('warning', 'Já existe um livro com esse ISBN.');
  } else {
    flash_set('danger', 'Erro ao atualizar livro: ' . $e->getMessage());
  }

  header("Location: editar.php?id=$id");
  exit;
}
