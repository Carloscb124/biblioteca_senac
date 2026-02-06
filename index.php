<?php
$titulo_pagina = "Início";
include("auth/auth_guard.php");
include("conexao.php");
include("includes/header.php");

$hoje = date('Y-m-d');

/*
  KPIs corretos:
  - Livros no acervo = livros ATIVOS (disponivel=1)
  - Membros ativos = usuarios ATIVOS e perfil leitor (se você usa isso)
  - Em empréstimo = empréstimos abertos (devolvido=0)
  - Em atraso = abertos e data_prevista < hoje
*/

// Livros ativos no acervo
$livros_total = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM livros WHERE disponivel = 1")
)['c'];

// (Opcional, mas muito útil) total de exemplares disponíveis agora
$exemplares_disponiveis = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COALESCE(SUM(qtd_disp),0) AS c FROM livros WHERE disponivel = 1")
)['c'];

// Membros ativos (ajusta se seu sistema não usa perfil)
$usuarios_total = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM usuarios WHERE ativo = 1 AND perfil = 'leitor'")
)['c'];

// Em empréstimo
$em_emprestimo = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM emprestimos WHERE devolvido = 0")
)['c'];

// Em atraso
$atrasados = (int)mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c
   FROM emprestimos
   WHERE devolvido = 0
     AND data_prevista IS NOT NULL
     AND data_prevista < CURDATE()"
))['c'];

/* Empréstimos recentes */
$sql_recent = "
SELECT
  e.id,
  e.data_prevista,
  e.data_devolucao,
  e.devolvido,
  u.nome AS membro,
  l.titulo AS livro
FROM emprestimos e
JOIN usuarios u ON u.id = e.id_usuario
JOIN livros l ON l.id = e.id_livro
ORDER BY e.id DESC
LIMIT 5
";
$recentes = mysqli_query($conn, $sql_recent);
?>

<div class="container my-4">

  <!-- HERO / BANNER -->
  <div class="dash-hero">
    <div class="dash-hero__text">
      <h2 class="dash-hero__title">Painel de Controle</h2>
      <p class="dash-hero__sub">
        Gerencie seu acervo, acompanhe empréstimos e mantenha tudo organizado.
      </p>
    </div>

    <div class="dash-hero__img">
      <img src="<?= $base ?>/assets/reader.png" alt="Ilustração leitura">
    </div>
  </div>

  <!-- KPIs -->
  <div class="dash-kpis">
    <div class="kpi-card">
      <div class="kpi-ic"><i class="bi bi-book"></i></div>
      <div class="kpi-num"><?= (int)$livros_total ?></div>
      <div class="kpi-label">Livros no Acervo</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-ic"><i class="bi bi-people"></i></div>
      <div class="kpi-num"><?= (int)$usuarios_total ?></div>
      <div class="kpi-label">Membros Ativos</div>
    </div>

    <div class="kpi-card">
      <div class="kpi-ic"><i class="bi bi-arrow-repeat"></i></div>
      <div class="kpi-num"><?= (int)$em_emprestimo ?></div>
      <div class="kpi-label">Em Empréstimo</div>
    </div>

    <div class="kpi-card kpi-danger">
      <div class="kpi-ic"><i class="bi bi-exclamation-circle"></i></div>
      <div class="kpi-num"><?= (int)$atrasados ?></div>
      <div class="kpi-label">Em Atraso</div>
    </div>
  </div>

  <!-- Recentes -->
  <div class="dash-section">
    <div class="dash-section__head">
      <h3 class="dash-h3">Histórico de Emprestimos</h3>
      <a class="dash-link" href="emprestimos/listar.php">
        Ver todos <i class="bi bi-arrow-right"></i>
      </a>
    </div>

    <div class="dash-table">
      <div class="table-responsive">
        <table class="table table-clean align-middle mb-0">
          <thead>
            <tr>
              <th>Livro</th>
              <th>Membro</th>
              <th>Devolução</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentes && mysqli_num_rows($recentes) > 0) { ?>
              <?php while ($e = mysqli_fetch_assoc($recentes)) {

                $devolvido = (int)$e['devolvido'];
                $prevista  = $e['data_prevista'] ?? null;
                $atrasado  = ($devolvido === 0 && !empty($prevista) && $prevista < $hoje);

              ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($e['livro']) ?></td>
                  <td><?= htmlspecialchars($e['membro']) ?></td>

                  <td class="text-muted">
                    <?php
                      if ($devolvido === 1) {
                        echo htmlspecialchars($e['data_devolucao'] ?? '-');
                      } else {
                        echo htmlspecialchars($e['data_prevista'] ?? '-');
                      }
                    ?>
                  </td>

                  <td>
                    <?php if ($devolvido === 1) { ?>
                      <span class="badge-status badge-done">Devolvido</span>
                    <?php } elseif ($atrasado) { ?>
                      <span class="badge-status badge-late">Atrasado</span>
                    <?php } else { ?>
                      <span class="badge-status badge-open">Aberto</span>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
            <?php } else { ?>
              <tr>
                <td colspan="4">
                  <div class="dash-empty">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Nenhum empréstimo registrado</span>
                  </div>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php include("includes/footer.php"); ?>
