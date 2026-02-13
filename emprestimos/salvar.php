<?php
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/flash.php");

$id_usuario = (int)($_POST['id_usuario'] ?? 0);
$data_emp   = $_POST['data_emprestimo'] ?? '';
$data_prev  = $_POST['data_prevista'] ?? null;

$id_livros = $_POST['id_livros'] ?? [];
if (!is_array($id_livros)) $id_livros = [];

$id_livros = array_map('intval', $id_livros);
$id_livros = array_values(array_filter($id_livros, fn($v) => $v > 0));
$id_livros = array_values(array_unique($id_livros));

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
      SELECT qtd_disp, disponivel
      FROM livros
      WHERE id = ?
      FOR UPDATE
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $livro = mysqli_fetch_assoc($res);

    if (!$livro) throw new Exception("Livro não encontrado (ID $id_livro).");

    // se sua tabela não tiver 'disponivel', comenta essa linha
    if (isset($livro['disponivel']) && (int)$livro['disponivel'] !== 1) {
      throw new Exception("Um dos livros está desativado.");
    }

    if ((int)$livro['qtd_disp'] <= 0) throw new Exception("Um dos livros está sem exemplares disponíveis.");
  }

  // 2) cria cabeçalho
  $stmt = mysqli_prepare($conn, "
    INSERT INTO emprestimos (id_usuario, data_emprestimo, data_prevista)
    VALUES (?, ?, ?)
  ");
  mysqli_stmt_bind_param($stmt, "iss", $id_usuario, $data_emp, $data_prev);
  mysqli_stmt_execute($stmt);

  $emprestimo_id = (int)mysqli_insert_id($conn);
  if ($emprestimo_id <= 0) throw new Exception("Falha ao criar o empréstimo.");

  // 3) cria itens + baixa estoque
  foreach ($id_livros as $id_livro) {

    $stmt = mysqli_prepare($conn, "
      INSERT INTO emprestimo_itens (emprestimo_id, id_livro, data_devolucao, devolvido)
      VALUES (?, ?, NULL, 0)
    ");
    mysqli_stmt_bind_param($stmt, "ii", $emprestimo_id, $id_livro);
    mysqli_stmt_execute($stmt);

    // baixa estoque
    $stmt = mysqli_prepare($conn, "
      UPDATE livros
      SET qtd_disp = GREATEST(qtd_disp - 1, 0)
      WHERE id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_livro);
    mysqli_stmt_execute($stmt);

    // opcional: manter coluna 'disponivel' coerente
    mysqli_query($conn, "
      UPDATE livros
      SET disponivel = CASE WHEN qtd_disp > 0 THEN 1 ELSE 0 END
      WHERE id = $id_livro
    ");
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
