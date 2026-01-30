<?php
$titulo_pagina = "Empréstimos";
include("../conexao.php");
include("../includes/header.php");

$sql = "
SELECT
  e.id,
  e.data_emprestimo,
  e.data_devolucao,
  e.devolvido,
  u.nome AS usuario_nome,
  l.titulo AS livro_titulo
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN livros l ON l.id = e.id_livro
ORDER BY e.id DESC
";
$r = mysqli_query($conn, $sql);
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Empréstimos</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Empréstimo
      </a>
    </div>

    <div class="table-responsive">
      <table class="table table-clean align-middle mb-0">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th>Usuário</th>
            <th>Livro</th>
            <th class="col-ano">Empréstimo</th>
            <th class="col-ano">Devolução</th>
            <th class="col-status">Status</th>
            <th class="text-end col-acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($e = mysqli_fetch_assoc($r)) { ?>
            <tr>
              <td class="text-muted fw-semibold">#<?= (int)$e['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($e['usuario_nome']) ?></td>
              <td><?= htmlspecialchars($e['livro_titulo']) ?></td>
              <td><?= htmlspecialchars($e['data_emprestimo']) ?></td>
              <td><?= htmlspecialchars($e['data_devolucao'] ?? '-') ?></td>

              <td>
                <?php if ((int)$e['devolvido'] === 0) { ?>
                  <span class="badge-soft-ok">Aberto</span>
                <?php } else { ?>
                  <span class="badge-soft-no">Devolvido</span>
                <?php } ?>
              </td>

              <td class="text-end">
                <?php if ((int)$e['devolvido'] === 0) { ?>
                  <a class="icon-btn icon-btn--edit"
                     href="devolver.php?id=<?= (int)$e['id'] ?>"
                     onclick="return confirm('Confirmar devolução?')"
                     title="Devolver">
                    <i class="bi bi-check2-circle"></i>
                  </a>
                <?php } ?>

                <a class="icon-btn icon-btn--del"
                   href="excluir.php?id=<?= (int)$e['id'] ?>"
                   onclick="return confirm('Excluir este empréstimo?')"
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
