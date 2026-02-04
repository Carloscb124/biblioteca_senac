<?php
$titulo_pagina = "Editar Livro";
include("../conexao.php");
include("../includes/header.php");

$id = (int)($_GET['id'] ?? 0);

/*
  Busca o livro + dados do CDD
*/
$stmt = mysqli_prepare($conn, "
  SELECT
    l.*,
    c.codigo AS cdd_codigo,
    c.descricao AS cdd_descricao
  FROM livros l
  LEFT JOIN cdd c ON c.id = l.categoria
  WHERE l.id = ?
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$l = mysqli_fetch_assoc($res);

if (!$l) { ?>
  <div class="container my-4">
    <div class="alert alert-danger mb-0">Livro não encontrado.</div>
  </div>
  <?php include("../includes/footer.php");
  exit; ?>
<?php } ?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Editar Livro</h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <form action="atualizar.php" method="post" class="form-grid" autocomplete="off">
      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">

      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Título</label>
          <input class="form-control" name="titulo" required
            value="<?= htmlspecialchars($l['titulo']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Autor</label>
          <input class="form-control" name="autor"
            value="<?= htmlspecialchars($l['autor'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Ano</label>
          <input class="form-control" type="number" name="ano_publicacao" min="0"
            value="<?= htmlspecialchars($l['ano_publicacao'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">ISBN</label>
          <input
            class="form-control"
            name="ISBN"
            id="isbn"
            maxlength="17"
            placeholder="978-65-5501-123-4"
            autocomplete="off"
            value="<?= isset($l) ? htmlspecialchars($l['ISBN'] ?? '') : '' ?>">

        </div>

        <!-- CDD -->
        <div class="col-12 position-relative">
          <label class="form-label">CDD (Categoria)</label>

          <input type="text" id="cdd" class="form-control"
            placeholder="Digite o código ou área"
            autocomplete="off"
            value="<?= htmlspecialchars(
                      ($l['cdd_codigo'] && $l['cdd_descricao'])
                        ? $l['cdd_codigo'] . ' - ' . $l['cdd_descricao']
                        : ''
                    ) ?>">

          <input type="hidden" name="categoria" id="categoria"
            value="<?= (int)($l['categoria'] ?? 0) ?>">

          <div id="resultadoCDD"
            class="list-group position-absolute w-100"
            style="z-index:1050"></div>

          <div class="form-text">Clique numa sugestão para alterar a categoria.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Disponível</label>
          <select class="form-select" name="disponivel">
            <option value="1" <?= ((int)$l['disponivel'] === 1) ? "selected" : "" ?>>Sim</option>
            <option value="0" <?= ((int)$l['disponivel'] === 0) ? "selected" : "" ?>>Não</option>
          </select>
        </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-brand" type="submit">
          <i class="bi bi-check2"></i>
          Atualizar
        </button>

        <a class="btn btn-outline-secondary" href="listar.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  const isbnInput = document.getElementById('isbn');

  function onlyDigits(v) {
    return (v || '').replace(/\D/g, '');
  }

  function formatISBN(value) {
    const digits = onlyDigits(value);

    // ISBN-10
    if (digits.length <= 10) {
      // padrão simples: X-XXX-XXXXX-X
      let out = digits;
      if (digits.length > 1) out = digits.slice(0,1) + '-' + digits.slice(1);
      if (digits.length > 4) out = out.slice(0,5) + '-' + digits.slice(4);
      if (digits.length > 9) out = out.slice(0,11) + '-' + digits.slice(9);
      return out;
    }

    // ISBN-13
    let out = digits.slice(0,13);
    // 978-65-5501-123-4 (modelo BR comum)
    out = out.replace(
      /^(\d{3})(\d{0,2})(\d{0,4})(\d{0,3})(\d{0,1}).*/,
      (_, a, b, c, d, e) =>
        [a, b, c, d, e].filter(Boolean).join('-')
    );
    return out;
  }

  if (isbnInput) {
    isbnInput.addEventListener('input', () => {
      const pos = isbnInput.selectionStart;
      isbnInput.value = formatISBN(isbnInput.value);
      isbnInput.setSelectionRange(pos, pos);
    });
  }
</script>


<script>
  const inputCDD = document.getElementById("cdd");
  const resultado = document.getElementById("resultadoCDD");
  const hidden = document.getElementById("categoria");

  function limpar() {
    resultado.innerHTML = "";
  }

  inputCDD.addEventListener("keyup", () => {
    const q = inputCDD.value.trim();
    hidden.value = ""; // se digitar, ainda não escolheu

    if (q.length < 2) {
      limpar();
      return;
    }

    fetch("buscar_cdd.php?q=" + encodeURIComponent(q))
      .then(res => res.text())
      .then(html => resultado.innerHTML = html)
      .catch(() => limpar());
  });

  resultado.addEventListener("click", (e) => {
    const item = e.target.closest("[data-id]");
    if (!item) return;

    inputCDD.value = item.dataset.text;
    hidden.value = item.dataset.id;
    limpar();
  });

  document.addEventListener("click", (e) => {
    if (e.target === inputCDD || resultado.contains(e.target)) return;
    limpar();
  });
</script>

<?php include("../includes/footer.php"); ?>