<?php
$titulo_pagina = "Livros";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

// Só pra manter o input preenchido no primeiro load
$q = trim($_GET['q'] ?? '');
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Livros</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Livro
      </a>
    </div>

    <!-- BUSCA -->
    <div class="row g-2 align-items-center mb-3">
      <div class="col-12 col-md-6">
        <div class="input-group">
          <span class="input-group-text bg-white">
            <i class="bi bi-search"></i>
          </span>
          <input
            type="text"
            id="bookSearch"
            class="form-control"
            placeholder="Buscar por título, autor ou ISBN..."
            autocomplete="off"
            value="<?= htmlspecialchars($q) ?>">
        </div>
        <small class="text-muted">A busca atualiza automaticamente enquanto você digita.</small>
      </div>
    </div>

    <div class="table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th style="width:70px;">Capa</th>
              <th>Título</th>
              <th>Autor</th>
              <th>Categoria (CDD)</th>
              <th class="col-ano">Ano</th>
              <th>ISBN</th>
              <th class="col-status">Disponíveis</th>
              <th class="text-end col-acoes">Ações</th>
            </tr>
          </thead>

          <tbody id="booksTbody">
            <tr>
              <td colspan="8" class="text-center text-muted py-4">Carregando...</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="booksPagination" class="mt-3"></div>
  </div>
</div>

<!-- MODAL: BAIXAR LIVRO -->
<div class="modal fade" id="modalBaixarLivro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-box-arrow-down me-2"></i>Baixar livro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2 text-muted">Você está prestes a baixar:</div>
        <div class="fw-semibold fs-5" id="modalLivroTituloBaixar">Livro</div>

        <div class="alert alert-warning mt-3 mb-0" style="border-radius:12px;">
          Ele não será apagado. Apenas ficará desativado no sistema.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-danger" id="btnConfirmarBaixar">
          <i class="bi bi-box-arrow-down me-1"></i> Baixar
        </a>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: REATIVAR LIVRO -->
<div class="modal fade" id="modalReativarLivro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-arrow-up-circle me-2"></i>Reativar livro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="mb-2 text-muted">Você está prestes a reativar:</div>
        <div class="fw-semibold fs-5" id="modalLivroTituloReativar">Livro</div>

        <div class="alert alert-success mt-3 mb-0" style="border-radius:12px;">
          O livro volta para o acervo e os disponíveis serão recalculados.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-success" id="btnConfirmarReativar">
          <i class="bi bi-arrow-up-circle me-1"></i> Reativar
        </a>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: DETALHES DO LIVRO -->
<div class="modal fade" id="modalDetalhesLivro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-journal-text me-2"></i>Detalhes do livro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
          <img id="detCapa" src="" alt="Capa"
               style="width:110px;height:155px;object-fit:cover;border-radius:14px;border:1px solid rgba(0,0,0,.08);background:#efe9dd;display:none;"
               onerror="this.style.display='none';" />

          <div class="flex-grow-1">
            <div class="fw-bold fs-4 mb-1" id="detTitulo">Livro</div>
            <div class="text-muted mb-2" id="detAutor">Autor</div>

            <div class="row g-2 small">
              <div class="col-md-4"><span class="text-muted">Ano:</span> <span id="detAno">-</span></div>
              <div class="col-md-8"><span class="text-muted">ISBN:</span> <span id="detIsbn">-</span></div>
              <div class="col-md-4"><span class="text-muted">CDD:</span> <span id="detCdd">-</span></div>
              <div class="col-md-8"><span class="text-muted">Disponíveis:</span> <span id="detDisp">-</span></div>
            </div>
          </div>
        </div>

        <hr>

        <div class="fw-semibold mb-1">Sinopse</div>
        <div class="text-muted" id="detSinopse" style="white-space:pre-wrap;">Sem sinopse cadastrada.</div>
      </div>

      <div class="modal-footer">
        <a href="#" class="btn btn-outline-secondary" id="detBtnEditar">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <button type="button" class="btn btn-pill" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>


<style>
  /* Quebra de linha pra títulos/autores enormes não estourarem a tabela */
  .cell-wrap{
    max-width: 320px;
    white-space: normal;
    word-break: break-word;
  }

  /* Capa com tamanho consistente (sem ficar mini ou gigante) */
  .cover-thumb{
    width: 44px;
    height: 62px;
    border-radius: 10px;
    object-fit: cover;
    display:block;
    border: 1px solid rgba(0,0,0,.06);
    background: #efe9dd;
  }
  .cover-placeholder{
    width: 44px;
    height: 62px;
    border-radius: 10px;
    display:flex;
    align-items:center;
    justify-content:center;
    border: 1px solid rgba(0,0,0,.06);
    background: #efe9dd;
    font-size: 10px;
    color:#7a7a7a;
  }
</style>

<script>
  // ===== Busca dinâmica + paginação (sem reload) =====
  const input = document.getElementById("bookSearch");
  const tbody = document.getElementById("booksTbody");
  const pagWrap = document.getElementById("booksPagination");
  let timer = null;

  function renderEmpty() {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="text-center text-muted py-4">Nenhum livro encontrado.</td>
      </tr>
    `;
    pagWrap.innerHTML = "";
  }

  async function fetchBooks({ q = "", p = 1 } = {}) {
    const url = `buscar.php?q=${encodeURIComponent(q)}&p=${encodeURIComponent(p)}`;
    const resp = await fetch(url, { headers: { "X-Requested-With": "fetch" } });

    if (!resp.ok) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-danger py-4">Erro ao carregar. Tente novamente.</td>
        </tr>
      `;
      pagWrap.innerHTML = "";
      return;
    }

    const data = await resp.json();
    tbody.innerHTML = data.rows_html?.trim() ? data.rows_html : (renderEmpty(), "");
    pagWrap.innerHTML = data.pagination_html || "";

    // paginação sem reload
    pagWrap.querySelectorAll("[data-page]").forEach(a => {
      a.addEventListener("click", (e) => {
        e.preventDefault();
        const page = parseInt(a.getAttribute("data-page"), 10) || 1;
        fetchBooks({ q: input.value.trim(), p: page });
      });
    });
  }

  // debounce da busca (pra não virar metralhadora de request)
  input.addEventListener("input", () => {
    clearTimeout(timer);
    timer = setTimeout(() => fetchBooks({ q: input.value.trim(), p: 1 }), 250);
  });

  fetchBooks({ q: input.value.trim(), p: 1 });

  // ===== Modais (delegação de evento - funciona com fetch) =====
  const modalBaixarEl = document.getElementById("modalBaixarLivro");
  const modalReativarEl = document.getElementById("modalReativarLivro");

  const tituloBaixar = document.getElementById("modalLivroTituloBaixar");
  const tituloReativar = document.getElementById("modalLivroTituloReativar");

  const btnConfirmarBaixar = document.getElementById("btnConfirmarBaixar");
  const btnConfirmarReativar = document.getElementById("btnConfirmarReativar");

  function getModalInstance(modalEl) {
    if (!(window.bootstrap && typeof bootstrap.Modal === "function")) return null;
    return bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  }

  tbody.addEventListener("click", (e) => {
    const btnBaixar = e.target.closest("[data-action='baixar']");
    const btnReativar = e.target.closest("[data-action='reativar']");
    if (!btnBaixar && !btnReativar) return;

    e.preventDefault();

    const btn = btnBaixar || btnReativar;
    const id = btn.getAttribute("data-id");
    const titulo = btn.getAttribute("data-titulo") || "Livro";

    if (btnBaixar) {
      const modal = getModalInstance(modalBaixarEl);
      if (!modal) { window.location.href = `excluir.php?id=${encodeURIComponent(id)}`; return; }
      tituloBaixar.textContent = titulo;
      btnConfirmarBaixar.href = `excluir.php?id=${encodeURIComponent(id)}`;
      modal.show();
      return;
    }

    const modal = getModalInstance(modalReativarEl);
    if (!modal) { window.location.href = `reativar.php?id=${encodeURIComponent(id)}`; return; }
    tituloReativar.textContent = titulo;
    btnConfirmarReativar.href = `reativar.php?id=${encodeURIComponent(id)}`;
    modal.show();
  });

    // ===== MODAL DETALHES (clicar no livro) =====
  const modalDetEl = document.getElementById("modalDetalhesLivro");

  const detCapa = document.getElementById("detCapa");
  const detTitulo = document.getElementById("detTitulo");
  const detAutor = document.getElementById("detAutor");
  const detAno = document.getElementById("detAno");
  const detIsbn = document.getElementById("detIsbn");
  const detCdd = document.getElementById("detCdd");
  const detDisp = document.getElementById("detDisp");
  const detSinopse = document.getElementById("detSinopse");
  const detBtnEditar = document.getElementById("detBtnEditar");

  function getModalInstance(modalEl) {
    if (!(window.bootstrap && typeof bootstrap.Modal === "function")) return null;
    return bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  }

  // Clique na linha do livro abre detalhes
  tbody.addEventListener("click", async (e) => {

    // Se clicou em botões de ação, deixa seu código atual cuidar
    if (e.target.closest("[data-action='baixar']") || e.target.closest("[data-action='reativar']")) return;
    if (e.target.closest("a.icon-btn")) return; // editar/ícones

    const tr = e.target.closest("tr.book-row");
    if (!tr) return;

    const id = tr.getAttribute("data-book-id");
    if (!id) return;

    const modal = getModalInstance(modalDetEl);
    if (!modal) return;

    // Mostra loading simples
    detTitulo.textContent = "Carregando...";
    detAutor.textContent = "";
    detAno.textContent = "-";
    detIsbn.textContent = "-";
    detCdd.textContent = "-";
    detDisp.textContent = "-";
    detSinopse.textContent = "Carregando sinopse...";
    detCapa.style.display = "none";
    detCapa.src = "";
    detBtnEditar.href = `editar.php?id=${encodeURIComponent(id)}`;

    modal.show();

    try {
      const resp = await fetch(`buscar_detalhes.php?id=${encodeURIComponent(id)}`, {
        headers: { "X-Requested-With": "fetch" }
      });
      const data = await resp.json();

      if (!data || !data.ok) {
        detTitulo.textContent = "Erro";
        detSinopse.textContent = "Não foi possível carregar os detalhes.";
        return;
      }

      const b = data.book;

      detTitulo.textContent = b.titulo || "Sem título";
      detAutor.textContent = b.autor || "Autor não informado";
      detAno.textContent = b.ano_publicacao || "-";
      detIsbn.textContent = b.isbn || "-";
      detCdd.textContent = b.categoria || "-";
      detDisp.textContent = `${b.qtd_disp}/${b.qtd_total}` + (b.disponivel ? "" : " (baixado)");
      detSinopse.textContent = (b.sinopse && b.sinopse.trim()) ? b.sinopse : "Sem sinopse cadastrada.";

      if (b.capa_url) {
        detCapa.src = b.capa_url;
        detCapa.style.display = "block";
      }

    } catch (err) {
      detTitulo.textContent = "Erro";
      detSinopse.textContent = "Falha ao buscar detalhes. Tente novamente.";
    }
  });

</script>

<?php include("../includes/footer.php"); ?>
