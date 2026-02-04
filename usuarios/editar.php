<?php
$titulo_pagina = "Editar Leitor";
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
    <div class="alert alert-danger mb-0">Leitor não encontrado.</div>
  </div>
  <?php include("../includes/footer.php"); exit; ?>
<?php } ?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Editar Leitor</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="atualizar.php" method="post" class="form-grid" autocomplete="off" id="formLeitorEdit">
      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">

      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required value="<?= htmlspecialchars($u['nome']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">CPF (obrigatório)</label>
          <input class="form-control" name="cpf" id="cpf" required maxlength="14"
                 value="<?= htmlspecialchars($u['cpf'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Email (opcional)</label>
          <input class="form-control" type="email" name="email" id="email"
                 value="<?= htmlspecialchars($u['email'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Telefone (opcional)</label>
          <input class="form-control" name="telefone" id="telefone"
                 value="<?= htmlspecialchars($u['telefone'] ?? '') ?>">
        </div>

        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="semContato">
            <label class="form-check-label" for="semContato">
              Leitor não tem email nem telefone
            </label>
          </div>
          <div class="form-text">Marcando isso, email e telefone ficam desativados.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select class="form-select" name="ativo">
            <option value="1" <?= ((int)$u['ativo'] === 1) ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= ((int)$u['ativo'] === 0) ? 'selected' : '' ?>>Desativado</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Perfil</label>
          <input class="form-control" value="Leitor" disabled>
        </div>

      </div>

      <div class="form-actions">
        <button class="btn btn-pill" type="submit">
          <i class="bi bi-check2"></i>
          Atualizar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  const cpf = document.getElementById('cpf');
  const email = document.getElementById('email');
  const tel = document.getElementById('telefone');
  const semContato = document.getElementById('semContato');

  function onlyDigits(s){ return (s || '').replace(/\D/g, ''); }

  // máscara simples 000.000.000-00
  function maskCpf() {
    let d = onlyDigits(cpf.value).slice(0, 11);
    let out = d;
    if (d.length > 3) out = d.slice(0,3) + '.' + d.slice(3);
    if (d.length > 6) out = out.slice(0,7) + '.' + d.slice(6);
    if (d.length > 9) out = out.slice(0,11) + '-' + d.slice(9);
    cpf.value = out;
  }
  cpf.addEventListener('input', maskCpf);
  maskCpf();

  function toggleContato() {
    const on = semContato.checked;
    email.disabled = on;
    tel.disabled = on;
    if (on) { email.value = ''; tel.value = ''; }
  }

  // auto marca semContato se já vier sem email e sem telefone
  semContato.checked = (email.value.trim() === '' && tel.value.trim() === '');
  semContato.addEventListener('change', toggleContato);
  toggleContato();
</script>

<?php include("../includes/footer.php"); ?>
