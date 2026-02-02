<?php
$titulo_pagina = "Empréstimos";
include("auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$sql = "
SELECT
  e.id,
  e.data_emprestimo,
  e.data_prevista,
  e.data_devolucao,
  e.devolvido,
  u.nome AS usuario_nome,
  l.titulo AS livro_titulo
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN livros l ON l.id = e.id_livro
ORDER BY e.id DESC
";
$r = mysqli_query($conn, $sql);

$hoje = date('Y-m-d');
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Lista de Empréstimos</h2>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i>
        Novo Empréstimo
      </a>
    </div>

    <div class="table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
        <thead>
          <tr>
            <th class="col-id">ID</th>
            <th>Usuário</th>
            <th>Livro</th>
            <th class="col-ano">Empréstimo</th>
            <th class="col-ano">Prevista</th>
            <th class="col-ano">Devolução</th>
            <th class="col-status">Status</th>
            <th class="text-end col-acoes">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($e = mysqli_fetch_assoc($r)) {
            $devolvido = (int)$e['devolvido'];
            $prevista  = $e['data_prevista'] ?? null;
            $atrasado  = ($devolvido === 0 && !empty($prevista) && $prevista < $hoje);
          ?>
            <tr>
              <td class="text-muted fw-semibold">#<?= (int)$e['id'] ?></td>
              <td class="fw-semibold"><?= htmlspecialchars($e['usuario_nome']) ?></td>
              <td><?= htmlspecialchars($e['livro_titulo']) ?></td>

              <td><?= htmlspecialchars($e['data_emprestimo']) ?></td>
              <td><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></td>
              <td><?= htmlspecialchars($e['data_devolucao'] ?? '-') ?></td>

              <td>
                <?php if ($devolvido === 1) { ?>
                  <span class="badge-status badge-done">
                    <i class="bi bi-check-circle"></i>
                    Devolvido
                  </span>

                <?php } elseif ($atrasado) { ?>
                  <span class="badge-status badge-late">
                    <i class="bi bi-exclamation-circle"></i>
                    Atrasado
                  </span>

                <?php } else { ?>
                  <span class="badge-status badge-open">
                    <i class="bi bi-clock-history"></i>
                    Aberto
                  </span>
                <?php } ?>
              </td>

              <td class="text-end">
                <?php if ($devolvido === 0) { ?>
                  <a class="icon-btn icon-btn--edit"
                    href="editar.php?id=<?= (int)$e['id'] ?>"
                    title="Editar">
                    <i class="bi bi-pencil"></i>
                  </a>

                  <a class="icon-btn icon-btn--edit"
                    href="devolver.php?id=<?= (int)$e['id'] ?>"
                    onclick="return confirm('Confirmar devolução?')"
                    title="Devolver">
                    <i class="bi bi-check2-circle"></i>
                  </a>
                <?php } ?>

                <a class="icon-btn icon-btn--del"
                  href="excluir.php?id=<?= (int)$e['id'] ?>"
                  onclick="return confirm('Excluir este empréstimo?')"
                  title="Excluir">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<?php include("../includes/footer.php"); ?>