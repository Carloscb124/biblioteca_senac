<?php
$titulo_pagina = "Cadastrar Livro";
include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Cadastrar Livro</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" class="form-grid" autocomplete="off">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Título</label>
          <input class="form-control" name="titulo" required placeholder="Ex: Dom Casmurro">
        </div>

        <div class="col-md-6">
          <label class="form-label">Autor</label>
          <input class="form-control" name="autor" placeholder="Ex: Machado de Assis">
        </div>

        <div class="col-md-3">
          <label class="form-label">Ano</label>
          <input class="form-control" type="number" name="ano_publicacao" min="0" placeholder="Ex: 1899">
        </div>

        <div class="col-md-3">
          <label class="form-label">ISBN</label>
          <input
            class="form-control"
            name="ISBN"
            id="isbn"
            maxlength="17"
            placeholder="978-65-5501-123-4"
            autocomplete="off"
            value="<?= isset($l) ? htmlspecialchars($l['ISBN'] ?? '') : '' ?>">
        </div>
      </div>

      <div class="col-12 position-relative">
        <label class="form-label">CDD (Categoria)</label>

        <input type="text" id="cdd" class="form-control"
          placeholder="Digite o código ou área"
          autocomplete="off">

        <input type="hidden" name="categoria" id="categoria"
          value="<?= isset($l) ? (int)($l['categoria'] ?? 0) : 0 ?>">

        <div id="resultadoCDD"
          class="list-group position-absolute w-100"
          style="z-index: 1050;"></div>
      </div>


      <div class="form-actions">
        <button class="btn btn-pill" type="submit">
          <i class="bi bi-check2"></i>
          Salvar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  const isbnInput = document.getElementById('isbn');

  function onlyDigits(v) {
    return (v || '').replace(/\D/g, '');
  }

  function formatISBN(value) {
    const digits = onlyDigits(value);

    // ISBN-10
    if (digits.length <= 10) {
      // padrão simples: X-XXX-XXXXX-X
      let out = digits;
      if (digits.length > 1) out = digits.slice(0,1) + '-' + digits.slice(1);
      if (digits.length > 4) out = out.slice(0,5) + '-' + digits.slice(4);
      if (digits.length > 9) out = out.slice(0,11) + '-' + digits.slice(9);
      return out;
    }

    // ISBN-13
    let out = digits.slice(0,13);
    // 978-65-5501-123-4 (modelo BR comum)
    out = out.replace(
      /^(\d{3})(\d{0,2})(\d{0,4})(\d{0,3})(\d{0,1}).*/,
      (_, a, b, c, d, e) =>
        [a, b, c, d, e].filter(Boolean).join('-')
    );
    return out;
  }

  if (isbnInput) {
    isbnInput.addEventListener('input', () => {
      const pos = isbnInput.selectionStart;
      isbnInput.value = formatISBN(isbnInput.value);
      isbnInput.setSelectionRange(pos, pos);
    });
  }
</script>


<script>
  const inputCDD = document.getElementById("cdd");
  const resultado = document.getElementById("resultadoCDD");
  const hidden = document.getElementById("categoria");

  function limpar() {
    resultado.innerHTML = "";
  }

  inputCDD?.addEventListener("keyup", () => {
    const q = inputCDD.value.trim();
    hidden.value = ""; // ainda não selecionou nada

    if (q.length < 2) {
      limpar();
      return;
    }

    fetch("buscar_cdd.php?q=" + encodeURIComponent(q))
      .then(r => r.text())
      .then(html => resultado.innerHTML = html)
      .catch(() => limpar());
  });

  resultado?.addEventListener("click", (e) => {
    const item = e.target.closest("[data-id]");
    if (!item) return;

    inputCDD.value = item.dataset.text;
    hidden.value = item.dataset.id;
    limpar();
  });

  document.addEventListener("click", (e) => {
    if (e.target === inputCDD || resultado.contains(e.target)) return;
    limpar();
  });
</script>


<?php include("../includes/footer.php"); ?>