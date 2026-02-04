<?php
$titulo_pagina = "Livros";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

// ===== CONFIG =====
$por_pagina = 10;
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
              <th class="col-id">ID</th>
              <th>Título</th>
              <th>Autor</th>
              <th class="col-ano">Ano</th>
              <th>ISBN</th>
              <th class="col-status">Disponível</th>
              <th class="text-end col-acoes">Ações</th>
            </tr>
          </thead>

          <tbody id="booksTbody">
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Carregando...</td>
            </tr>
          </tbody>

        </table>
      </div>
    </div>

    <!-- PAGINAÇÃO (vai ser preenchida via JS) -->
    <div id="booksPagination" class="mt-3"></div>

  </div>
</div>

<script>
  const input = document.getElementById("bookSearch");
  const tbody = document.getElementById("booksTbody");
  const pagWrap = document.getElementById("booksPagination");

  let timer = null;

  function renderEmpty() {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center text-muted py-4">
          Nenhum livro encontrado.
        </td>
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
          <td colspan="7" class="text-center text-danger py-4">
            Erro ao carregar. Tente novamente.
          </td>
        </tr>
      `;
      pagWrap.innerHTML = "";
      return;
    }

    const data = await resp.json();

    tbody.innerHTML = data.rows_html?.trim()
      ? data.rows_html
      : renderEmpty() || "";

    pagWrap.innerHTML = data.pagination_html || "";

    // Intercepta cliques na paginação (sem reload)
    pagWrap.querySelectorAll("[data-page]").forEach(a => {
      a.addEventListener("click", (e) => {
        e.preventDefault();
        const page = parseInt(a.getAttribute("data-page"), 10) || 1;
        fetchBooks({ q: input.value.trim(), p: page });
      });
    });
  }

  // Debounce na busca
  input.addEventListener("input", () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      fetchBooks({ q: input.value.trim(), p: 1 });
    }, 250);
  });

  // Carrega inicial
  fetchBooks({ q: input.value.trim(), p: 1 });
</script>

<?php include("../includes/footer.php"); ?>
