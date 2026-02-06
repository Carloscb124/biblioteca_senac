<?php
$titulo_pagina = "Editar Empréstimo";
include("../conexao.php");
include("../includes/header.php");


$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash_set('danger', 'Empréstimo inválido.');
  header("Location: listar.php");
  exit;
}

$stmt = mysqli_prepare($conn, "
  SELECT
    e.*,
    u.nome AS usuario_nome, u.cpf AS usuario_cpf, u.email AS usuario_email, u.telefone AS usuario_tel,
    l.titulo AS livro_titulo, l.autor AS livro_autor, l.ISBN AS livro_isbn, l.qtd_disp AS livro_disp, l.qtd_total AS livro_tot
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN livros l ON l.id = e.id_livro
  WHERE e.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$e = mysqli_fetch_assoc($res);

if (!$e) {
  flash_set('danger', 'Empréstimo não encontrado.');
  header("Location: listar.php");
  exit;
}

if ((int)$e['devolvido'] === 1) {
  flash_set('warning', 'Empréstimo devolvido não pode ser editado.');
  header("Location: listar.php");
  exit;
}

$hoje = date('Y-m-d');

$cont = [];
if (!empty($e['usuario_email'])) $cont[] = $e['usuario_email'];
if (!empty($e['usuario_tel'])) $cont[] = $e['usuario_tel'];
$contTxt = $cont ? implode(" | ", $cont) : "Sem contato";

$textoLeitor = $e['usuario_nome'] . " (CPF: " . ($e['usuario_cpf'] ?? '') . ") • " . $contTxt;

$extra = [];
if (!empty($e['livro_autor'])) $extra[] = "Autor: " . $e['livro_autor'];
if (!empty($e['livro_isbn'])) $extra[] = "ISBN: " . $e['livro_isbn'];
$extraTxt = $extra ? " • " . implode(" | ", $extra) : "";

$textoLivro = $e['livro_titulo'] . $extraTxt . " (" . (int)$e['livro_disp'] . "/" . (int)$e['livro_tot'] . " disponíveis)";
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Editar Empréstimo</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="atualizar.php" method="post" class="form-grid" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
      <input type="hidden" name="id_livro_atual" value="<?= (int)$e['id_livro'] ?>">

      <div class="row g-3">

        <!-- LEITOR -->
        <div class="col-md-6 position-relative">
          <label class="form-label">Leitor</label>
          <input type="text" class="form-control" id="leitorBusca"
                 placeholder="Digite nome, CPF, email ou telefone..." autocomplete="off"
                 value="<?= htmlspecialchars($textoLeitor) ?>" required>
          <input type="hidden" name="id_usuario" id="id_usuario" value="<?= (int)$e['id_usuario'] ?>" required>
          <div id="leitorSugestoes" class="list-group position-absolute w-100" style="z-index:1050;"></div>
          <div class="form-text">Se trocar o texto, clique numa sugestão pra validar.</div>
        </div>

        <!-- LIVRO -->
        <div class="col-md-6 position-relative">
          <label class="form-label">Livro</label>
          <input type="text" class="form-control" id="livroBusca"
                 placeholder="Digite título, autor ou ISBN..." autocomplete="off"
                 value="<?= htmlspecialchars($textoLivro) ?>" required>
          <input type="hidden" name="id_livro" id="id_livro" value="<?= (int)$e['id_livro'] ?>" required>
          <div id="livroSugestoes" class="list-group position-absolute w-100" style="z-index:1050;"></div>
          <div class="form-text">O livro atual já está selecionado. Se trocar, clique numa sugestão.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data do empréstimo</label>
          <input class="form-control" type="date" name="data_emprestimo"
                 value="<?= htmlspecialchars($e['data_emprestimo'] ?? $hoje) ?>" required>
        </div>

        <div class="col-md-4">
          <label class="form-label">Data prevista (opcional)</label>
          <input class="form-control" type="date" name="data_prevista"
                 value="<?= htmlspecialchars($e['data_prevista'] ?? '') ?>">
        </div>

        <div class="col-12">
          <button class="btn btn-pill" type="submit">
            <i class="bi bi-check2"></i>
            Salvar alterações
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
