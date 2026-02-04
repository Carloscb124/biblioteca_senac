<?php
$titulo_pagina = "Leitores";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$r = mysqli_query($conn, "SELECT id, nome, cpf, email, telefone, ativo FROM usuarios ORDER BY id DESC");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Leitores</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Leitor
      </a>
    </div>

    <div class="table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">

          <thead>
            <tr>
              <th class="col-id">ID</th>
              <th>Nome</th>
              <th>CPF</th>
              <th>Email</th>
              <th>Telefone</th>
              <th>Status</th>
              <th class="text-end col-acoes">Ações</th>
            </tr>
          </thead>

          <tbody>
            <?php while ($u = mysqli_fetch_assoc($r)) {
              $id = (int)$u['id'];
              $ativo = ((int)$u['ativo'] === 1);

              $emailTxt = !empty($u['email']) ? htmlspecialchars($u['email']) : "<span class='text-muted'>-</span>";
              $telTxt   = !empty($u['telefone']) ? htmlspecialchars($u['telefone']) : "<span class='text-muted'>-</span>";
            ?>
              <tr>
                <td class="text-muted fw-semibold">#<?= $id ?></td>
                <td class="fw-semibold"><?= htmlspecialchars($u['nome']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($u['cpf']) ?></td>
                <td><?= $emailTxt ?></td>
                <td><?= $telTxt ?></td>

                <td>
                  <?= $ativo
                    ? "<span class='badge-soft-ok'>Ativo</span>"
                    : "<span class='badge-soft-no'>Desativado</span>"
                  ?>
                </td>

                <td class="text-end">
                  <!-- EDITAR sempre -->
                  <a class="icon-btn icon-btn--edit"
                    href="editar.php?id=<?= $id ?>"
                    title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <?php if ($ativo) { ?>
                    <!-- DESATIVAR -->
                    <a class="icon-btn icon-btn--del"
                      href="excluir.php?id=<?= $id ?>"
                      onclick="return confirm('Desativar este leitor? Ele não será apagado, apenas desativado.')"
                      title="Desativar">
                      <i class="bi bi-person-x"></i>
                    </a>
                  <?php } else { ?>
                    <!-- REATIVAR -->
                    <a class="icon-btn icon-btn--ok"
                      href="reativar.php?id=<?= $id ?>"
                      onclick="return confirm('Reativar este leitor?')"
                      title="Reativar">
                      <i class="bi bi-person-check"></i>
                    </a>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
          </tbody>

        </table>
      </div>
    </div>
  </div>

<?php include("../includes/footer.php"); ?>
