<?php
$titulo_pagina = "Relatórios";
include("../conexao.php");
include("../includes/header.php");

$livros_total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM livros"))['c'];
$livros_disp  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM livros WHERE disponivel = 1"))['c'];
$usuarios_total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM usuarios"))['c'];
$emprestimos_ativos = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM emprestimos WHERE devolvido = 0"))['c'];
$atrasados = (int)mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT COUNT(*) AS c
  FROM emprestimos
  WHERE devolvido = 0
    AND data_prevista IS NOT NULL
    AND data_prevista < CURDATE()
"))['c'];
?>

<div class="container my-4">

  <div class="report-head">
    <div class="report-head__title">
      <div class="report-ic"><i class="bi bi-graph-up"></i></div>
      <div>
        <h2 class="report-title">Relatórios</h2>
        <p class="report-sub">Estatísticas e análises da biblioteca</p>
      </div>
    </div>
  </div>

  <hr class="report-hr">

  <div class="report-kpis">
    <div class="report-card">
      <div class="report-card__top"><i class="bi bi-book"></i><span>Total de Livros</span></div>
      <div class="report-card__num"><?= $livros_total ?></div>
      <div class="report-card__foot"><?= $livros_disp ?> disponíveis</div>
    </div>

    <div class="report-card">
      <div class="report-card__top"><i class="bi bi-people"></i><span>Membros Cadastrados</span></div>
      <div class="report-card__num"><?= $usuarios_total ?></div>
      <div class="report-card__foot"><?= $usuarios_total ?> ativos</div>
    </div>

    <div class="report-card">
      <div class="report-card__top"><i class="bi bi-arrow-repeat"></i><span>Empréstimos Ativos</span></div>
      <div class="report-card__num"><?= $emprestimos_ativos ?></div>
      <div class="report-card__foot">Em andamento</div>
    </div>

    <div class="report-card report-card--danger">
      <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Em Atraso</span></div>
      <div class="report-card__num"><?= $atrasados ?></div>
      <div class="report-card__foot">Requer atenção</div>
    </div>
  </div>

  <div class="report-links">
    <a class="report-link" href="emprestimos_periodo.php">
      <div class="report-link__ic"><i class="bi bi-calendar2-week"></i></div>
      <div class="report-link__txt">
        <strong>Empréstimos por período</strong>
        <span>Filtre por datas e veja quem pegou o quê.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="livros_mais_emprestados.php">
      <div class="report-link__ic"><i class="bi bi-star"></i></div>
      <div class="report-link__txt">
        <strong>Livros mais emprestados</strong>
        <span>Ranking de livros mais populares.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="atrasados.php">
      <div class="report-link__ic report-link__ic--danger"><i class="bi bi-clock-history"></i></div>
      <div class="report-link__txt">
        <strong>Empréstimos em atraso</strong>
        <span>Lista de devoluções vencidas.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="usuario.php">
      <div class="report-link__ic"><i class="bi bi-person-lines-fill"></i></div>
      <div class="report-link__txt">
        <strong>Histórico por usuário</strong>
        <span>Veja o histórico de empréstimos por membro.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>
  </div>

</div>

<?php include("../includes/footer.php"); ?>
