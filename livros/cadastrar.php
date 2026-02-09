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

    <!--
      Envia:
      - titulo, autor, ano_publicacao, ISBN, qtd_total
      - categoria (id do CDD)
      - capa_url (string) -> pode vir da API, de URL manual ou de upload local
      - capa_arquivo (upload) -> salvamos no servidor e colocamos o caminho em capa_url
    -->
    <form action="salvar.php" method="post" class="form-grid" autocomplete="off" enctype="multipart/form-data">
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
            placeholder="Digite o ISBN (com ou sem traços)"
            autocomplete="off"
            required>

          <!-- Preview + status da capa -->
          <div class="mt-2 d-flex align-items-center gap-3">
            <img
              id="coverPreview"
              src=""
              alt="Capa do livro"
              style="width:64px;height:96px;object-fit:cover;border-radius:10px;display:none;border:1px solid #e7e1d6;"
              onerror="this.style.display='none';">

            <div class="text-muted small" id="coverHint">Digite um ISBN para buscar a capa.</div>
          </div>

          <!-- Guarda a capa que será salva (url final) -->
          <input type="hidden" name="capa_url" id="capa_url" value="">
        </div>

        <div class="col-md-3">
          <label class="form-label">Quantidade (exemplares)</label>
          <input class="form-control" type="number" name="qtd_total" min="1" value="1" required>
          <div class="form-text">Ao cadastrar, todos os exemplares entram como disponíveis.</div>
        </div>

        <!-- CDD (categoria com busca dinâmica) -->
        <div class="col-12 position-relative">
          <label class="form-label">CDD (Categoria)</label>

          <input type="text" id="cdd" class="form-control"
            placeholder="Digite o código ou área"
            autocomplete="off" required>

          <input type="hidden" name="categoria" id="categoria">
          <div id="resultadoCDD" class="list-group position-absolute w-100" style="z-index:1050;"></div>

          <div class="form-text">Clique numa sugestão para preencher a categoria.</div>
        </div>

        <!-- Plano B: se API não achar capa -->
        <div class="col-12">
          <div class="row g-2">

            <div class="col-md-6">
              <label class="form-label">Capa por URL (opcional)</label>
              <input class="form-control" id="capa_url_manual" placeholder="Cole um link de imagem (https://...)">
              <div class="form-text">Se preencher aqui, essa URL vira a capa (e ignora a API).</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Upload de capa (opcional)</label>
              <input class="form-control" type="file" name="capa_arquivo" id="capa_arquivo" accept="image/*">
              <div class="form-text">Se enviar arquivo, ele vira a capa. No banco fica só o caminho.</div>
            </div>

          </div>
        </div>

      </div>

      <div class="form-actions mt-3">
        <button class="btn btn-brand" type="submit">
          <i class="bi bi-check2"></i>
          Salvar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  // ============ Helpers ============
  function onlyDigits(s){ return (s || '').replace(/\D/g, ''); }

  // ============ CDD autocomplete ============
  const inputCDD = document.getElementById("cdd");
  const resultadoCDD = document.getElementById("resultadoCDD");
  const hiddenCat = document.getElementById("categoria");

  inputCDD.addEventListener("keyup", () => {
    const q = inputCDD.value.trim();
    if (q.length < 2) { resultadoCDD.innerHTML = ""; return; }

    fetch("buscar_cdd.php?q=" + encodeURIComponent(q))
      .then(res => res.text())
      .then(html => resultadoCDD.innerHTML = html);
  });

  resultadoCDD.addEventListener("click", (e) => {
    const item = e.target.closest("[data-id]");
    if (!item) return;

    inputCDD.value = item.dataset.text || "";
    hiddenCat.value = item.dataset.id || "";
    resultadoCDD.innerHTML = "";
  });

  // ============ Capa por ISBN (OpenLibrary -> Google Books) ============
  const isbnInput   = document.getElementById("isbn");
  const imgPrev     = document.getElementById("coverPreview");
  const hint        = document.getElementById("coverHint");
  const capaHidden  = document.getElementById("capa_url");

  // inputs de override
  const capaManual = document.getElementById("capa_url_manual");
  const capaFile   = document.getElementById("capa_arquivo");

  // Quando o usuário coloca URL manual, a gente usa ela como capa
  capaManual.addEventListener("input", () => {
    const url = capaManual.value.trim();
    if (!url) return;

    capaHidden.value = url;
    imgPrev.src = url;
    imgPrev.style.display = "block";
    hint.textContent = "Capa definida por URL manual.";
  });

  // Preview do upload local (não salva aqui, só mostra)
  capaFile.addEventListener("change", () => {
    const f = capaFile.files && capaFile.files[0];
    if (!f) return;

    const url = URL.createObjectURL(f);
    imgPrev.src = url;
    imgPrev.style.display = "block";
    hint.textContent = "Capa definida por upload (vai salvar no servidor).";

    // Importante: o caminho real vai ser definido no salvar.php,
    // então a gente só limpa o hidden pra não brigar com URL antiga.
    capaHidden.value = "";
  });

  let t = null;
  isbnInput.addEventListener("input", () => {
    clearTimeout(t);
    t = setTimeout(buscarCapaPorIsbn, 300);
  });

  async function buscarCapaPorIsbn(){
    // Se o usuário já colocou URL manual ou upload, a gente não atrapalha
    if (capaManual.value.trim()) return;
    if (capaFile.files && capaFile.files.length) return;

    const d = onlyDigits(isbnInput.value);
    if (!d || (d.length !== 10 && d.length !== 13)) {
      imgPrev.style.display = "none";
      imgPrev.src = "";
      capaHidden.value = "";
      hint.textContent = "Digite um ISBN válido (10 ou 13 dígitos) para buscar a capa.";
      return;
    }

    hint.textContent = "Buscando capa...";
    imgPrev.style.display = "none";
    imgPrev.src = "";

    try{
      // Chama nosso backend (sem CORS e com fallback correto)
      const resp = await fetch(`buscar_capa.php?isbn=${encodeURIComponent(d)}`, {
        headers: { "X-Requested-With": "fetch" }
      });
      const data = await resp.json();

      if (data && data.ok && data.url) {
        capaHidden.value = data.url;             // salva só a URL/caminho
        imgPrev.src = data.url;
        imgPrev.style.display = "block";
        hint.textContent = `Capa encontrada (${data.source}).`;
      } else {
        capaHidden.value = "";
        hint.textContent = "Sem capa encontrada nas APIs. Você pode colar uma URL ou enviar uma imagem.";
      }
    }catch(e){
      capaHidden.value = "";
      hint.textContent = "Erro ao buscar capa. Use URL manual ou upload.";
    }
  }
</script>



<?php include("../includes/footer.php"); ?>
