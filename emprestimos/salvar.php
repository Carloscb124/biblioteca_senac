<?php
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$id_usuario = (int)($_POST['id_usuario'] ?? 0);
$data_emp   = trim($_POST['data_emprestimo'] ?? '');
$data_prev  = $_POST['data_prevista'] ?? null;

$id_livros = $_POST['id_livros'] ?? [];
if (!is_array($id_livros)) $id_livros = [];

$id_livros = array_map('intval', $id_livros);
$id_livros = array_values(array_filter($id_livros, fn($v) => $v > 0));
$id_livros = array_values(array_unique($id_livros)); // se quiser permitir pegar 2 exemplares iguais, remova isso

if ($data_prev === '') $data_prev = null;

if ($id_usuario <= 0 || $data_emp === '' || count($id_livros) < 1) {
  flash_set('danger', 'Preencha os dados e selecione pelo menos 1 livro.');
  header("Location: cadastrar.php");
  exit;
}

if (count($id_livros) > 3) {
  flash_set('danger', 'Máximo de 3 livros por empréstimo.');
  header("Location: cadastrar.php");
  exit;
}

mysqli_begin_transaction($conn);

try {
  // 1) trava livros e valida disponibilidade
  foreach ($id_livros as $id_livro) {
    $stmt = mysqli_prepare($conn, "
      SELECT id, qtd_disp, qtd_total, disponivel
      FROM livros
      WHERE id = ?
      LIMIT 1 FOR UPDATE
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
    $livro = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$livro) throw new Exception("Livro não encontrado (ID $id_livro).");

    // dispo­nivel = 0 aqui significa desativado manualmente (admin), não “sem estoque”
    if (isset($livro['disponivel']) && (int)$livro['disponivel'] !== 1) {
      throw new Exception("Um dos livros está desativado.");
    }

    // estoque
    if ((int)$livro['qtd_disp'] <= 0) {
      throw new Exception("Um dos livros está sem exemplares disponíveis.");
    }
  }

  // 2) cria cabeçalho
  $stmt = mysqli_prepare($conn, "
    INSERT INTO emprestimos (id_usuario, data_emprestimo, data_prevista)
    VALUES (?, ?, ?)
  ");
  $dp = $data_prev; // pode ser null
  mysqli_stmt_bind_param($stmt, "iss", $id_usuario, $data_emp, $dp);
  mysqli_stmt_execute($stmt);

  $emprestimo_id = (int)mysqli_insert_id($conn);
  if ($emprestimo_id <= 0) throw new Exception("Falha ao criar o empréstimo.");

  // 3) cria itens + baixa estoque (SEM desativar o livro)
  foreach ($id_livros as $id_livro) {

    $stmt = mysqli_prepare($conn, "
      INSERT INTO emprestimo_itens (emprestimo_id, id_livro, data_devolucao, devolvido)
      VALUES (?, ?, NULL, 0)
    ");
    mysqli_stmt_bind_param($stmt, "ii", $emprestimo_id, $id_livro);
    mysqli_stmt_execute($stmt);

    // baixa somente o estoque disponível
    $stmt = mysqli_prepare($conn, "
      UPDATE livros
      SET qtd_disp = GREATEST(qtd_disp - 1, 0)
      WHERE id = ?
      LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
  }

  mysqli_commit($conn);
  flash_set('success', 'Empréstimo registrado com sucesso!');
  header("Location: listar.php");
  exit;

} catch (Exception $e) {
  mysqli_rollback($conn);
  flash_set('danger', $e->getMessage());
  header("Location: cadastrar.php");
  exit;
}
