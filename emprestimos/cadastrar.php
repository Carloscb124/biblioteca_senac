<?php
$titulo_pagina = "Novo Empréstimo";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$hoje = date('Y-m-d');
$prevPadrao = date('Y-m-d', strtotime('+7 days'));
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Novo Empréstimo</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i> Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" autocomplete="off">
      <div class="row g-3">

        <!-- LEITOR -->
        <div class="col-md-6 position-relative">
          <label class="form-label fw-bold">Leitor</label>
          <input type="text" class="form-control" id="leitorBusca"
                 placeholder="Digite nome, CPF, email ou telefone..." required>
          <input type="hidden" name="id_usuario" id="id_usuario" required>
          <div id="leitorSugestoes" class="list-group position-absolute w-100" style="z-index:1050;"></div>
          <div class="form-text">Digite pelo menos 2 caracteres e clique numa sugestão.</div>
        </div>

        <!-- DATAS -->
        <div class="col-md-3">
          <label class="form-label fw-bold">Data do empréstimo</label>
          <input type="date" class="form-control" name="data_emprestimo" value="<?= $hoje ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-bold">Data prevista</label>
          <input type="date" class="form-control" name="data_prevista" value="<?= $prevPadrao ?>">
        </div>

        <!-- LIVROS -->
        <div class="col-12">
          <label class="form-label fw-bold">Livros (até 3)</label>

          <div id="livrosContainer" class="row g-3">
            <div class="col-md-6 position-relative livro-item">
              <input type="text" class="form-control livroBusca"
                     placeholder="Digite título, autor ou ISBN..." required>
              <input type="hidden" name="id_livros[]" class="id_livro" required>
              <div class="livroSugestoes list-group position-absolute w-100" style="z-index:1050;"></div>
              <div class="form-text">Só aparecem livros com exemplares disponíveis.</div>
            </div>
          </div>

          <div class="mt-2 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-pill" id="btnAddLivro">
              <i class="bi bi-plus-lg"></i> Adicionar outro livro
            </button>

            <button type="button" class="btn btn-sm btn-pill" id="btnRemLivro" disabled>
              <i class="bi bi-dash-lg"></i> Remover último
            </button>
          </div>
        </div>

        <div class="col-12">
          <button class="btn btn-pill" type="submit">
            <i class="bi bi-check2"></i> Salvar
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

<script>
  function debounce(fn, delay=200) {
    let t=null;
    return (...args) => { clearTimeout(t); t=setTimeout(()=>fn(...args), delay); }
  }

  // ========== LEITORES ==========
  (function setupLeitor() {
    const input = document.getElementById("leitorBusca");
    const list  = document.getElementById("leitorSugestoes");
    const hid   = document.getElementById("id_usuario");

    function limpar(){ list.innerHTML=""; }

    const buscar = debounce(() => {
      const q = input.value.trim();
      hid.value = "";
      if (q.length < 2) return limpar();

      fetch("buscar_leitores.php?q=" + encodeURIComponent(q))
        .then(r => r.text())
        .then(html => list.innerHTML = html)
        .catch(() => limpar());
    });

    input.addEventListener("input", buscar);

    list.addEventListener("click", (e) => {
      const item = e.target.closest("[data-id]");
      if (!item) return;
      input.value = item.dataset.text;
      hid.value   = item.dataset.id;
      limpar();
    });

    document.addEventListener("click", (e) => {
      if (e.target === input || list.contains(e.target)) return;
      limpar();
    });
  })();

  // ========== LIVROS ==========
  function setupLivro(wrapper) {
    const input = wrapper.querySelector(".livroBusca");
    const list  = wrapper.querySelector(".livroSugestoes");
    const hid   = wrapper.querySelector(".id_livro");

    function limpar(){ list.innerHTML=""; }

    const buscar = debounce(() => {
      const q = input.value.trim();
      hid.value = "";
      if (q.length < 2) return limpar();

      fetch("buscar_livros.php?q=" + encodeURIComponent(q))
        .then(r => r.text())
        .then(html => list.innerHTML = html)
        .catch(() => limpar());
    });

    input.addEventListener("input", buscar);

    list.addEventListener("click", (e) => {
      const item = e.target.closest("[data-id]");
      if (!item) return;

      // evita escolher o mesmo livro repetido
      const escolhido = item.dataset.id;
      const ja = Array.from(document.querySelectorAll(".id_livro"))
        .map(x => x.value)
        .filter(v => v);
      if (ja.includes(escolhido)) {
        alert("Esse livro já foi selecionado neste empréstimo.");
        return;
      }

      input.value = item.dataset.text;
      hid.value   = item.dataset.id;
      limpar();
    });

    document.addEventListener("click", (e) => {
      if (e.target === input || list.contains(e.target)) return;
      limpar();
    });
  }

  setupLivro(document.querySelector(".livro-item"));

  const container = document.getElementById("livrosContainer");
  const btnAdd = document.getElementById("btnAddLivro");
  const btnRem = document.getElementById("btnRemLivro");

  function atualizarBotoes() {
    const total = container.querySelectorAll(".livro-item").length;
    btnAdd.disabled = total >= 3;
    btnRem.disabled = total <= 1;
  }

  btnAdd.addEventListener("click", () => {
    const total = container.querySelectorAll(".livro-item").length;
    if (total >= 3) return;

    const col = document.createElement("div");
    col.className = "col-md-6 position-relative livro-item";
    col.innerHTML = `
      <input type="text" class="form-control livroBusca" placeholder="Digite título, autor ou ISBN..." required>
      <input type="hidden" name="id_livros[]" class="id_livro" required>
      <div class="livroSugestoes list-group position-absolute w-100" style="z-index:1050;"></div>
      <div class="form-text">Só aparecem livros com exemplares disponíveis.</div>
    `;
    container.appendChild(col);
    setupLivro(col);
    atualizarBotoes();
  });

  btnRem.addEventListener("click", () => {
    const itens = container.querySelectorAll(".livro-item");
    if (itens.length <= 1) return;
    itens[itens.length - 1].remove();
    atualizarBotoes();
  });

  atualizarBotoes();
</script>

<?php include("../includes/footer.php"); ?>
