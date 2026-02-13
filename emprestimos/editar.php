<?php
$titulo_pagina = "Cancelar Empréstimo";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");

$hoje = date('Y-m-d');
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  flash_set('danger', 'Empréstimo inválido.');
  header("Location: listar.php");
  exit;
}

// ===============================
// Carrega empréstimo + usuário + totais
// ===============================
$stmt = mysqli_prepare($conn, "
  SELECT
    e.id,
    e.data_emprestimo,
    e.data_prevista,
    IFNULL(e.cancelado,0) AS cancelado,
    e.cancelado_em,
    e.cancelado_motivo,
    u.nome AS usuario_nome,
    COUNT(ei.id) AS qtd_itens,
    SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) AS abertos
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  WHERE e.id = ?
  GROUP BY e.id
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$e = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$e) {
  flash_set('danger', 'Empréstimo não encontrado.');
  header("Location: listar.php");
  exit;
}

$cancelado = ((int)($e['cancelado'] ?? 0) === 1);
$abertos = (int)($e['abertos'] ?? 0);
$devolvido = (!$cancelado && $abertos === 0);

// ===============================
// POST: cancelar
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($cancelado) {
    flash_set('warning', 'Este empréstimo já está cancelado.');
    header("Location: listar.php");
    exit;
  }
  if ($devolvido) {
    flash_set('warning', 'Este empréstimo já foi devolvido. Não faz sentido cancelar agora.');
    header("Location: listar.php");
    exit;
  }

  $motivo = trim($_POST['motivo'] ?? '');
  if (mb_strlen($motivo) > 255) $motivo = mb_substr($motivo, 0, 255);

  mysqli_begin_transaction($conn);

  try {
    // marca o empréstimo como cancelado
    $stmtC = mysqli_prepare($conn, "
      UPDATE emprestimos
      SET cancelado = 1,
          cancelado_em = NOW(),
          cancelado_motivo = ?
      WHERE id = ?
      LIMIT 1
    ");
    mysqli_stmt_bind_param($stmtC, "si", $motivo, $id);
    mysqli_stmt_execute($stmtC);

    // fecha os itens que ainda estavam em aberto
    $stmtI = mysqli_prepare($conn, "
      UPDATE emprestimo_itens
      SET devolvido = 1,
          data_devolucao = ?
      WHERE emprestimo_id = ?
        AND devolvido = 0
    ");
    mysqli_stmt_bind_param($stmtI, "si", $hoje, $id);
    mysqli_stmt_execute($stmtI);

    mysqli_commit($conn);

    flash_set('success', 'Empréstimo cancelado com sucesso.');
    header("Location: listar.php");
    exit;

  } catch (Throwable $ex) {
    mysqli_rollback($conn);
    flash_set('danger', 'Erro ao cancelar o empréstimo.');
    header("Location: listar.php");
    exit;
  }
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Cancelar Empréstimo #<?= (int)$e['id'] ?></h2>

      <a class="btn btn-pill" href="listar.php">
        <i class="bi bi-arrow-left"></i>
        Voltar
      </a>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:16px;">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="text-muted small">Usuário</div>
            <div class="fw-semibold"><?= htmlspecialchars($e['usuario_nome'] ?? '-') ?></div>

            <div class="mt-3 row g-2">
              <div class="col-6">
                <div class="text-muted small">Empréstimo</div>
                <div><?= htmlspecialchars($e['data_emprestimo'] ?? '-') ?></div>
              </div>
              <div class="col-6">
                <div class="text-muted small">Prevista</div>
                <div><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></div>
              </div>
            </div>

            <div class="mt-3 text-muted small">
              Itens: <b><?= (int)($e['qtd_itens'] ?? 0) ?></b> |
              Em aberto: <b><?= (int)$abertos ?></b>
            </div>
          </div>

          <div class="col-lg-6">
            <?php if ($cancelado) { ?>
              <div class="alert alert-warning" style="border-radius:16px;">
                <div class="fw-semibold mb-1"><i class="bi bi-x-circle me-1"></i> Já está cancelado</div>
                <div class="small text-muted">
                  <?= htmlspecialchars($e['cancelado_em'] ?? '-') ?>
                  <?php if (!empty($e['cancelado_motivo'] ?? '')) { ?>
                    | <?= htmlspecialchars($e['cancelado_motivo']) ?>
                  <?php } ?>
                </div>
              </div>
            <?php } elseif ($devolvido) { ?>
              <div class="alert alert-success" style="border-radius:16px;">
                <div class="fw-semibold mb-1"><i class="bi bi-check-circle me-1"></i> Já foi devolvido</div>
                <div class="small text-muted">Esse empréstimo está encerrado. Cancelar aqui não ajuda muito.</div>
              </div>
            <?php } else { ?>
              <div class="alert alert-warning" style="border-radius:16px;">
                <div class="fw-semibold mb-1">Atenção</div>
                <div class="small text-muted">
                  Cancelar serve pra quando o empréstimo foi lançado errado.
                  Isso vai encerrar os itens que ainda estavam em aberto.
                </div>
              </div>

              <form method="POST">
                <div class="mb-3">
                  <label class="form-label fw-semibold">Motivo (opcional)</label>
                  <input type="text" name="motivo" class="form-control" maxlength="255"
                         placeholder="Ex: lançado no usuário errado, livro errado..."
                         value="<?= htmlspecialchars($_POST['motivo'] ?? '') ?>">
                </div>

                <button class="btn btn-warning w-100" style="border-radius:999px;"
                        onclick="return confirm('Confirmar o cancelamento deste empréstimo?');">
                  <i class="bi bi-x-circle me-1"></i> Cancelar empréstimo
                </button>
              </form>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php include("../includes/footer.php"); ?>
