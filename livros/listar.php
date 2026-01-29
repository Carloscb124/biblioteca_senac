<?php
$titulo_pagina = "Livros";
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "SELECT * FROM livros ORDER BY id DESC");
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h2 class="m-0">Livros</h2>
  <a class="btn btn-primary" href="cadastrar.php">Novo Livro</a>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead>
        <tr>
          <th>Título</th>
          <th>Autor</th>
          <th>Ano</th>
          <th>ISBN</th>
          <th>Disponível</th>
          <th class="text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($l = mysqli_fetch_assoc($r)) { ?>
          <tr>
            <td><?= htmlspecialchars($l['titulo']) ?></td>
            <td><?= htmlspecialchars($l['autor'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['ano_publicacao'] ?? '') ?></td>
            <td><?= htmlspecialchars($l['ISBN'] ?? '') ?></td>
            <td>
              <?= ($l['disponivel'] == 1) ? "Sim" : "Não" ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="editar.php?id=<?= $l['id'] ?>">Editar</a>
              <a class="btn btn-sm btn-outline-danger" href="excluir.php?id=<?= $l['id'] ?>"
                 onclick="return confirm('Excluir este livro?')">Excluir</a>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
