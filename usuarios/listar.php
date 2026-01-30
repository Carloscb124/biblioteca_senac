<?php
$titulo_pagina = "Usuários";
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "SELECT * FROM usuarios ORDER BY id DESC");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Usuários</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Usuário
      </a>
    </div>

    <div class="table-responsive">
      <table class="table table-clean align-middle mb-0">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th class="col-status">Perfil</th>
            <th class="text-end col-acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($u = mysqli_fetch_assoc($r)) { ?>
            <tr>
              <td class="text-muted fw-semibold">#<?= (int)$u['id'] ?></td>

              <td class="fw-semibold">
                <?= htmlspecialchars($u['nome']) ?>
              </td>

              <td class="text-muted">
                <?= htmlspecialchars($u['email']) ?>
              </td>

              <td>
                <?php if ($u['perfil'] === 'admin') { ?>
                  <span class="badge-soft-ok">Admin</span>
                <?php } else { ?>
                  <span class="badge-soft-no">Leitor</span>
                <?php } ?>
              </td>

              <td class="text-end">
                <a class="icon-btn icon-btn--edit"
                   href="editar.php?id=<?= (int)$u['id'] ?>"
                   title="Editar">
                  <i class="bi bi-pencil"></i>
                </a>

                <a class="icon-btn icon-btn--del"
                   href="excluir.php?id=<?= (int)$u['id'] ?>"
                   onclick="return confirm('Excluir este usuário?')"
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
