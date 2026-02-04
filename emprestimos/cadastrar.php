<?php
$titulo_pagina = "Novo Empréstimo";
include("../conexao.php");
include("../includes/header.php");

$usuarios = mysqli_query($conn, "SELECT id, nome, email FROM usuarios ORDER BY nome ASC");
$livros = mysqli_query($conn, "
  SELECT id, titulo, autor, qtd_disp, qtd_total
  FROM livros
  WHERE qtd_disp > 0
  ORDER BY titulo ASC
");

$hoje = date('Y-m-d');
$previstaPadrao = date('Y-m-d', strtotime('+7 days'));
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Novo Empréstimo</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" class="form-grid">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Usuário</label>
          <select class="form-select" name="id_usuario" required>
            <option value="">Selecione...</option>
            <?php while ($u = mysqli_fetch_assoc($usuarios)) { ?>
              <option value="<?= (int)$u['id'] ?>">
                <?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['email']) ?>)
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Livro (disponível)</label>
          <select class="form-select" name="id_livro" required>
            <option value="">Selecione...</option>
            <?php while ($l = mysqli_fetch_assoc($livros)) { ?>
              <option value="<?= (int)$l['id'] ?>">
                <?= htmlspecialchars($l['titulo']) ?>
                <?= !empty($l['autor']) ? " - " . htmlspecialchars($l['autor']) : "" ?>
                (<?= (int)$l['qtd_disp'] ?>/<?= (int)$l['qtd_total'] ?>)
              </option>
            <?php } ?>
          </select>
          <div class="form-text">Se não aparecer livro, é porque todos os exemplares estão emprestados.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data do empréstimo</label>
          <input class="form-control" type="date" name="data_emprestimo" value="<?= $hoje ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data prevista (opcional)</label>
          <input class="form-control" type="date" name="data_prevista" value="<?= $previstaPadrao ?>">
        </div>

        <div class="col-12">
          <button class="btn btn-pill" type="submit">
            <i class="bi bi-check2"></i>
            Salvar
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
