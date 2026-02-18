<?php
$titulo_pagina = "Relatórios";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

// ===== LIVROS (qtd_total / qtd_disp) =====
$row = mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT
    COUNT(*) AS titulos,
    SUM(qtd_total) AS exemplares_total,
    SUM(qtd_disp)  AS exemplares_disp
  FROM livros
  WHERE disponivel = 1
"));
$livros_titulos = (int)($row['titulos'] ?? 0);
$ex_total = (int)($row['exemplares_total'] ?? 0);
$ex_disp  = (int)($row['exemplares_disp'] ?? 0);

// ===== USUÁRIOS (membros) =====
$usuarios_total  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM usuarios"))['c'];
$usuarios_ativos = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM usuarios WHERE ativo = 1"))['c'];

// ===== EMPRÉSTIMOS / ITENS =====

// Empréstimos ativos = empréstimo com pelo menos 1 item aberto (não devolvido e não perdido)
$emprestimos_ativos = (int)mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT COUNT(*) AS c
  FROM (
    SELECT e.id
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    WHERE e.cancelado = 0
    GROUP BY e.id
    HAVING SUM(CASE WHEN ei.devolvido = 0 AND ei.perdido = 0 THEN 1 ELSE 0 END) > 0
  ) t
"))['c'];

// Atrasados (itens) = item aberto + data_prevista < hoje
$itens_atrasados = (int)mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT COUNT(*) AS c
  FROM emprestimos e
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  WHERE e.cancelado = 0
    AND ei.devolvido = 0
    AND ei.perdido = 0
    AND e.data_prevista IS NOT NULL
    AND e.data_prevista < CURDATE()
"))['c'];

// Perdidos (itens) = ei.perdido = 1
$itens_perdidos = (int)mysqli_fetch_assoc(mysqli_query($conn, "
  SELECT COUNT(*) AS c
  FROM emprestimos e
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  WHERE e.cancelado = 0
    AND ei.perdido = 1
"))['c'];
?>

<style>
/* ===== KPIs EM 2 LINHAS ORGANIZADAS ===== */
.kpi-row{
  display:flex;
  gap:16px;
  margin-bottom:16px;
}

.kpi-row .report-card{
  flex:1;
}

/* segunda linha centralizada */
.kpi-row.center{
  justify-content:center;
}

.kpi-row.center .report-card{
  max-width:420px;
  flex:0 0 420px;
}

/* deixar todos com altura consistente */
.report-card{
  height: 100%;
  display:flex;
  flex-direction:column;
}
.report-card__foot{ margin-top:auto; }

/* responsivo */
@media (max-width:992px){
  .kpi-row{
    flex-direction:column;
  }
  .kpi-row.center .report-card{
    max-width:100%;
    flex:1;
  }
}
</style>

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

  <!-- LINHA 1 -->
  <div class="kpi-row">

    <div class="report-card">
      <div class="report-card__top"><i class="bi bi-book"></i><span>Livros (títulos)</span></div>
      <div class="report-card__num"><?= $livros_titulos ?></div>
      <div class="report-card__foot"><?= $ex_disp ?> / <?= $ex_total ?> exemplares disponíveis</div>
    </div>

    <div class="report-card">
      <div class="report-card__top"><i class="bi bi-people"></i><span>Membros</span></div>
      <div class="report-card__num"><?= $usuarios_total ?></div>
      <div class="report-card__foot"><?= $usuarios_ativos ?> ativos</div>
    </div>

    <div class="report-card">
      <div class="report-card__top"><i class="bi bi-arrow-repeat"></i><span>Empréstimos ativos</span></div>
      <div class="report-card__num"><?= $emprestimos_ativos ?></div>
      <div class="report-card__foot">com itens em aberto</div>
    </div>

  </div>

  <!-- LINHA 2 -->
  <div class="kpi-row center">

    <div class="report-card report-card--danger">
      <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Atrasados</span></div>
      <div class="report-card__num"><?= $itens_atrasados ?></div>
      <div class="report-card__foot">itens vencidos</div>
    </div>

    <div class="report-card report-card--danger">
      <div class="report-card__top"><i class="bi bi-x-octagon"></i><span>Perdidos</span></div>
      <div class="report-card__num"><?= $itens_perdidos ?></div>
      <div class="report-card__foot">itens marcados como perdidos</div>
    </div>

  </div>

  <div class="report-links mt-4">
    <a class="report-link" href="emprestimos_periodo.php">
      <div class="report-link__ic"><i class="bi bi-calendar2-week"></i></div>
      <div class="report-link__txt">
        <strong>Empréstimos por período</strong>
        <span>Filtre por datas e veja o volume no gráfico.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="livros_mais_emprestados.php">
      <div class="report-link__ic"><i class="bi bi-star"></i></div>
      <div class="report-link__txt">
        <strong>Livros mais emprestados</strong>
        <span>Ranking com gráfico.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="atrasados.php">
      <div class="report-link__ic report-link__ic--danger"><i class="bi bi-clock-history"></i></div>
      <div class="report-link__txt">
        <strong>Atrasados e Perdidos</strong>
        <span>Vencidos e itens marcados como perdidos.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="usuario.php">
      <div class="report-link__ic"><i class="bi bi-person-lines-fill"></i></div>
      <div class="report-link__txt">
        <strong>Histórico por usuário</strong>
        <span>Gráfico por status do membro.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>

    <a class="report-link" href="<?= $base ?>/relatorios/csv/index.php">
      <div class="report-link__ic"><i class="bi bi-filetype-csv"></i></div>
      <div class="report-link__txt">
        <strong>Importar / Exportar CSV</strong>
        <span>Gerencie via planilha.</span>
      </div>
      <i class="bi bi-chevron-right report-link__arrow"></i>
    </a>
  </div>

</div>

<?php include("../includes/footer.php"); ?>
