<?php
$titulo_pagina = "Início";
include("auth/auth_guard.php");
include("conexao.php");

$hoje = date('Y-m-d');

/* =========================================================
   KPIs
   ========================================================= */

// Total de livros ativos no acervo (disponivel=1 = ativo)
$livros_total = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM livros WHERE disponivel = 1")
)['c'];

// Total de usuários leitores ativos
$usuarios_total = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM usuarios WHERE ativo = 1 AND perfil = 'leitor'")
)['c'];

// Empréstimos em aberto
$em_emprestimo = (int)mysqli_fetch_assoc(
  mysqli_query($conn, "SELECT COUNT(*) AS c FROM emprestimos WHERE devolvido = 0")
)['c'];

// Empréstimos atrasados (aberto + data prevista menor que hoje)
$atrasados = (int)mysqli_fetch_assoc(mysqli_query(
  $conn,
  "SELECT COUNT(*) AS c
   FROM emprestimos
   WHERE devolvido = 0
     AND data_prevista IS NOT NULL
     AND data_prevista < CURDATE()"
))['c'];

/* =========================================================
   Empréstimos recentes (só 3)
   ========================================================= */
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
LIMIT 3
";
$recentes = mysqli_query($conn, $sql_recent);

/* =========================================================
   ✅ AJAX do histórico (IMPORTANTE)
   Esse bloco tem que rodar ANTES do header/footer.
   Se o header entrar aqui, o fetch recebe a página inteira
   (navbar, layout, etc) e isso quebra o <tbody>.
   ========================================================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'recentes') {
  header("Content-Type: text/html; charset=UTF-8");

  if ($recentes && mysqli_num_rows($recentes) > 0) {
    while ($e = mysqli_fetch_assoc($recentes)) {
      $devolvido = (int)$e['devolvido'];
      $prevista  = $e['data_prevista'] ?? null;

      // Atrasado = ainda não devolvido + data prevista < hoje
      $atrasado  = ($devolvido === 0 && !empty($prevista) && $prevista < $hoje);

      // Retorna SOMENTE as linhas <tr> para o tbody
      echo "<tr>";
      echo "<td class='fw-semibold'>" . htmlspecialchars($e['livro']) . "</td>";
      echo "<td>" . htmlspecialchars($e['membro']) . "</td>";

      echo "<td class='text-muted'>";
      if ($devolvido === 1) {
        echo htmlspecialchars($e['data_devolucao'] ?? '-');
      } else {
        echo htmlspecialchars($e['data_prevista'] ?? '-');
      }
      echo "</td>";

      echo "<td>";
      if ($devolvido === 1) echo "<span class='badge-status badge-done'>Devolvido</span>";
      elseif ($atrasado) echo "<span class='badge-status badge-late'>Atrasado</span>";
      else echo "<span class='badge-status badge-open'>Aberto</span>";
      echo "</td>";

      echo "</tr>";
    }
  } else {
    // Caso não exista nada, retorna só 1 linha
    echo "<tr>
            <td colspan='4' class='text-center text-muted py-4'>
              Nenhum empréstimo registrado
            </td>
          </tr>";
  }

  // ✅ Para aqui, não renderiza o resto da página
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

                $devolvido = (int)$e['devolvido'];
                $prevista  = $e['data_prevista'] ?? null;
                $atrasado  = ($devolvido === 0 && !empty($prevista) && $prevista < $hoje);

              ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($e['livro']) ?></td>
                  <td><?= htmlspecialchars($e['membro']) ?></td>

                  <td class="text-muted">
                    <?php
                      if ($devolvido === 1) echo htmlspecialchars($e['data_devolucao'] ?? '-');
                      else echo htmlspecialchars($e['data_prevista'] ?? '-');
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
</style>

<script>
  // Atualiza só o histórico (sem reload da página)
  // Busca SOMENTE os <tr> porque o PHP do ajax=recentes sai antes do header
  const tbody = document.getElementById('tbodyRecentes');

  async function refreshRecentes(){
    try{
      const resp = await fetch("<?= $base ?>/index.php?ajax=recentes", {
        headers: { "X-Requested-With": "fetch" },
        cache: "no-store"
      });

      if(!resp.ok) return;

      const html = await resp.text();

      // Só atualiza se vier conteúdo válido
      if(html && html.trim().length){
        tbody.innerHTML = html;
      }
    }catch(e){
      // se falhar, ignora
    }
  }

  // Atualiza periodicamente
  setInterval(refreshRecentes, 15000);
</script>

<?php include("includes/footer.php"); ?>
