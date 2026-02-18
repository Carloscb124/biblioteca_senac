<?php
$titulo_pagina = "Histórico por usuário";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

// Busca todos os usuários
$usuarios_query = mysqli_query($conn, "SELECT id, nome FROM usuarios ORDER BY nome ASC");
$lista_usuarios = [];
while ($row = mysqli_fetch_assoc($usuarios_query)) $lista_usuarios[] = $row;

$uid = (int)($_GET['usuario'] ?? 0);
$historico = null;
$nome_selecionado = "";

$kpi = ['total' => 0, 'devolvidos' => 0, 'abertos' => 0, 'atrasados' => 0, 'perdidos' => 0];

if ($uid > 0) {
  // KPIs (contando ITENS do usuário)
  $stmt = mysqli_prepare($conn, "
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN ei.devolvido=1 THEN 1 ELSE 0 END) AS devolvidos,
      SUM(CASE WHEN ei.devolvido=0 AND ei.perdido=0 THEN 1 ELSE 0 END) AS abertos,
      SUM(CASE WHEN ei.devolvido=0 AND ei.perdido=0 AND e.data_prevista IS NOT NULL AND e.data_prevista < CURDATE() THEN 1 ELSE 0 END) AS atrasados,
      SUM(CASE WHEN ei.perdido=1 THEN 1 ELSE 0 END) AS perdidos
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    WHERE e.cancelado = 0
      AND e.id_usuario = ?
  ");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  $kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $kpi;

  // Histórico detalhado (1 linha por item/livro)
  $stmt = mysqli_prepare($conn, "
    SELECT
      l.titulo,
      e.data_emprestimo,
      e.data_prevista,
      ei.data_devolucao,
      ei.devolvido,
      ei.perdido,
      ei.data_perdido
    FROM emprestimos e
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    JOIN livros l ON l.id = ei.id_livro
    WHERE e.cancelado = 0
      AND e.id_usuario = ?
    ORDER BY e.data_emprestimo DESC, ei.id DESC
  ");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  $historico = mysqli_stmt_get_result($stmt);

  foreach ($lista_usuarios as $u) {
    if ((int)$u['id'] === $uid) { $nome_selecionado = $u['nome']; break; }
  }
}

$labels = ["Aberto", "Devolvido", "Atrasado", "Perdido"];
$values = [(int)$kpi['abertos'], (int)$kpi['devolvidos'], (int)$kpi['atrasados'], (int)$kpi['perdidos']];
?>

<style>
  .search-container { position: relative; }
  .custom-results {
    position: absolute; top: 100%; left: 0; right: 0;
    background: #fff; border: 1px solid #dee2e6; border-top: none;
    z-index: 1000; display: none; max-height: 250px; overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  }
  .result-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #f1f1f1; color: #333; transition: background 0.2s; }
  .result-item:last-child { border-bottom: none; }
  .result-item:hover { background-color: #f8f9fa; }
</style>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title m-0">Histórico por usuário</h2>
      <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <form class="row g-3 mt-1" id="formBusca" method="GET">
      <div class="col-md-6 search-container">
        <label class="form-label">Pesquisar usuário</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input
            type="text"
            id="inputUsuario"
            class="form-control"
            placeholder="Digite o nome..."
            value="<?= htmlspecialchars($nome_selecionado) ?>"
            autocomplete="off"
          >
          <input type="hidden" name="usuario" id="usuarioId" value="<?= $uid ?>">
        </div>
        <div id="resultsBox" class="custom-results"></div>
      </div>
    </form>

    <?php if ($uid > 0) { ?>
      <div class="report-kpis mt-4">
        <div class="report-card">
          <div class="report-card__top"><i class="bi bi-list-check"></i><span>Total</span></div>
          <div class="report-card__num"><?= (int)$kpi['total'] ?></div>
          <div class="report-card__foot">itens</div>
        </div>
        <div class="report-card">
          <div class="report-card__top"><i class="bi bi-check-circle"></i><span>Devolvidos</span></div>
          <div class="report-card__num"><?= (int)$kpi['devolvidos'] ?></div>
          <div class="report-card__foot">finalizados</div>
        </div>
        <div class="report-card">
          <div class="report-card__top"><i class="bi bi-clock-history"></i><span>Abertos</span></div>
          <div class="report-card__num"><?= (int)$kpi['abertos'] ?></div>
          <div class="report-card__foot">em andamento</div>
        </div>
        <div class="report-card report-card--danger">
          <div class="report-card__top"><i class="bi bi-exclamation-triangle"></i><span>Atrasados</span></div>
          <div class="report-card__num"><?= (int)$kpi['atrasados'] ?></div>
          <div class="report-card__foot">atenção</div>
        </div>
      </div>

      <div class="report-kpis mt-3">
        <div class="report-card report-card--danger">
          <div class="report-card__top"><i class="bi bi-x-octagon"></i><span>Perdidos</span></div>
          <div class="report-card__num"><?= (int)$kpi['perdidos'] ?></div>
          <div class="report-card__foot">itens perdidos</div>
        </div>
      </div>

      <div class="report-chart mt-4">
        <div class="report-chart__head">
          <strong>Status dos empréstimos</strong>
        </div>
        <div class="chart-wrap">
          <canvas id="chartStatusUsuario"></canvas>
        </div>
      </div>

      <div class="mt-4 table-base-wrap">
        <div class="table-responsive">
          <table class="table table-base align-middle mb-0">
            <thead>
              <tr>
                <th>Livro</th>
                <th>Empréstimo</th>
                <th>Prevista</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $hojeX = date('Y-m-d');
              $tem = false;
              while ($h = mysqli_fetch_assoc($historico)) {
                $tem = true;
                $devolvido = (int)$h['devolvido'];
                $perdido = (int)$h['perdido'];
                $prev = $h['data_prevista'] ?? null;
                $atrasado = ($devolvido === 0 && $perdido === 0 && !empty($prev) && $prev < $hojeX);
              ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars($h['titulo']) ?></td>
                  <td><?= htmlspecialchars($h['data_emprestimo']) ?></td>
                  <td><?= htmlspecialchars($h['data_prevista'] ?? '-') ?></td>
                  <td>
                    <?php if ($perdido === 1) { ?>
                      <span class="badge-soft-no"><i class="bi bi-x-circle"></i> Perdido</span>
                    <?php } elseif ($devolvido === 1) { ?>
                      <span class="badge-status badge-done">Devolvido</span>
                    <?php } elseif ($atrasado) { ?>
                      <span class="badge-status badge-late">Atrasado</span>
                    <?php } else { ?>
                      <span class="badge-status badge-open">Aberto</span>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
              <?php if (!$tem) { ?>
                <tr><td colspan="4" class="text-muted">Sem histórico.</td></tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
      <script>
        new Chart(document.getElementById('chartStatusUsuario'), {
          type: 'doughnut',
          data: { labels: <?= json_encode($labels) ?>, datasets: [{ data: <?= json_encode($values) ?> }] },
          options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
      </script>
    <?php } ?>
  </div>
</div>

<script>
const usuarios = <?= json_encode($lista_usuarios) ?>;
const input = document.getElementById('inputUsuario');
const resultsBox = document.getElementById('resultsBox');
const hiddenId = document.getElementById('usuarioId');

input.addEventListener('input', function() {
  const val = this.value.toLowerCase();
  resultsBox.innerHTML = '';
  if (!val) { resultsBox.style.display = 'none'; return; }

  const filtered = usuarios.filter(u => u.nome.toLowerCase().includes(val));

  if (filtered.length > 0) {
    filtered.forEach(u => {
      const div = document.createElement('div');
      div.classList.add('result-item');
      div.textContent = u.nome;
      div.onclick = function() {
        input.value = u.nome;
        hiddenId.value = u.id;
        resultsBox.style.display = 'none';
        document.getElementById('formBusca').submit();
      };
      resultsBox.appendChild(div);
    });
    resultsBox.style.display = 'block';
  } else {
    resultsBox.style.display = 'none';
  }
});

document.addEventListener('click', function(e) {
  if (e.target !== input) resultsBox.style.display = 'none';
});
</script>

<?php include("../includes/footer.php"); ?>
