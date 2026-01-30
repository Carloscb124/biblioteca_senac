<?php
$titulo_pagina = "Editar Usuário";
include("../conexao.php");
include("../includes/header.php");

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT * FROM usuarios WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$u = mysqli_fetch_assoc($res);

if (!$u) { ?>
  <div class="container my-4">
    <div class="alert alert-danger mb-0">Usuário não encontrado.</div>
  </div>
  <?php include("../includes/footer.php"); exit; ?>
<?php } ?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Editar Usuário</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="atualizar.php" method="post" class="form-grid" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required
                 value="<?= htmlspecialchars($u['nome']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" required
                 value="<?= htmlspecialchars($u['email']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Perfil</label>
          <select class="form-select" name="perfil">
            <option value="admin" <?= (($u['perfil'] ?? '') === 'admin') ? "selected" : "" ?>>Administrador</option>
            <option value="leitor" <?= (($u['perfil'] ?? '') === 'leitor') ? "selected" : "" ?>>Leitor</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Nova senha (opcional)</label>
          <input class="form-control" type="password" name="senha"
                 placeholder="Deixe em branco para manter a senha atual">
          <div class="form-text">Se não preencher, a senha atual continua a mesma.</div>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-brand" type="submit">
          <i class="bi bi-check2"></i>
          Atualizar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php include("../includes/footer.php"); ?>
