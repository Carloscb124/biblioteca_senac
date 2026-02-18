<?php
// funcionarios/listar.php
$titulo_pagina = "Funcionários";

require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");
include("../includes/header.php");

$sql = "SELECT id, nome, email, cargo, ativo
        FROM funcionarios
        ORDER BY ativo DESC, cargo ASC, nome ASC";
$res = mysqli_query($conn, $sql);

$funcs = [];
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) $funcs[] = $row;
}

$meuId = (int)($_SESSION["auth"]["id"] ?? 0);
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Funcionários</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-person-plus"></i>
        Novo funcionário
      </a>
    </div>

    <p class="text-muted mb-3">Clique na linha para ver detalhes.</p>

    <div class="table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Status</th>
              <th>Nome</th>
              <th>Email</th>
              <th style="width:180px;">Cargo</th>
              <th class="text-end" style="width:240px;">Ações</th>
            </tr>
          </thead>

          <tbody>
          <?php if (empty($funcs)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Nenhum funcionário cadastrado.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($funcs as $f): ?>
              <?php
                $id = (int)$f["id"];
                $ativo = ((int)$f["ativo"] === 1);

                $cargo = strtoupper(trim($f["cargo"] ?? "BIBLIOTECARIO"));
                if ($cargo !== "ADMIN" && $cargo !== "BIBLIOTECARIO") $cargo = "BIBLIOTECARIO";
                $cargoLabel = ($cargo === "ADMIN") ? "ADMIN" : "BIBLIOTECÁRIO";

                $statusClass = $ativo ? "badge rounded-pill text-bg-success" : "badge rounded-pill text-bg-danger";
                $statusText  = $ativo ? "Ativo" : "Desativado";

                $isMe = ($id === $meuId);
              ?>

              <tr class="tr-click js-row" data-func-id="<?= $id ?>" title="Clique para ver detalhes">
                <td><span class="<?= $statusClass ?> px-3 py-2"><?= $statusText ?></span></td>

                <td class="fw-semibold">
                  <?= htmlspecialchars($f["nome"]) ?>
                  <?php if ($isMe): ?>
                    <span class="text-muted small ms-2">(você)</span>
                  <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($f["email"]) ?></td>

                <td>
                  <?php if ($cargo === "ADMIN"): ?>
                    <span class="badge rounded-pill text-bg-dark px-3 py-2"><?= $cargoLabel ?></span>
                  <?php else: ?>
                    <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= $cargoLabel ?></span>
                  <?php endif; ?>
                </td>

                <td class="text-end">

                  <!-- EDITAR (ícone quadradinho) -->
                  <a class="icon-btn icon-btn--edit"
                     href="editar.php?id=<?= $id ?>"
                     title="Editar"
                     data-stop-row="1">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <?php if (!$isMe): ?>

                    <!-- RESETAR SENHA (ícone quadradinho amarelo) -->
                    <a class="icon-btn icon-btn--warn"
                       href="resetar_senha.php?id=<?= $id ?>"
                       title="Resetar senha"
                       data-stop-row="1">
                      <i class="bi bi-key"></i>
                    </a>

                    <!-- ATIVAR / DESATIVAR (ícone quadradinho) -->
                    <form action="toggle_ativo.php" method="post" class="d-inline" data-stop-row="1">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="novo" value="<?= $ativo ? 0 : 1 ?>">

                      <button type="submit"
                              class="icon-btn <?= $ativo ? "icon-btn--del" : "icon-btn--ok" ?>"
                              title="<?= $ativo ? "Desativar" : "Ativar" ?>">
                        <i class="bi <?= $ativo ? "bi-person-x" : "bi-person-check" ?>"></i>
                      </button>
                    </form>

                  <?php else: ?>
                    <span class="text-muted ms-2">Você</span>
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

<!-- MODAL -->
<div class="modal fade" id="modalFuncionario" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-card-list me-1"></i> Detalhes do funcionário</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body" id="modalFuncionarioBody">
        <div class="text-muted">Carregando…</div>
      </div>

      <div class="modal-footer" id="modalFuncionarioFooter">
        <button type="button" class="btn btn-pill btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<?php include("../includes/footer.php"); ?>

<!-- garante bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const modalEl  = document.getElementById("modalFuncionario");
  const bodyEl   = document.getElementById("modalFuncionarioBody");
  const footerEl = document.getElementById("modalFuncionarioFooter");

  if (!window.bootstrap || !bootstrap.Modal) {
    console.error("Bootstrap JS não carregou. Modal não vai abrir.");
    return;
  }

  const modal = new bootstrap.Modal(modalEl);

  // clicar em botões não dispara clique da linha
  document.addEventListener("click", (e) => {
    if (e.target.closest('[data-stop-row="1"]')) e.stopPropagation();
  }, true);

  async function abrir(id){
    bodyEl.innerHTML = `<div class="text-muted">Carregando…</div>`;
    footerEl.innerHTML = `<button type="button" class="btn btn-pill btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>`;
    modal.show();

    try{
      const resp = await fetch("./detalhes.php?id=" + encodeURIComponent(id), {
        headers: { "Accept": "application/json" }
      });
      const data = await resp.json();

      if (!data.ok) {
        bodyEl.innerHTML = `<div class="alert alert-warning" style="border-radius:16px;">${data.msg || "Não foi possível carregar."}</div>`;
        return;
      }

      bodyEl.innerHTML = data.html;
      footerEl.innerHTML = data.footer || footerEl.innerHTML;

    } catch (err) {
      bodyEl.innerHTML = `<div class="alert alert-danger" style="border-radius:16px;">Erro ao carregar detalhes.</div>`;
    }
  }

  document.querySelectorAll(".js-row").forEach(tr => {
    tr.addEventListener("click", () => {
      const id = tr.getAttribute("data-func-id");
      if (id) abrir(id);
    });
  });
})();
</script>
