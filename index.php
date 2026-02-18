<?php
$titulo_pagina = "Início";
include("auth/auth_guard.php");
include("conexao.php");

$hoje = date('Y-m-d');

/* =========================================================
   KPIs (modelo novo: emprestimos + emprestimo_itens)
   ========================================================= */

// Total de livros ativos no acervo (disponivel=1 = ativo)
$livros_total = (int)(mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM livros WHERE disponivel = 1")
)['c'] ?? 0);

// Total de usuários leitores ativos
$usuarios_total = (int)(mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM usuarios WHERE ativo = 1 AND perfil = 'leitor'")
)['c'] ?? 0);

// ✅ Empréstimos em aberto: existe pelo menos 1 item NÃO devolvido E NÃO perdido
$sql_abertos = "
  SELECT COUNT(*) AS c
  FROM (
    SELECT e.id
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    WHERE IFNULL(e.cancelado,0) = 0
    GROUP BY e.id
    HAVING SUM(CASE WHEN ei.devolvido = 0 AND IFNULL(ei.perdido,0)=0 THEN 1 ELSE 0 END) > 0
  ) t
";
$em_emprestimo = (int)(mysqli_fetch_assoc(mysqli_query($conn, $sql_abertos))['c'] ?? 0);

// ✅ Empréstimos atrasados: tem item aberto (não devolvido e não perdido) E data_prevista < hoje
$sql_atrasados = "
  SELECT COUNT(*) AS c
  FROM (
    SELECT e.id
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    WHERE IFNULL(e.cancelado,0) = 0
      AND e.data_prevista IS NOT NULL
      AND e.data_prevista < CURDATE()
    GROUP BY e.id
    HAVING SUM(CASE WHEN ei.devolvido = 0 AND IFNULL(ei.perdido,0)=0 THEN 1 ELSE 0 END) > 0
  ) t
";
$atrasados = (int)(mysqli_fetch_assoc(mysqli_query($conn, $sql_atrasados))['c'] ?? 0);

/* =========================================================
   Empréstimos recentes (só 3) - modelo novo
   ========================================================= */
function buscarRecentes(mysqli $conn) {
  $sql = "
    SELECT
      e.id,
      e.data_prevista,
      u.nome AS membro,

      COUNT(ei.id) AS qtd_itens,

      -- ✅ abertos = não devolvido e não perdido
      SUM(CASE WHEN ei.devolvido = 0 AND IFNULL(ei.perdido,0)=0 THEN 1 ELSE 0 END) AS abertos,

      -- ✅ perdidos
      SUM(CASE WHEN IFNULL(ei.perdido,0)=1 THEN 1 ELSE 0 END) AS perdidos,

      MAX(ei.data_devolucao) AS ultima_devolucao,

      GROUP_CONCAT(DISTINCT l.titulo ORDER BY l.titulo SEPARATOR ' | ') AS livros_titulos
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.id_usuario
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    JOIN livros l ON l.id = ei.id_livro
    WHERE IFNULL(e.cancelado,0) = 0
    GROUP BY e.id
    ORDER BY e.id DESC
    LIMIT 3
  ";
  return mysqli_query($conn, $sql);
}

$recentes = buscarRecentes($conn);

/* =========================================================
   AJAX do histórico: retorna SOMENTE <tr> (igual seu design)
   ========================================================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'recentes') {
  header("Content-Type: text/html; charset=UTF-8");

  $recentesAjax = buscarRecentes($conn);

  if ($recentesAjax && mysqli_num_rows($recentesAjax) > 0) {
    while ($e = mysqli_fetch_assoc($recentesAjax)) {
      $abertos   = (int)($e['abertos'] ?? 0);
      $perdidos  = (int)($e['perdidos'] ?? 0);
      $qtd       = (int)($e['qtd_itens'] ?? 0);
      $prevista  = $e['data_prevista'] ?? null;

      // ✅ fechado = não tem mais abertos (tudo devolvido OU perdido)
      $fechado = ($abertos === 0);
      $temPerdido = ($fechado && $perdidos > 0);

      $atrasado = (!$fechado && !empty($prevista) && $prevista < date('Y-m-d'));

      $livroTxt = ($qtd > 1) ? ($qtd . " livros") : ($e['livros_titulos'] ?? '—');

      echo "<tr>";
      echo "<td class='fw-semibold'>" . htmlspecialchars($livroTxt) . "</td>";
      echo "<td>" . htmlspecialchars($e['membro']) . "</td>";

      echo "<td class='text-muted'>";
      if ($fechado) echo htmlspecialchars($e['ultima_devolucao'] ?? '-');
      else echo htmlspecialchars($prevista ?? '-');
      echo "</td>";

      echo "<td>";
      if ($temPerdido) echo "<span class='badge-status badge-lost'>Perdido</span>";
      elseif ($fechado) echo "<span class='badge-status badge-done'>Devolvido</span>";
      elseif ($atrasado) echo "<span class='badge-status badge-late'>Atrasado</span>";
      else echo "<span class='badge-status badge-open'>Aberto</span>";
      echo "</td>";

      echo "</tr>";
    }
  } else {
    echo "<tr>
            <td colspan='4' class='text-center text-muted py-4'>
              Nenhum empréstimo registrado
            </td>
          </tr>";
  }

  exit;
}

include("includes/header.php");
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

  <!-- KPIs (cards clicáveis) -->
  <div class="dash-kpis">

    <a class="kpi-card kpi-click" href="<?= $base ?>/livros/listar.php?f=acervo" title="Ver livros no acervo">
      <div class="kpi-ic"><i class="bi bi-book"></i></div>
      <div class="kpi-num"><?= (int)$livros_total ?></div>
      <div class="kpi-label">Livros no Acervo</div>
      <div class="kpi-hint">Ver lista</div>
    </a>

    <a class="kpi-card kpi-click" href="<?= $base ?>/usuarios/listar.php?f=ativos" title="Ver leitores ativos">
      <div class="kpi-ic"><i class="bi bi-people"></i></div>
      <div class="kpi-num"><?= (int)$usuarios_total ?></div>
      <div class="kpi-label">Membros Ativos</div>
      <div class="kpi-hint">Ver lista</div>
    </a>

    <a class="kpi-card kpi-click" href="<?= $base ?>/emprestimos/listar.php?f=abertos" title="Ver empréstimos em aberto">
      <div class="kpi-ic"><i class="bi bi-arrow-repeat"></i></div>
      <div class="kpi-num"><?= (int)$em_emprestimo ?></div>
      <div class="kpi-label">Em Empréstimo</div>
      <div class="kpi-hint">Ver lista</div>
    </a>

    <a class="kpi-card kpi-danger kpi-click" href="<?= $base ?>/emprestimos/listar.php?f=atrasados" title="Ver atrasados">
      <div class="kpi-ic"><i class="bi bi-exclamation-circle"></i></div>
      <div class="kpi-num"><?= (int)$atrasados ?></div>
      <div class="kpi-label">Em Atraso</div>
      <div class="kpi-hint">Resolver</div>
    </a>

  </div>

  <!-- Recentes -->
  <div class="dash-section">
    <div class="dash-section__head">
      <h3 class="dash-h3">Histórico de Emprestimos</h3>
      <a class="dash-link" href="<?= $base ?>/emprestimos/listar.php">
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

          <tbody id="tbodyRecentes">
            <?php if ($recentes && mysqli_num_rows($recentes) > 0) { ?>
              <?php while ($e = mysqli_fetch_assoc($recentes)) {
                $abertos   = (int)($e['abertos'] ?? 0);
                $perdidos  = (int)($e['perdidos'] ?? 0);
                $qtd       = (int)($e['qtd_itens'] ?? 0);
                $prevista  = $e['data_prevista'] ?? null;

                $fechado = ($abertos === 0);
                $temPerdido = ($fechado && $perdidos > 0);
                $atrasado  = (!$fechado && !empty($prevista) && $prevista < $hoje);

                $livroTxt = ($qtd > 1) ? ($qtd . " livros") : ($e['livros_titulos'] ?? '—');
              ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($livroTxt) ?></td>
                  <td><?= htmlspecialchars($e['membro']) ?></td>

                  <td class="text-muted">
                    <?php
                      if ($fechado) echo htmlspecialchars($e['ultima_devolucao'] ?? '-');
                      else echo htmlspecialchars($prevista ?? '-');
                    ?>
                  </td>

                  <td>
                    <?php if ($temPerdido) { ?>
                      <span class="badge-status badge-lost">Perdido</span>
                    <?php } elseif ($fechado) { ?>
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

<style>
  /* cards clicáveis com feedback visual */
  .kpi-click{
    display:block;
    text-decoration:none;
    color: inherit;
    position: relative;
    cursor: pointer;
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .kpi-click:hover{
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,.06);
  }
  .kpi-click:active{
    transform: translateY(0px);
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
  }
  .kpi-hint{
    font-size: 12px;
    color: #6c757d;
    margin-top: 6px;
  }

  /* badge perdido (caso não exista no seu css global) */
  .badge-lost{
    background:#ffe1e1;
    color:#b42318;
    border:1px solid #f5a3a3;
    padding:.35rem .65rem;
    border-radius:999px;
    font-weight:600;
    font-size:.85rem;
    display:inline-flex;
    align-items:center;
    gap:.35rem;
  }
</style>

<script>
  // Atualiza só o histórico (sem reload da página)
  const tbody = document.getElementById('tbodyRecentes');

  async function refreshRecentes(){
    try{
      const resp = await fetch("<?= $base ?>/index.php?ajax=recentes", {
        headers: { "X-Requested-With": "fetch" },
        cache: "no-store"
      });

      if(!resp.ok) return;

      const html = await resp.text();

      if(html && html.trim().length){
        tbody.innerHTML = html;
      }
    }catch(e){
      // falhou? deixa quieto
    }
  }

  setInterval(refreshRecentes, 15000);
</script>

<?php include("includes/footer.php"); ?>
