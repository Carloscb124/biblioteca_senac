<?php
$titulo_pagina = "Cadastrar Leitor";
include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Cadastrar Leitor</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" class="form-grid" autocomplete="off" id="formLeitor">
      <div class="row g-3">

        <div class="col-md-6">
          <label class="form-label">Nome</label>
          <input class="form-control" name="nome" required placeholder="Ex: João da Silva">
        </div>

        <div class="col-md-6">
          <label class="form-label">CPF (obrigatório)</label>
          <input class="form-control" name="cpf" id="cpf" required placeholder="000.000.000-00" maxlength="14">
          <div class="form-text">Pode digitar com ou sem pontuação.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Email (opcional)</label>
          <input class="form-control" type="email" name="email" id="email" placeholder="exemplo@email.com">
        </div>

        <div class="col-md-6">
          <label class="form-label">Telefone (opcional)</label>
          <input class="form-control" name="telefone" id="telefone" placeholder="(00) 00000-0000">
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
            <option value="1" selected>Ativo</option>
            <option value="0">Desativado</option>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Perfil</label>
          <input class="form-control" value="Leitor" disabled>
          <div class="form-text">Por segurança, leitores sempre são “Leitor”.</div>
        </div>

      </div>

      <div class="form-actions">
        <button class="btn btn-pill" type="submit">
          <i class="bi bi-check2"></i>
          Salvar
        </button>

        <a class="btn btn-pill" href="listar.php">Cancelar</a>
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

  cpf.addEventListener('input', () => {
    // máscara simples 000.000.000-00
    let d = onlyDigits(cpf.value).slice(0, 11);
    let out = d;
    if (d.length > 3) out = d.slice(0,3) + '.' + d.slice(3);
    if (d.length > 6) out = out.slice(0,7) + '.' + d.slice(6);
    if (d.length > 9) out = out.slice(0,11) + '-' + d.slice(9);
    cpf.value = out;
  });

  function toggleContato() {
    const on = semContato.checked;
    email.disabled = on;
    tel.disabled = on;
    if (on) { email.value = ''; tel.value = ''; }
  }
  semContato.addEventListener('change', toggleContato);
  toggleContato();
</script>

<?php include("../includes/footer.php"); ?>
