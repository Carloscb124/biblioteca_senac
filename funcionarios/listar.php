<?php
// ============================================
// funcionarios/listar.php
// - Somente ADMIN acessa
// - Tabela no MESMO estilo do empréstimos:
//   .table-base-wrap + .table.table-base
// ============================================

$titulo_pagina = "Funcionários";

require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/header.php");

// Pega lista de funcionários
$sql = "SELECT id, nome, email, cargo, ativo
        FROM funcionarios
        ORDER BY ativo DESC, cargo ASC, nome ASC";
$res = mysqli_query($conn, $sql);

$funcs = [];
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $funcs[] = $row;
  }
}

// ID do usuário logado (pra mostrar 'Você' e bloquear desativar a si mesmo)
$meuId = (int)($_SESSION['auth']['id'] ?? 0);
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Funcionários</h2>

      <!-- Botão no mesmo estilo do sistema -->
      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-person-plus"></i>
        Novo bibliotecário
      </a>
    </div>

    <p class="text-muted mb-3">Somente administradores podem gerenciar funcionários.</p>

    <!-- Wrapper que dá borda arredondada + sombra (igual empréstimos) -->
    <div class="table-base-wrap">
      <div class="table-responsive">
        <!-- table-base = estilo do tables.css (igual empréstimos) -->
        <table class="table table-base table-hover align-middle mb-0">
          <thead>
            <tr>
              <th class="col-status">Status</th>
              <th>Nome</th>
              <th>Email</th>
              <th style="width: 180px;">Cargo</th>
              <th class="col-acoes text-end">Ações</th>
            </tr>
          </thead>

          <tbody>
            <?php if (empty($funcs)): ?>
              <tr>
                <td colspan="5" class="text-center text-muted py-4">
                  Nenhum funcionário cadastrado.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($funcs as $f): ?>
                <?php
                  $id = (int)$f['id'];
                  $ativo = (int)$f['ativo'] === 1;

                  // Normaliza cargo (só pra não dar ruim com valores antigos)
                  $cargo = strtoupper(trim($f['cargo'] ?? 'BIBLIOTECARIO'));
                  if ($cargo !== 'ADMIN' && $cargo !== 'BIBLIOTECARIO') $cargo = 'BIBLIOTECARIO';

                  // Labels bonitinhas (sem inventar CSS novo)
                  $cargoLabel = ($cargo === 'ADMIN') ? 'ADMIN' : 'BIBLIOTECÁRIO';

                  // Status pill usando Bootstrap (se seu sistema já tiver pill próprio, pode trocar)
                  $statusClass = $ativo
                    ? "badge rounded-pill text-bg-success"
                    : "badge rounded-pill text-bg-danger";
                  $statusText  = $ativo ? "Ativo" : "Desativado";
                ?>

                <tr>
                  <td>
                    <span class="<?= $statusClass ?> px-3 py-2"><?= $statusText ?></span>
                  </td>

                  <td class="fw-semibold">
                    <?= htmlspecialchars($f['nome']) ?>
                  </td>

                  <td>
                    <?= htmlspecialchars($f['email']) ?>
                  </td>

                  <td>
                    <?php if ($cargo === 'ADMIN'): ?>
                      <span class="badge rounded-pill text-bg-dark px-3 py-2"><?= $cargoLabel ?></span>
                    <?php else: ?>
                      <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= $cargoLabel ?></span>
                    <?php endif; ?>
                  </td>

                  <td class="text-end">
                    <?php if ($id === $meuId): ?>
                      <span class="text-muted">Você</span>
                    <?php else: ?>
                      <!-- Ativar/Desativar no mesmo esquema de botões -->
                      <form action="toggle_ativo.php" method="post" class="d-inline">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="novo" value="<?= $ativo ? 0 : 1 ?>">

                        <button type="submit"
                          class="btn btn-sm <?= $ativo ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                          <i class="bi <?= $ativo ? 'bi-person-x' : 'bi-person-check' ?>"></i>
                          <?= $ativo ? 'Desativar' : 'Ativar' ?>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>

              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include("../includes/footer.php"); ?>
