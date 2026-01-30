<?php
$titulo_pagina = "Histórico do Usuário";
include("../conexao.php");
include("../includes/header.php");

$usuarios = mysqli_query($conn, "SELECT id, nome FROM usuarios");
$uid = $_GET['usuario'] ?? null;
$historico = null;

if ($uid) {
  $stmt = mysqli_prepare($conn, "
    SELECT l.titulo, e.data_emprestimo, e.data_devolucao, e.devolvido
    FROM emprestimos e
    JOIN livros l ON l.id = e.id_livro
    WHERE e.id_usuario = ?
    ORDER BY e.data_emprestimo DESC
  ");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  $historico = mysqli_stmt_get_result($stmt);
}
?>

<div class="container my-4">
  <div class="page-card">
    <h2 class="page-card__title mb-3">Histórico por usuário</h2>

    <form class="mb-4">
      <select name="usuario" class="form-select" onchange="this.form.submit()">
        <option value="">Selecione um usuário</option>
        <?php while ($u = mysqli_fetch_assoc($usuarios)) { ?>
          <option value="<?= $u['id'] ?>" <?= ($uid == $u['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['nome']) ?>
          </option>
        <?php } ?>
      </select>
    </form>

    <?php if ($historico) { ?>
      <table class="table table-clean">
        <thead>
          <tr>
            <th>Livro</th>
            <th>Empréstimo</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($h = mysqli_fetch_assoc($historico)) { ?>
            <tr>
              <td><?= htmlspecialchars($h['titulo']) ?></td>
              <td><?= $h['data_emprestimo'] ?></td>
              <td>
                <?php if ($h['devolvido']) { ?>
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
