<?php
$titulo_pagina = "Histórico por usuário";
include("../conexao.php");
include("../includes/header.php");

$usuarios = mysqli_query($conn, "SELECT id, nome FROM usuarios ORDER BY nome ASC");

$uid = (int)($_GET['usuario'] ?? 0);
$historico = null;

$kpi = ['total' => 0, 'devolvidos' => 0, 'abertos' => 0, 'atrasados' => 0];

if ($uid > 0) {
    // KPIs do usuário
    $stmt = mysqli_prepare($conn, "
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN devolvido=1 THEN 1 ELSE 0 END) AS devolvidos,
      SUM(CASE WHEN devolvido=0 THEN 1 ELSE 0 END) AS abertos,
      SUM(CASE WHEN devolvido=0 AND data_prevista IS NOT NULL AND data_prevista < CURDATE() THEN 1 ELSE 0 END) AS atrasados
    FROM emprestimos
    WHERE id_usuario = ?
  ");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: $kpi;

    // Histórico detalhado
    $stmt = mysqli_prepare($conn, "
    SELECT l.titulo, e.data_emprestimo, e.data_prevista, e.data_devolucao, e.devolvido
    FROM emprestimos e
    JOIN livros l ON l.id = e.id_livro
    WHERE e.id_usuario = ?
    ORDER BY e.data_emprestimo DESC
  ");
    mysqli_stmt_bind_param($stmt, "i", $uid);
    mysqli_stmt_execute($stmt);
    $historico = mysqli_stmt_get_result($stmt);
}

$labels = ["Aberto", "Devolvido", "Atrasado"];
$values = [
    (int)$kpi['abertos'],
    (int)$kpi['devolvidos'],
    (int)$kpi['atrasados'],
];
?>

<div class="container my-4">
    <div class="page-card">
        <div class="page-card__head">
            <h2 class="page-card__title m-0">Histórico por usuário</h2>
            <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>

        <form class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label">Selecione um usuário</label>
                <select name="usuario" class="form-select" onchange="this.form.submit()">
                    <option value="">Escolha...</option>
                    <?php while ($u = mysqli_fetch_assoc($usuarios)) { ?>
                        <option value="<?= (int)$u['id'] ?>" <?= ($uid == (int)$u['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['nome']) ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </form>

        <?php if ($uid > 0) { ?>
            <!-- KPIs -->
            <div class="report-kpis mt-4">
                <div class="report-card">
                    <div class="report-card__top"><i class="bi bi-list-check"></i><span>Total</span></div>
                    <div class="report-card__num"><?= (int)$kpi['total'] ?></div>
                    <div class="report-card__foot">registros</div>
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

            <!-- Gráfico -->
            <div class="report-chart mt-4">
                <div class="report-chart__head">
                    <strong>Status dos empréstimos</strong>
                    <span class="text-muted small">usuário selecionado</span>
                </div>
                <div class="chart-wrap">
                    <canvas id="chartStatusUsuario"></canvas>
                </div>
            </div>

            <!-- Tabela -->
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
                            $hoje = date('Y-m-d');
                            while ($h = mysqli_fetch_assoc($historico)) {
                                $devolvido = (int)$h['devolvido'];
                                $prev = $h['data_prevista'] ?? null;
                                $atrasado = ($devolvido === 0 && !empty($prev) && $prev < $hoje);
                            ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($h['titulo']) ?></td>
                                    <td><?= htmlspecialchars($h['data_emprestimo']) ?></td>
                                    <td><?= htmlspecialchars($h['data_prevista'] ?? '-') ?></td>
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
                            <?php if ((int)$kpi['total'] === 0) { ?>
                                <tr>
                                    <td colspan="4" class="text-muted">Nenhum registro para esse usuário.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script>
                const labels = <?= json_encode($labels) ?>;
                const values = <?= json_encode($values) ?>;

                new Chart(document.getElementById('chartStatusUsuario'), {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{
                            data: values
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            </script>
        <?php } ?>

    </div>
</div>

<?php include("../includes/footer.php"); ?>