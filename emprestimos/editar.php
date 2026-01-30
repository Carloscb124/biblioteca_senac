<?php
$titulo_pagina = "Editar Empréstimo";
include("../conexao.php");
include("../includes/header.php");

$id = (int)($_GET['id'] ?? 0);

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

if (!$e) { ?>
  <div class="container my-4">
    <div class="alert alert-danger mb-0">Empréstimo não encontrado.</div>
  </div>
  <?php include("../includes/footer.php"); exit; ?>
<?php }

if ((int)$e['devolvido'] === 1) { ?>
  <div class="container my-4">
    <div class="alert alert-warning mb-0">Empréstimo devolvido não pode ser editado.</div>
  </div>
  <?php include("../includes/footer.php"); exit; ?>
<?php }

$usuarios = mysqli_query($conn, "SELECT id, nome, email FROM usuarios ORDER BY nome ASC");
// livros disponíveis + o livro atual (mesmo se indisponível)
$livros = mysqli_query($conn, "
  SELECT id, titulo, autor
  FROM livros
  WHERE disponivel = 1 OR id = " . (int)$e['id_livro'] . "
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
            <?php while ($u = mysqli_fetch_assoc($usuarios)) { ?>
              <option value="<?= (int)$u['id'] ?>" <?= ((int)$e['id_usuario'] === (int)$u['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['email']) ?>)
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Livro</label>
          <select class="form-select" name="id_livro" required>
            <?php while ($l = mysqli_fetch_assoc($livros)) { ?>
              <option value="<?= (int)$l['id'] ?>" <?= ((int)$e['id_livro'] === (int)$l['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($l['titulo']) ?><?= !empty($l['autor']) ? " - " . htmlspecialchars($l['autor']) : "" ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data do empréstimo</label>
          <input class="form-control" type="date" name="data_emprestimo" required value="<?= htmlspecialchars($e['data_emprestimo']) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Data prevista</label>
          <input class="form-control" type="date" name="data_prevista" value="<?= htmlspecialchars($e['data_prevista'] ?? '') ?>">
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-brand" type="submit">
          <i class="bi bi-check2"></i>
          Atualizar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
