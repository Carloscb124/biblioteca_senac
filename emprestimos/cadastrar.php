<?php
$titulo_pagina = "Novo Empréstimo";
include("../conexao.php");
include("../includes/header.php");

$hoje = date('Y-m-d');
$previstaPadrao = date('Y-m-d', strtotime('+7 days'));
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Novo Empréstimo</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="salvar.php" method="post" class="form-grid" autocomplete="off">
      <div class="row g-3">

        <!-- LEITOR (BUSCA) -->
        <div class="col-md-6 position-relative">
          <label class="form-label">Leitor</label>
          <input type="text" class="form-control" id="leitorBusca"
                 placeholder="Digite nome, CPF, email ou telefone..." autocomplete="off" required>
          <input type="hidden" name="id_usuario" id="id_usuario" required>
          <div id="leitorSugestoes" class="list-group position-absolute w-100" style="z-index:1050;"></div>
          <div class="form-text">Digite pelo menos 2 caracteres e clique em uma sugestão.</div>
        </div>

        <!-- LIVRO (BUSCA) -->
        <div class="col-md-6 position-relative">
          <label class="form-label">Livro</label>
          <input type="text" class="form-control" id="livroBusca"
                 placeholder="Digite título, autor ou ISBN..." autocomplete="off" required>
          <input type="hidden" name="id_livro" id="id_livro" required>
          <div id="livroSugestoes" class="list-group position-absolute w-100" style="z-index:1050;"></div>
          <div class="form-text">Só aparecem livros com exemplares disponíveis.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data do empréstimo</label>
          <input class="form-control" type="date" name="data_emprestimo" value="<?= $hoje ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data prevista (opcional)</label>
          <input class="form-control" type="date" name="data_prevista" value="<?= $previstaPadrao ?>">
        </div>

        <div class="col-12">
          <button class="btn btn-pill" type="submit">
            <i class="bi bi-check2"></i>
            Salvar
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
  function setupBusca({ inputId, listId, hiddenId, url }) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    const hidden = document.getElementById(hiddenId);
    let timer = null;

    function limpar() { list.innerHTML = ""; }

    input.addEventListener("input", () => {
      clearTimeout(timer);
      hidden.value = ""; // invalida seleção ao digitar
      const q = input.value.trim();

      if (q.length < 2) { limpar(); return; }

      timer = setTimeout(() => {
        fetch(url + "?q=" + encodeURIComponent(q))
          .then(r => r.text())
          .then(html => list.innerHTML = html)
          .catch(() => limpar());
      }, 200);
    });

    list.addEventListener("click", (e) => {
      const item = e.target.closest("[data-id]");
      if (!item) return;

      input.value = item.dataset.text;
      hidden.value = item.dataset.id;
      limpar();
    });

    document.addEventListener("click", (e) => {
      if (e.target === input || list.contains(e.target)) return;
      limpar();
    });
  }

  setupBusca({
    inputId: "leitorBusca",
    listId: "leitorSugestoes",
    hiddenId: "id_usuario",
    url: "buscar_leitores.php"
  });

  setupBusca({
    inputId: "livroBusca",
    listId: "livroSugestoes",
    hiddenId: "id_livro",
    url: "buscar_livros.php"
  });
</script>

<?php include("../includes/footer.php"); ?>
