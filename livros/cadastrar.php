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

        <div class="col-md-2">
          <label class="form-label">Ano</label>
          <input class="form-control" type="number" name="ano_publicacao" min="0" placeholder="Ex: 1899">
        </div>

        <div class="col-md-4">
          <label class="form-label">ISBN</label>
          <input
            class="form-control"
            name="ISBN"
            id="isbn"
            maxlength="20"
            placeholder="Digite o ISBN (com ou sem traços)"
            autocomplete="off"
            required>

          <!-- Preview da capa (não salva no banco) -->
          <div class="mt-2 d-flex align-items-center gap-3">
            <img
              id="isbnCoverPreview"
              src=""
              alt="Capa do livro"
              style="width:64px;height:96px;object-fit:cover;border-radius:10px;display:none;border:1px solid #e7e1d6;"
              onerror="this.style.display='none';">
            <div class="text-muted small" id="isbnCoverHint">Digite um ISBN para ver a capa.</div>
          </div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Quantidade (exemplares)</label>
          <input class="form-control" type="number" name="qtd_total" min="1" value="1" required>
          <div class="form-text">Ao cadastrar, todos os exemplares entram como disponíveis.</div>
        </div>

        <!-- CDD -->
        <div class="col-12 position-relative">
          <label class="form-label">CDD (Categoria)</label>

          <input type="text" id="cdd" class="form-control"
            placeholder="Digite o código ou área"
            autocomplete="off">

          <input type="hidden" name="categoria" id="categoria" value="">

          <div id="resultadoCDD" class="list-group position-absolute w-100" style="z-index: 1050;"></div>

          <div class="form-text">Clique numa sugestão para preencher a categoria.</div>
        </div>
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
  // ===== CDD autocomplete =====
  const inputCDD = document.getElementById("cdd");
  const resultadoCDD = document.getElementById("resultadoCDD");
  const hiddenCat = document.getElementById("categoria");

  function limparCDD() { resultadoCDD.innerHTML = ""; }

  inputCDD?.addEventListener("keyup", () => {
    const q = inputCDD.value.trim();
    hiddenCat.value = "";

    if (q.length < 2) { limparCDD(); return; }

    fetch("buscar_cdd.php?q=" + encodeURIComponent(q))
      .then(r => r.text())
      .then(html => resultadoCDD.innerHTML = html)
      .catch(() => limparCDD());
  });

  resultadoCDD?.addEventListener("click", (e) => {
    const item = e.target.closest("[data-id]");
    if (!item) return;

    inputCDD.value = item.dataset.text;
    hiddenCat.value = item.dataset.id;
    limparCDD();
  });

  document.addEventListener("click", (e) => {
    if (e.target === inputCDD || resultadoCDD.contains(e.target)) return;
    limparCDD();
  });

  // ===== Preview da capa por ISBN (Open Library) =====
  const isbnInput = document.getElementById('isbn');
  const imgPrev = document.getElementById('isbnCoverPreview');
  const hint = document.getElementById('isbnCoverHint');

  function isbnDigits(v){ return (v || '').replace(/\D/g, ''); }

  function updateCover(){
    const d = isbnDigits(isbnInput?.value);
    if (!d || (d.length !== 10 && d.length !== 13)) {
      imgPrev.style.display = 'none';
      imgPrev.src = '';
      hint.textContent = 'Digite um ISBN válido (10 ou 13 dígitos) para ver a capa.';
      return;
    }

    const url = `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(d)}-M.jpg?default=false`;

    imgPrev.onload = () => {
      imgPrev.style.display = 'block';
      hint.textContent = 'Capa encontrada (Open Library).';
    };

    imgPrev.onerror = () => {
      imgPrev.style.display = 'none';
      hint.textContent = 'Sem capa para este ISBN (Open Library).';
    };

    imgPrev.src = url;
  }

  let t = null;
  isbnInput?.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(updateCover, 250);
  });

  async function updateCover() {
  const d = isbnDigits(isbnInput?.value);
  if (!d || (d.length !== 10 && d.length !== 13)) {
    imgPrev.style.display = 'none';
    imgPrev.src = '';
    hint.textContent = 'Digite um ISBN válido (10 ou 13 dígitos) para ver a capa.';
    return;
  }

  // 1. Tenta Open Library primeiro
  const urlOL = `https://covers.openlibrary.org/b/isbn/${encodeURIComponent(d)}-M.jpg?default=false`;
  
  imgPrev.onload = () => {
    imgPrev.style.display = 'block';
    hint.textContent = 'Capa encontrada (Open Library).';
  };

  imgPrev.onerror = async () => {
    // 2. Se falhar, tenta Google Books
    hint.textContent = 'Buscando no Google Books...';
    try {
      const resp = await fetch(`https://www.googleapis.com/books/v1/volumes?q=isbn:${d}`);
      const data = await resp.json();
      
      if (data.totalItems > 0 && data.items[0].volumeInfo.imageLinks) {
        const urlGoogle = data.items[0].volumeInfo.imageLinks.thumbnail.replace('http:', 'https:');
        imgPrev.src = urlGoogle;
        imgPrev.style.display = 'block';
        hint.textContent = 'Capa encontrada (Google Books).';
      } else {
        imgPrev.style.display = 'none';
        hint.textContent = 'Sem capa disponível para este ISBN.';
      }
    } catch (e) {
      imgPrev.style.display = 'none';
      hint.textContent = 'Erro ao buscar capa.';
    }
  };

  imgPrev.src = urlOL;
}
</script>

<?php include("../includes/footer.php"); ?>
