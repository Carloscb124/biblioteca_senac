<?php
include("../auth/auth_guard.php");
include("../conexao.php");

$hoje = date('Y-m-d');

function badgeStatusEmprestimo(bool $devolvido, bool $atrasado, bool $cancelado, bool $temPerdido): string {
  if ($cancelado) return "<span class='badge-status badge-cancel'><i class='bi bi-x-circle'></i> Cancelado</span>";
  if ($temPerdido) return "<span class='badge-status badge-lost'><i class='bi bi-exclamation-triangle'></i> Perdido</span>";
  if ($devolvido) return "<span class='badge-status badge-done'><i class='bi bi-check-circle'></i> Devolvido</span>";
  if ($atrasado)  return "<span class='badge-status badge-late'><i class='bi bi-exclamation-circle'></i> Atrasado</span>";
  return "<span class='badge-status badge-open'><i class='bi bi-clock-history'></i> Aberto</span>";
}

/* =========================================================
   DETALHES (modal)
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] === 'detalhes') {
  header('Content-Type: application/json; charset=utf-8');

  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    echo json_encode(['ok' => false, 'html' => "<div class='text-muted'>ID inválido.</div>", 'footer' => ""], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmtH = mysqli_prepare($conn, "
    SELECT
      e.id,
      e.data_emprestimo,
      e.data_prevista,
      IFNULL(e.cancelado,0) AS cancelado,
      e.cancelado_em,
      e.cancelado_motivo,
      u.nome AS usuario_nome
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.id_usuario
    WHERE e.id = ?
    LIMIT 1
  ");
  mysqli_stmt_bind_param($stmtH, "i", $id);
  mysqli_stmt_execute($stmtH);
  $h = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtH));

  if (!$h) {
    echo json_encode(['ok' => false, 'html' => "<div class='text-muted'>Empréstimo não encontrado.</div>", 'footer' => ""], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmtI = mysqli_prepare($conn, "
    SELECT
      ei.id AS item_id,
      ei.devolvido,
      ei.data_devolucao,
      IFNULL(ei.perdido,0) AS perdido,
      ei.data_perdido,
      l.titulo,
      l.autor,
      l.ISBN
    FROM emprestimo_itens ei
    JOIN livros l ON l.id = ei.id_livro
    WHERE ei.emprestimo_id = ?
    ORDER BY l.titulo
  ");
  mysqli_stmt_bind_param($stmtI, "i", $id);
  mysqli_stmt_execute($stmtI);
  $ri = mysqli_stmt_get_result($stmtI);

  $itens = [];
  $abertos = 0;
  $perdidos = 0;

  while ($x = mysqli_fetch_assoc($ri)) {
    $iDev = ((int)$x['devolvido'] === 1);
    $iPer = ((int)$x['perdido'] === 1);

    if (!$iDev && !$iPer) $abertos++;
    if ($iPer) $perdidos++;

    $itens[] = $x;
  }

  $cancelado = ((int)($h['cancelado'] ?? 0) === 1);
  $devolvido = (!$cancelado && $abertos === 0);      // fechado (pode ser devolvido OU perdido)
  $temPerdido = (!$cancelado && $perdidos > 0);

  $prevista = $h['data_prevista'] ?? null;
  $atrasado = (!$cancelado && !$devolvido && !empty($prevista) && $prevista < $hoje);

  ob_start();
  ?>
  <div class="mb-2">
    <div class="text-muted small">Usuário</div>
    <div class="fw-semibold"><?= htmlspecialchars($h['usuario_nome'] ?? '—') ?></div>
  </div>

  <?php if ($cancelado) { ?>
    <div class="alert alert-warning" style="border-radius:16px;">
      <div class="fw-semibold mb-1"><i class="bi bi-x-circle me-1"></i> Empréstimo cancelado</div>
      <div class="small text-muted">
        <?= htmlspecialchars($h['cancelado_em'] ?? '-') ?>
        <?= !empty($h['cancelado_motivo'] ?? '') ? ' | ' . htmlspecialchars($h['cancelado_motivo']) : '' ?>
      </div>
    </div>
  <?php } ?>

  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <div class="text-muted small">Empréstimo</div>
      <div><?= htmlspecialchars($h['data_emprestimo'] ?? '-') ?></div>
    </div>
    <div class="col-md-4">
      <div class="text-muted small">Prevista</div>
      <div><?= htmlspecialchars($h['data_prevista'] ?? '-') ?></div>
    </div>
    <div class="col-md-4">
      <div class="text-muted small">Status</div>
      <div><?= badgeStatusEmprestimo($devolvido, $atrasado, $cancelado, $temPerdido) ?></div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-clean align-middle mb-0">
      <thead>
        <tr>
          <th>Livro</th>
          <th>ISBN</th>
          <th>Status</th>
          <th>Data</th>
          <th class="text-end">Ação</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($itens) === 0) { ?>
          <tr><td colspan="5" class="text-center text-muted py-3">Nenhum item.</td></tr>
        <?php } ?>

        <?php foreach ($itens as $x) {
          $iDev = ((int)$x['devolvido'] === 1);
          $iPer = ((int)$x['perdido'] === 1);
          $isOpen = (!$iDev && !$iPer);

          if ($iDev) {
            $badge = "<span class='badge-status badge-done'>Devolvido</span>";
            $dataStatus = $x['data_devolucao'] ?? '-';
          } elseif ($iPer) {
            $badge = "<span class='badge-status badge-lost'><i class='bi bi-exclamation-triangle'></i> Perdido</span>";
            $dataStatus = $x['data_perdido'] ?? '-';
          } else {
            $badge = "<span class='badge-status badge-open'>Aberto</span>";
            $dataStatus = '-';
          }
        ?>
          <tr>
            <td class="fw-semibold">
              <?= htmlspecialchars($x['titulo'] ?? '—') ?>
              <div class="text-muted small"><?= htmlspecialchars($x['autor'] ?? '') ?></div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($x['ISBN'] ?? '-') ?></td>
            <td><?= $badge ?></td>
            <td class="text-muted"><?= htmlspecialchars($dataStatus) ?></td>
            <td class="text-end">
              <?php if ($isOpen && !$cancelado) { ?>
                <div class="d-flex gap-2 justify-content-end">
                  <button class="btn btn-sm btn-outline-success js-devolver-item"
                          style="border-radius:12px;"
                          data-item-id="<?= (int)$x['item_id'] ?>">
                    <i class="bi bi-check2-circle me-1"></i> Devolver
                  </button>

                  <button class="btn btn-sm btn-outline-danger js-perder-item"
                          style="border-radius:12px;"
                          data-item-id="<?= (int)$x['item_id'] ?>"
                          data-titulo="<?= htmlspecialchars($x['titulo'] ?? '', ENT_QUOTES) ?>">
                    <i class="bi bi-exclamation-triangle me-1"></i> Perdido
                  </button>
                </div>
              <?php } else { ?>
                <span class="text-muted small">—</span>
              <?php } ?>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
  <?php
  $html = ob_get_clean();

  $footer = "";
  if (!$cancelado && !$devolvido) {
    $footer .= "<a class='btn btn-outline-primary' style='border-radius:999px;' href='renovar.php?id=".(int)$id."'>
                  <i class='bi bi-arrow-repeat me-1'></i> Renovar
                </a>";
    $footer .= "<a class='btn btn-success' style='border-radius:999px;' href='devolver.php?id=".(int)$id."'>
                  <i class='bi bi-check2-circle me-1'></i> Devolver tudo
                </a>";
    $footer .= "<a class='btn btn-outline-warning' style='border-radius:999px;' href='excluir.php?id=".(int)$id."'>
                  <i class='bi bi-x-circle me-1'></i> Cancelar
                </a>";
  }

  echo json_encode(['ok' => true, 'html' => $html, 'footer' => $footer], JSON_UNESCAPED_UNICODE);
  exit;
}

/* =========================================================
   LISTAGEM AJAX
   ========================================================= */
header('Content-Type: application/json; charset=utf-8');

$por_pagina = 10;
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $por_pagina;

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = "";

// por padrão, esconde cancelados
$where[] = "(IFNULL(e.cancelado,0) = 0)";

if ($q !== '') {
  $where[] = "(u.nome LIKE ? OR l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?)";
  $like = "%{$q}%";
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types .= "ssss";
}

// expressões
$exprAbertos  = "SUM(CASE WHEN ei.devolvido = 0 AND IFNULL(ei.perdido,0)=0 THEN 1 ELSE 0 END)";
$exprPerdidos = "SUM(CASE WHEN IFNULL(ei.perdido,0)=1 THEN 1 ELSE 0 END)";

$having = [];

// Regras iguais ao listar.php (SSR):
// - atrasado / aberto / devolvido NÃO podem ter perdido
// - perdido = tem pelo menos 1 item perdido
if ($status === 'atrasado') {
  $where[] = "(e.data_prevista IS NOT NULL AND e.data_prevista < ?)";
  $params[] = $hoje;
  $types .= "s";
  $having[] = "{$exprAbertos} > 0";
  $having[] = "{$exprPerdidos} = 0";
} elseif ($status === 'aberto') {
  $where[] = "(e.data_prevista IS NULL OR e.data_prevista >= ?)";
  $params[] = $hoje;
  $types .= "s";
  $having[] = "{$exprAbertos} > 0";
  $having[] = "{$exprPerdidos} = 0";
} elseif ($status === 'devolvido') {
  $having[] = "{$exprAbertos} = 0";
  $having[] = "{$exprPerdidos} = 0";
} elseif ($status === 'perdido') {
  $having[] = "{$exprPerdidos} > 0";
}

$whereSql  = count($where)  ? ("WHERE "  . implode(" AND ", $where))  : "";
$havingSql = count($having) ? ("HAVING " . implode(" AND ", $having)) : "";

// COUNT
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM (
    SELECT e.id
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.id_usuario
    JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
    JOIN livros l ON l.id = ei.id_livro
    $whereSql
    GROUP BY e.id
    $havingSql
  ) t
";
$stmtC = mysqli_prepare($conn, $sqlCount);
if ($types !== "") mysqli_stmt_bind_param($stmtC, $types, ...$params);
mysqli_stmt_execute($stmtC);
$total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC))['total'] ?? 0);

$total_paginas = max(1, (int)ceil($total / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;
$offset = ($pagina - 1) * $por_pagina;

// SELECT
$sql = "
  SELECT
    e.id,
    e.data_emprestimo,
    e.data_prevista,
    u.nome AS usuario_nome,
    COUNT(ei.id) AS qtd_itens,
    {$exprAbertos} AS abertos,
    {$exprPerdidos} AS perdidos,
    MAX(ei.data_devolucao) AS ultima_devolucao,
    GROUP_CONCAT(DISTINCT l.titulo ORDER BY l.titulo SEPARATOR ' | ') AS livros_titulos
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN emprestimo_itens ei ON ei.emprestimo_id = e.id
  JOIN livros l ON l.id = ei.id_livro
  $whereSql
  GROUP BY e.id
  $havingSql
  ORDER BY e.id DESC
  LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $sql);
$types2 = $types . "ii";
$params2 = array_merge($params, [$por_pagina, $offset]);
mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

ob_start();

if ($total === 0) { ?>
  <tr>
    <td colspan="8" class="text-center text-muted py-4">Nenhum empréstimo encontrado.</td>
  </tr>
<?php } else {
  while ($e = mysqli_fetch_assoc($r)) {
    $id = (int)$e['id'];
    $qtd = (int)($e['qtd_itens'] ?? 0);
    $abertos = (int)($e['abertos'] ?? 0);
    $perdidos = (int)($e['perdidos'] ?? 0);

    $encerrado = ($abertos === 0);
    $temPerdido = ($perdidos > 0);

    $prevista = $e['data_prevista'] ?? null;
    $atrasado = (!$encerrado && !empty($prevista) && $prevista < $hoje);

    $titulos = (string)($e['livros_titulos'] ?? '—');

    if ($encerrado) {
      $livroMostrar = ($qtd > 1) ? ($qtd . " livros") : $titulos;
    } else {
      $livroMostrar = ($abertos === 1) ? "1 livro" : ($abertos . " livros");
    }

    $usuarioAttr = htmlspecialchars($e['usuario_nome'] ?? '', ENT_QUOTES);
    $livroAttr   = htmlspecialchars($livroMostrar, ENT_QUOTES);

    // badge
    if ($temPerdido) {
      $badge = "<span class='badge-status badge-lost'><i class='bi bi-exclamation-triangle'></i> Perdido</span>";
    } elseif ($encerrado) {
      $badge = "<span class='badge-status badge-done'><i class='bi bi-check-circle'></i> Devolvido</span>";
    } elseif ($atrasado) {
      $badge = "<span class='badge-status badge-late'><i class='bi bi-exclamation-circle'></i> Atrasado</span>";
    } else {
      $badge = "<span class='badge-status badge-open'><i class='bi bi-clock-history'></i> Aberto</span>";
    }
?>
  <tr class="row-click" data-emprestimo-id="<?= $id ?>">
    <td class="text-muted fw-semibold">#<?= $id ?></td>
    <td class="fw-semibold"><?= htmlspecialchars($e['usuario_nome'] ?? '-') ?></td>
    <td><?= htmlspecialchars($livroMostrar) ?></td>
    <td><?= htmlspecialchars($e['data_emprestimo'] ?? '-') ?></td>
    <td><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></td>
    <td><?= htmlspecialchars($e['ultima_devolucao'] ?? '-') ?></td>
    <td><?= $badge ?></td>

    <td class="text-end">
      <?php if (!$encerrado) { ?>
        <a class="icon-btn icon-btn--edit"
           href="renovar.php?id=<?= $id ?>"
           title="Renovar"
           data-stop-row="1">
          <i class="bi bi-arrow-repeat"></i>
        </a>

        <a class="icon-btn icon-btn--edit"
           href="#"
           title="Detalhes"
           data-action="detalhes"
           data-id="<?= $id ?>"
           data-stop-row="1">
          <i class="bi bi-check2-circle"></i>
        </a>

        <a class="icon-btn icon-btn--del"
           href="#"
           title="Cancelar empréstimo"
           data-action="cancelar"
           data-id="<?= $id ?>"
           data-livro="<?= $livroAttr ?>"
           data-usuario="<?= $usuarioAttr ?>"
           data-stop-row="1">
          <i class="bi bi-x-circle"></i>
        </a>
      <?php } ?>
    </td>
  </tr>
<?php
  }
}

$rows = ob_get_clean();

function montaLinkAjax($p, $q, $status) {
  $qs = [];
  $qs['p'] = max(1, (int)$p);
  if ($q !== '') $qs['q'] = $q;
  if ($status !== '') $qs['status'] = $status;
  return "listar.php?" . http_build_query($qs);
}

$pagination = "<ul class='pagination pagination-green mb-0'>";
$pagination .= "<li class='page-item ".(($pagina <= 1) ? "disabled" : "")."'>
  <a class='page-link' href='".montaLinkAjax($pagina - 1, $q, $status)."'>Anterior</a>
</li>";

$janela = 2;
$ini = max(1, $pagina - $janela);
$fimPag = min($total_paginas, $pagina + $janela);
for ($p = $ini; $p <= $fimPag; $p++) {
  $active = ($p === $pagina) ? "active" : "";
  $pagination .= "<li class='page-item {$active}'>
    <a class='page-link' href='".montaLinkAjax($p, $q, $status)."'>{$p}</a>
  </li>";
}

$pagination .= "<li class='page-item ".(($pagina >= $total_paginas) ? "disabled" : "")."'>
  <a class='page-link' href='".montaLinkAjax($pagina + 1, $q, $status)."'>Próxima</a>
</li>";
$pagination .= "</ul>";

$inicio = ($total === 0) ? 0 : ($offset + 1);
$fim = min($offset + $por_pagina, $total);
$summary = "Mostrando {$inicio}–{$fim} de {$total} empréstimos";

echo json_encode([
  'rows' => $rows,
  'pagination' => $pagination,
  'summary' => $summary
], JSON_UNESCAPED_UNICODE);
