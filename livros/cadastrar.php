<?php
$titulo_pagina = "Cadastrar Livro";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");


/*
  cadastrar.php
  - ISBN no topo
  - Busca automática por ISBN (buscar_isbn.php) enquanto digita
  - Preenche: título, autor, editora, ano, sinopse, assuntos e capa
  - CDD com autocomplete (buscar_cdd.php) usando campo visível + hidden numérico
  - Envia para salvar.php
*/

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}
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

    <form action="salvar.php" method="post" class="form-grid" autocomplete="off" enctype="multipart/form-data">
      <div class="row g-3">

        <!-- ISBN -->
        <div class="col-12">
          <label class="form-label">ISBN</label>

          <div class="input-group">
            <span class="input-group-text bg-white">
              <i class="bi bi-search"></i>
            </span>

            <input
              type="text"
              class="form-control"
              name="ISBN"
              id="isbn"
              placeholder="Digite o ISBN (10 ou 13 dígitos, com ou sem traços)"
              autocomplete="off"
              required>
          </div>

          <div class="mt-2 d-flex align-items-center gap-3">
            <img
              id="coverPreview"
              src=""
              alt="Capa do livro"
              style="width:64px;height:96px;object-fit:cover;border-radius:10px;display:none;border:1px solid #e7e1d6;"
              onerror="this.style.display='none';">
            <div class="text-muted small" id="coverHint">Digite um ISBN válido para buscar automaticamente os dados.</div>
          </div>

          <!-- URL final da capa (usada no salvar.php) -->
          <input type="hidden" name="capa_url" id="capa_url" value="">
        </div>

        <!-- Título / Autor / Editora / Ano -->
        <div class="col-md-6">
          <label class="form-label">Título</label>
          <input class="form-control" name="titulo" id="titulo" placeholder="Ex: Dom Casmurro" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Autor</label>
          <input class="form-control" name="autor" id="autor" placeholder="Ex: Machado de Assis">
        </div>

        <div class="col-md-2">
          <label class="form-label">Ano</label>
          <input class="form-control" type="number" name="ano_publicacao" id="ano_publicacao" min="0" placeholder="Ex: 1899">
        </div>

        <div class="col-md-6">
          <label class="form-label">Editora</label>
          <input class="form-control" name="editora" id="editora" placeholder="Ex: Companhia das Letras">
        </div>

        <!-- Quantidade -->
        <div class="col-md-3">
          <label class="form-label">Quantidade (exemplares)</label>
          <input class="form-control" type="number" name="qtd_total" id="qtd_total" min="1" value="1" required>
          <div class="form-text">Ao cadastrar, entram como disponíveis.</div>
        </div>

        <!-- CDD (Categoria) com autocomplete -->
        <div class="col-12 position-relative">
          <label class="form-label">CDD (Categoria)</label>

          <input
            type="text"
            id="cdd"
            class="form-control"
            placeholder="Digite o código ou área"
            autocomplete="off"
            required>

          <!-- ID numérico do CDD para o banco -->
          <input type="hidden" name="categoria" id="categoria" value="">

          <div id="resultadoCDD" class="list-group position-absolute w-100" style="z-index:1050;"></div>
          <div class="form-text">Digite e clique numa sugestão para preencher.</div>
        </div>

        <!-- Sinopse -->
        <div class="col-12">
          <label class="form-label">Sinopse</label>
          <textarea class="form-control" name="sinopse" id="sinopse" rows="4" placeholder="Vai preencher automático quando disponível"></textarea>
        </div>

        <!-- Assuntos -->
        <div class="col-12">
          <label class="form-label">Assuntos</label>
          <input class="form-control" name="assuntos" id="assuntos" placeholder="Ex: distopia, política, ficção científica">
          <div class="form-text">Vem automático quando disponível.</div>
        </div>

        <!-- Capa manual / upload -->
        <div class="col-md-6">
          <label class="form-label">Capa por URL (opcional)</label>
          <input class="form-control" id="capa_url_manual" placeholder="Cole um link de imagem (https://...)">
          <div class="form-text">Se colar aqui, vira a capa e não será sobrescrita pela busca.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Upload de capa (opcional)</label>
          <input class="form-control" type="file" name="capa_arquivo" id="capa_arquivo" accept="image/*">
          <div class="form-text">Se enviar arquivo, ele vira a capa.</div>
        </div>

      </div>

      <div class="form-actions mt-3">
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
  function onlyDigits(s) {
    return (s || '').replace(/\D/g, '');
  }

  // Campos do livro
  const isbnInput = document.getElementById("isbn");
  const tituloInput = document.getElementById("titulo");
  const autorInput = document.getElementById("autor");
  const editoraInput = document.getElementById("editora");
  const anoInput = document.getElementById("ano_publicacao");
  const sinopseInput = document.getElementById("sinopse");
  const assuntosInput = document.getElementById("assuntos");

  // Capa
  const imgPrev = document.getElementById("coverPreview");
  const hint = document.getElementById("coverHint");
  const capaHidden = document.getElementById("capa_url");
  const capaManual = document.getElementById("capa_url_manual");
  const capaFile = document.getElementById("capa_arquivo");

  // CDD
  const inputCDD = document.getElementById("cdd");
  const resultadoCDD = document.getElementById("resultadoCDD");
  const hiddenCat = document.getElementById("categoria");

  // Capa por URL manual
  capaManual.addEventListener("input", () => {
    const url = capaManual.value.trim();
    if (!url) return;

    capaHidden.value = url;
    imgPrev.src = url;
    imgPrev.style.display = "block";
    hint.textContent = "Capa definida por URL manual.";
  });

  // Preview do upload
  capaFile.addEventListener("change", () => {
    const f = capaFile.files && capaFile.files[0];
    if (!f) return;

    const url = URL.createObjectURL(f);
    imgPrev.src = url;
    imgPrev.style.display = "block";
    hint.textContent = "Capa definida por upload.";
  });

  // =========================
  // Auto busca ISBN (debounce)
  // =========================
  let isbnTimer = null;
  let lastIsbnFetched = "";

  isbnInput.addEventListener("input", () => {
    clearTimeout(isbnTimer);
    isbnTimer = setTimeout(() => buscarPorIsbnAuto(), 350);
  });

  async function buscarPorIsbnAuto() {
    const isbn = onlyDigits(isbnInput.value);

    if (!(isbn.length === 10 || isbn.length === 13)) {
      hint.textContent = "Digite um ISBN válido (10 ou 13 dígitos) para buscar automaticamente.";
      return;
    }

    if (isbn === lastIsbnFetched) return;

    const hasCoverOverride =
      (capaManual.value.trim() !== "") ||
      (capaFile.files && capaFile.files.length);

    hint.textContent = "Buscando dados do livro...";
    lastIsbnFetched = isbn;

    try {
      const resp = await fetch(`buscar_isbn.php?isbn=${encodeURIComponent(isbn)}`, {
        headers: {
          "X-Requested-With": "fetch"
        }
      });

      if (!resp.ok) {
        hint.textContent = `Erro ao buscar ISBN (HTTP ${resp.status}).`;
        return;
      }

      const data = await resp.json();

      if (!data || !data.ok) {
        hint.textContent = "Não encontrei esse ISBN. Preencha manualmente.";
        return;
      }

      if (data.titulo && !tituloInput.value.trim()) tituloInput.value = data.titulo;

      if (data.autor && !autorInput.value.trim() && String(data.autor).toLowerCase() !== "author not identified") {
        autorInput.value = data.autor;
      }

      if (data.editora && !editoraInput.value.trim()) {
        editoraInput.value = data.editora;
      }

      if (data.ano_publicacao && !anoInput.value.trim()) anoInput.value = data.ano_publicacao;

      if (data.sinopse && !sinopseInput.value.trim()) sinopseInput.value = data.sinopse;

      if (data.assuntos && !assuntosInput.value.trim()) assuntosInput.value = data.assuntos;

      if (!hasCoverOverride && data.capa_url) {
        capaHidden.value = data.capa_url;
        imgPrev.src = data.capa_url;
        imgPrev.style.display = "block";
      }

      const src = data.source ? data.source : "api";
      hint.textContent = `Dados carregados (${src}).`;
      // =========================
      // CDD automático pelo ISBN
      // =========================
      // Só tenta se o usuário ainda não escolheu um CDD manualmente
      if (!hiddenCat.value) {
        try {
          hint.textContent = `Dados carregados (${src}). Calculando CDD...`;

          const respCDD = await fetch(`buscar_cdd_isbn.php?isbn=${encodeURIComponent(isbn)}`);
          const cddData = await respCDD.json();

          if (cddData && cddData.ok) {
            inputCDD.value = cddData.cdd_text; // ex: "813 - Ficção..."
            hiddenCat.value = cddData.cdd_id; // id real da tabela cdd
            hint.textContent = `Dados carregados (${src}). CDD sugerido automaticamente.`;
          } else {
            hint.textContent = `Dados carregados (${src}). Não consegui sugerir CDD.`;
            console.log("CDD auto:", cddData);
          }
        } catch (e) {
          console.log("CDD auto falhou:", e);
          hint.textContent = `Dados carregados (${src}). Falha ao sugerir CDD.`;
        }
      }


    } catch (e) {
      console.log(e);
      hint.textContent = "Falha ao buscar ISBN.";
    }
  }

  // =========================
  // Autocomplete CDD (debounce)
  // =========================
  let cddTimer = null;

  inputCDD.addEventListener("input", () => {
    clearTimeout(cddTimer);

    const q = inputCDD.value.trim();
    hiddenCat.value = "";

    if (q.length < 2) {
      resultadoCDD.innerHTML = "";
      return;
    }

    cddTimer = setTimeout(async () => {
      try {
        const resp = await fetch(`buscar_cdd.php?q=${encodeURIComponent(q)}`);
        const html = await resp.text();
        resultadoCDD.innerHTML = html;
      } catch (e) {
        console.log(e);
        resultadoCDD.innerHTML = "";
      }
    }, 200);
  });

  resultadoCDD.addEventListener("click", (e) => {
    const item = e.target.closest("[data-id]");
    if (!item) return;

    inputCDD.value = item.dataset.text || "";
    hiddenCat.value = item.dataset.id || "";
    resultadoCDD.innerHTML = "";
  });

  document.addEventListener("click", (e) => {
    const inside = e.target.closest("#resultadoCDD") || e.target.closest("#cdd");
    if (!inside) resultadoCDD.innerHTML = "";
  });
</script>

<?php include("../includes/footer.php"); ?>