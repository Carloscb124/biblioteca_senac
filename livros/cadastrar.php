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
      - sinopse
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

            <div class="text-muted small" id="coverHint">Digite um ISBN para buscar os dados e a capa.</div>
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

        <!-- SINOPSE -->
        <div class="col-12">
          <label class="form-label">Sinopse</label>
          <textarea class="form-control" name="sinopse" id="sinopse" rows="4" placeholder="Vai preencher automático pelo ISBN (quando disponível)"></textarea>
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
  function onlyDigits(s) {
    return (s || '').replace(/\D/g, '');
  }

  // ============ CDD autocomplete ============
  const inputCDD = document.getElementById("cdd");
  const resultadoCDD = document.getElementById("resultadoCDD");
  const hiddenCat = document.getElementById("categoria");

  inputCDD.addEventListener("keyup", () => {
    const q = inputCDD.value.trim();
    if (q.length < 2) {
      resultadoCDD.innerHTML = "";
      return;
    }

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

  // ============ Inputs do formulário ============
  const isbnInput = document.getElementById("isbn");

  const tituloInput = document.querySelector('input[name="titulo"]');
  const autorInput = document.querySelector('input[name="autor"]');
  const anoInput = document.querySelector('input[name="ano_publicacao"]');
  const sinopseInput = document.getElementById("sinopse");

  // Preview de capa
  const imgPrev = document.getElementById("coverPreview");
  const hint = document.getElementById("coverHint");
  const capaHidden = document.getElementById("capa_url");

  // inputs de override
  const capaManual = document.getElementById("capa_url_manual");
  const capaFile = document.getElementById("capa_arquivo");

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

    // O caminho real vai ser definido no salvar.php
    capaHidden.value = "";
  });

  // ============ Busca automática por ISBN (dados + capa) ============
  let t = null;
  isbnInput.addEventListener("input", () => {
    clearTimeout(t);
    t = setTimeout(buscarDadosPorIsbn, 350);
  });

  async function buscarDadosPorIsbn() {
    // Se o usuário já colocou URL manual ou upload, a gente não atrapalha a capa
    const hasOverrideCover =
      (capaManual.value.trim() !== "") ||
      (capaFile.files && capaFile.files.length);

    const d = onlyDigits(isbnInput.value);

    if (!d || (d.length !== 10 && d.length !== 13)) {
      hint.textContent = "Digite um ISBN válido (10 ou 13 dígitos).";
      return;
    }

    hint.textContent = "Buscando dados do livro...";
    try {
      const resp = await fetch(`buscar_isbn.php?isbn=${encodeURIComponent(d)}`, {
        headers: {
          "X-Requested-With": "fetch"
        }
      });

      const data = await resp.json();
      if (!data || !data.ok) {
        hint.textContent = "Não achei dados desse ISBN. Você pode preencher manual.";
        return;
      }

      // Preenche somente se estiver vazio (pra não brigar com você)
      if (data.titulo && !tituloInput.value.trim()) tituloInput.value = data.titulo;
      if (data.autor && !autorInput.value.trim() && data.autor.toLowerCase() !== "author not identified") {
        autorInput.value = data.autor;
      }

      if (data.ano_publicacao && !anoInput.value.trim()) anoInput.value = data.ano_publicacao;

      if (data.sinopse && !sinopseInput.value.trim()) {
        sinopseInput.value = data.sinopse;
      } else if (!sinopseInput.value.trim()) {
        // só mensagem, não preenche texto fake
        // deixa o campo vazio mesmo
      }


      // Capa vinda do buscar_isbn (só se não tiver override)
      if (!hasOverrideCover && data.capa_url) {
        capaHidden.value = data.capa_url;
        imgPrev.src = data.capa_url;
        imgPrev.style.display = "block";
        const src = data.source ? data.source : "api";
        hint.textContent = `Dados carregados (${src}).`;

        return;
      }

      // Se não veio capa no buscar_isbn, tenta teu buscar_capa.php (fallback do teu sistema)
      if (!hasOverrideCover) {
        await buscarCapaPorIsbnFallback(d);
      } else {
        hint.textContent = `Dados carregados (${data.source}).`;
      }

    } catch (e) {
      hint.textContent = "Erro ao buscar dados. Preencha manual ou tente de novo.";
    }
  }

  // ============ Fallback: usa teu buscar_capa.php ============
  async function buscarCapaPorIsbnFallback(isbn) {
    hint.textContent = "Buscando capa...";
    imgPrev.style.display = "none";
    imgPrev.src = "";

    try {
      const resp = await fetch(`buscar_capa.php?isbn=${encodeURIComponent(isbn)}`, {
        headers: {
          "X-Requested-With": "fetch"
        }
      });
      const data = await resp.json();

      if (data && data.ok && data.url) {
        capaHidden.value = data.url;
        imgPrev.src = data.url;
        imgPrev.style.display = "block";
        hint.textContent = `Capa encontrada (${data.source}).`;
      } else {
        capaHidden.value = "";
        hint.textContent = "Sem capa encontrada. Você pode colar uma URL ou enviar uma imagem.";
      }
    } catch (e) {
      capaHidden.value = "";
      hint.textContent = "Erro ao buscar capa. Use URL manual ou upload.";
    }
  }
</script>

<?php include("../includes/footer.php"); ?>