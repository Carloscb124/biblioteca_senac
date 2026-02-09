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
<div class="modal fade" id="modalLivroDetalhes" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px; overflow:hidden;">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-book me-2"></i>Detalhes do livro
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex gap-3 align-items-start">
          <img id="detCapa" src="" alt="Capa"
               style="width:120px;height:180px;object-fit:cover;border-radius:12px;border:1px solid #e7e1d6;"
               onerror="this.src=''; this.style.opacity=.4;">

          <div class="flex-grow-1">
            <div class="fw-semibold fs-4" id="detTitulo">Carregando...</div>
            <div class="text-muted" id="detAutorAno"></div>

            <div class="mt-2 d-flex flex-wrap gap-2">
              <span class="badge text-bg-light" style="border:1px solid #e7e1d6;" id="detIsbn">ISBN: -</span>
              <span class="badge text-bg-light" style="border:1px solid #e7e1d6;" id="detCdd">CDD: -</span>
              <span class="badge text-bg-light" style="border:1px solid #e7e1d6;" id="detQtd">Disponíveis: -</span>
            </div>
          </div>
        </div>

        <hr>

        <div class="fw-semibold mb-1">Sinopse</div>
        <div id="detSinopse" class="text-muted" style="white-space:pre-line;">
          Carregando sinopse...
        </div>

        <div id="detFonte" class="mt-2 small text-muted"></div>
      </div>

      <div class="modal-footer">
        <a class="btn btn-outline-secondary" id="detEditar" href="#">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <button type="button" class="btn btn-brand" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script>
  // ===== Modal de detalhes do livro =====
  const modalDetEl = document.getElementById("modalLivroDetalhes");

  // elementos do modal
  const detCapa = document.getElementById("detCapa");
  const detTitulo = document.getElementById("detTitulo");
  const detAutorAno = document.getElementById("detAutorAno");
  const detIsbn = document.getElementById("detIsbn");
  const detCdd = document.getElementById("detCdd");
  const detQtd = document.getElementById("detQtd");
  const detSinopse = document.getElementById("detSinopse");
  const detFonte = document.getElementById("detFonte");
  const detEditar = document.getElementById("detEditar");

  function getModalInstance(modalEl) {
    if (!(window.bootstrap && typeof bootstrap.Modal === "function")) return null;
    return bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
  }

  // Clique no título OU em qualquer lugar da linha (menos nos botões de ação)
  tbody.addEventListener("click", async (e) => {
    // se clicou em algum botão de ação, não abre detalhes
    if (e.target.closest(".icon-btn")) return;

    const row = e.target.closest("tr");
    if (!row) return;

  
    const id = row.getAttribute("data-id");
    if (!id) return;

    e.preventDefault();

    const modal = getModalInstance(modalDetEl);
    if (!modal) return;

    // estado inicial (loading)
    detTitulo.textContent = "Carregando...";
    detAutorAno.textContent = "";
    detIsbn.textContent = "ISBN: -";
    detCdd.textContent = "CDD: -";
    detQtd.textContent = "Disponíveis: -";
    detSinopse.textContent = "Carregando sinopse...";
    detFonte.textContent = "";
    detCapa.src = "";
    detEditar.href = `editar.php?id=${encodeURIComponent(id)}`;

    modal.show();

    try {
      const resp = await fetch(`detalhes.php?id=${encodeURIComponent(id)}`, {
        headers: { "X-Requested-With": "fetch" }
      });
      const data = await resp.json();

      if (!data.ok) {
        detTitulo.textContent = "Não consegui carregar os detalhes";
        detSinopse.textContent = data.message || "Erro desconhecido.";
        return;
      }

      const b = data.book;

      detTitulo.textContent = b.titulo || "-";
      detAutorAno.textContent = `${b.autor || "-"}${b.ano ? " • " + b.ano : ""}`;
      detIsbn.textContent = `ISBN: ${b.isbn || "-"}`;
      detCdd.textContent = `CDD: ${b.cdd || "-"}`;
      detQtd.textContent = `Disponíveis: ${b.qtd_disponivel}/${b.qtd_total}`;

      detCapa.src = b.capa_url ? b.capa_url : "";
      detSinopse.textContent = data.sinopse ? data.sinopse : "Sem sinopse encontrada nas APIs. (Triste, mas acontece 😅)";

      if (data.source) {
        detFonte.textContent = `Fonte da sinopse: ${data.source}`;
      } else {
        detFonte.textContent = "";
      }

    } catch (err) {
      detTitulo.textContent = "Ops...";
      detSinopse.textContent = "Erro ao buscar detalhes. Tenta de novo.";
    }
  });
</script>


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
</script>

<?php include("../includes/footer.php"); ?>
