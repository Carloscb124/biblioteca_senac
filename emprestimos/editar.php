<?php
$titulo_pagina = "Editar Empréstimo";
include("../conexao.php");
include("../includes/header.php");
include("../includes/flash.php");

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('danger', 'Empréstimo inválido.');
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "
  SELECT e.*, u.nome AS usuario_nome, l.titulo AS livro_titulo
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN livros l ON l.id = e.id_livro
  WHERE e.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$e = mysqli_fetch_assoc($res);

if (!$e) {
  flash_set('danger', 'Empréstimo não encontrado.');
  header("Location: listar.php");
  exit;
}

if ((int)$e['devolvido'] === 1) {
  flash_set('warning', 'Empréstimo devolvido não pode ser editado.');
  header("Location: listar.php");
  exit;
}

$usuarios = mysqli_query($conn, "SELECT id, nome, email FROM usuarios ORDER BY nome ASC");

// mostra livros com exemplares + o livro atual (mesmo que esteja 0 disponível)
$livros = mysqli_query($conn, "
  SELECT id, titulo, autor, qtd_disp, qtd_total
  FROM livros
  WHERE qtd_disp > 0 OR id = " . (int)$e['id_livro'] . "
  ORDER BY titulo ASC
");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Editar Empréstimo</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="atualizar.php" method="post" class="form-grid">
      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
      <input type="hidden" name="id_livro_atual" value="<?= (int)$e['id_livro'] ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Usuário</label>
          <select class="form-select" name="id_usuario" required>
            <option value="">Selecione...</option>
            <?php while ($u = mysqli_fetch_assoc($usuarios)) { ?>
              <option value="<?= (int)$u['id'] ?>" <?= ((int)$e['id_usuario'] === (int)$u['id']) ? "selected" : "" ?>>
                <?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['email']) ?>)
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Livro</label>
          <select class="form-select" name="id_livro" required>
            <option value="">Selecione...</option>
            <?php while ($l = mysqli_fetch_assoc($livros)) { ?>
              <option value="<?= (int)$l['id'] ?>" <?= ((int)$e['id_livro'] === (int)$l['id']) ? "selected" : "" ?>>
                <?= htmlspecialchars($l['titulo']) ?>
                <?= !empty($l['autor']) ? " - " . htmlspecialchars($l['autor']) : "" ?>
                (<?= (int)$l['qtd_disp'] ?>/<?= (int)$l['qtd_total'] ?>)
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data do empréstimo</label>
          <input class="form-control" type="date" name="data_emprestimo" value="<?= htmlspecialchars($e['data_emprestimo']) ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data prevista (opcional)</label>
          <input class="form-control" type="date" name="data_prevista" value="<?= htmlspecialchars($e['data_prevista'] ?? '') ?>">
        </div>

        <div class="col-12">
          <button class="btn btn-pill" type="submit">
            <i class="bi bi-check2"></i>
            Atualizar
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
