<?php
$titulo_pagina = "Empréstimos por Período";
include("../conexao.php");
include("../includes/header.php");

$inicio = $_GET['inicio'] ?? '';
$fim    = $_GET['fim'] ?? '';

$resultado = null;

if ($inicio && $fim) {
  $stmt = mysqli_prepare($conn, "
    SELECT
      l.titulo,
      u.nome AS usuario,
      e.data_emprestimo,
      e.data_prevista,
      e.data_devolucao,
      e.devolvido
    FROM emprestimos e
    JOIN livros l ON l.id = e.id_livro
    JOIN usuarios u ON u.id = e.id_usuario
    WHERE e.data_emprestimo BETWEEN ? AND ?
    ORDER BY e.data_emprestimo DESC
  ");
  mysqli_stmt_bind_param($stmt, "ss", $inicio, $fim);
  mysqli_stmt_execute($stmt);
  $resultado = mysqli_stmt_get_result($stmt);
}
?>

<div class="container my-4">
  <div class="page-card">
    <h2 class="page-card__title mb-3">Empréstimos por período</h2>

    <form class="row g-3 mb-4">
      <div class="col-md-4">
        <label class="form-label">Data inicial</label>
        <input type="date" name="inicio" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Data final</label>
        <input type="date" name="fim" class="form-control" required>
      </div>
      <div class="col-md-4 align-self-end">
        <button class="btn btn-brand">Filtrar</button>
      </div>
    </form>

    <?php if ($resultado) { ?>
      <table class="table table-clean">
        <thead>
          <tr>
            <th>Livro</th>
            <th>Usuário</th>
            <th>Empréstimo</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($r = mysqli_fetch_assoc($resultado)) { ?>
            <tr>
              <td><?= htmlspecialchars($r['titulo']) ?></td>
              <td><?= htmlspecialchars($r['usuario']) ?></td>
              <td><?= $r['data_emprestimo'] ?></td>
              <td>
                <?php if ($r['devolvido']) { ?>
                  <span class="badge-status badge-done">Devolvido</span>
                <?php } else { ?>
                  <span class="badge-status badge-open">Aberto</span>
                <?php } ?>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    <?php } ?>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
