<?php
$titulo_pagina = "Livros";
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "SELECT * FROM livros ORDER BY id DESC");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Livros</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Livro
      </a>
    </div>

    <div class="table-responsive">
      <table class="table table-clean align-middle mb-0">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th>Título</th>
            <th>Autor</th>
            <th class="col-ano">Ano</th>
            <th class="col-status">Status</th>
            <th>ISBN</th>
            <th class="text-end col-acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($l = mysqli_fetch_assoc($r)) { ?>
            <tr>
              <td class="text-muted fw-semibold">#<?= (int)$l['id'] ?></td>

              <td class="fw-semibold"><?= htmlspecialchars($l['titulo']) ?></td>

              <td><?= htmlspecialchars($l['autor'] ?? '-') ?></td>

              <td><?= htmlspecialchars($l['ano_publicacao'] ?? '-') ?></td>

              <td>
                <?php if ((int)$l['disponivel'] === 1) { ?>
                  <span class="badge-soft-ok">Disponível</span>
                <?php } else { ?>
                  <span class="badge-soft-no">Indisponível</span>
                <?php } ?>
              </td>

              <td class="text-muted small"><?= htmlspecialchars($l['ISBN'] ?? '-') ?></td>

              <td class="text-end">
                <a class="icon-btn icon-btn--edit"
                   href="editar.php?id=<?= (int)$l['id'] ?>"
                   title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>

                <a class="icon-btn icon-btn--del"
                   href="excluir.php?id=<?= (int)$l['id'] ?>"
                   onclick="return confirm('Excluir este livro?')"
                   title="Excluir">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
