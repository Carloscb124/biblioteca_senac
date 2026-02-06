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
              $nomeTxt  = htmlspecialchars($u['nome']);
            ?>
              <tr>
                <td class="text-muted fw-semibold">#<?= $id ?></td>
                <td class="fw-semibold"><?= $nomeTxt ?></td>
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
                  <!-- EDITAR -->
                  <a class="icon-btn icon-btn--edit"
                    href="editar.php?id=<?= $id ?>"
                    title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <?php if ($ativo) { ?>
                    <!-- DESATIVAR (abre modal) -->
                    <button
                      type="button"
                      class="icon-btn icon-btn--del"
                      title="Desativar"
                      data-bs-toggle="modal"
                      data-bs-target="#modalDesativar"
                      data-id="<?= $id ?>"
                      data-nome="<?= $nomeTxt ?>">
                      <i class="bi bi-person-x"></i>
                    </button>
                  <?php } else { ?>
                    <!-- REATIVAR (abre modal) -->
                    <button
                      type="button"
                      class="icon-btn icon-btn--ok"
                      title="Reativar"
                      data-bs-toggle="modal"
                      data-bs-target="#modalReativar"
                      data-id="<?= $id ?>"
                      data-nome="<?= $nomeTxt ?>">
                      <i class="bi bi-person-check"></i>
                    </button>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
          </tbody>

        </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL DESATIVAR -->
<div class="modal fade" id="modalDesativar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-person-x me-2"></i>Desativar leitor
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Você está prestes a desativar:</p>
        <p class="fw-semibold mb-2" id="desativarNome">-</p>
        <div class="alert alert-warning mb-0">
          Ele não será apagado. Apenas ficará desativado no sistema.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-danger" id="desativarLink">
          <i class="bi bi-person-x me-1"></i>Desativar
        </a>
      </div>
    </div>
  </div>
</div>

<!-- MODAL REATIVAR -->
<div class="modal fade" id="modalReativar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-person-check me-2"></i>Reativar leitor
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Você está prestes a reativar:</p>
        <p class="fw-semibold mb-2" id="reativarNome">-</p>
        <div class="alert alert-success mb-0">
          O leitor voltará a aparecer como ativo no sistema.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-success" id="reativarLink">
          <i class="bi bi-check2 me-1"></i>Reativar
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  // Desativar
  const modalDesativar = document.getElementById('modalDesativar');
  modalDesativar?.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const nome = btn.getAttribute('data-nome');

    document.getElementById('desativarNome').textContent = nome;
    document.getElementById('desativarLink').href = `excluir.php?id=${encodeURIComponent(id)}`;
  });

  // Reativar
  const modalReativar = document.getElementById('modalReativar');
  modalReativar?.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-id');
    const nome = btn.getAttribute('data-nome');

    document.getElementById('reativarNome').textContent = nome;
    document.getElementById('reativarLink').href = `reativar.php?id=${encodeURIComponent(id)}`;
  });
</script>

<?php include("../includes/footer.php"); ?>
