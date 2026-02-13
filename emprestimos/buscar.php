<?php
include("../auth/auth_guard.php");
include("../conexao.php");

$hoje = date('Y-m-d');

function badgeStatusEmprestimo(bool $devolvido, bool $atrasado, bool $cancelado): string {
  if ($cancelado) return "<span class='badge-status badge-cancel'><i class='bi bi-x-circle'></i> Cancelado</span>";
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
    SELECT e.id, e.data_emprestimo, e.data_prevista, IFNULL(e.cancelado,0) AS cancelado, e.cancelado_em, e.cancelado_motivo, u.nome AS usuario_nome
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
      l.id AS livro_id,
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

  $abertos = 0;
  $total = 0;

  mysqli_data_seek($ri, 0);
  while ($x = mysqli_fetch_assoc($ri)) {
    $total++;
    if ((int)$x['devolvido'] === 0) $abertos++;
  }

  $cancelado = ((int)($h['cancelado'] ?? 0) === 1);
  $devolvido = (!$cancelado && $abertos === 0);
  $prevista = $h['data_prevista'] ?? null;
  $atrasado = (!$cancelado && !$devolvido && !empty($prevista) && $prevista < $hoje);

  ob_start();
  ?>
  <div class="mb-2">
    <div class="text-muted small">Usuário</div>
    <div class="fw-semibold"><?= htmlspecialchars($h['usuario_nome'] ?? '—') ?></div>
  </div>

  <?php if (((int)($h['cancelado'] ?? 0)) === 1) { ?>
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
      <div><?= badgeStatusEmprestimo($devolvido, $atrasado, $cancelado) ?></div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-clean align-middle mb-0">
      <thead>
        <tr>
          <th>Livro</th>
          <th>ISBN</th>
          <th>Status</th>
          <th>Devolução</th>
          <th class="text-end">Ação</th>
        </tr>
      </thead>
      <tbody>
        <?php
        mysqli_data_seek($ri, 0);
        if ($total === 0) {
          echo "<tr><td colspan='5' class='text-center text-muted py-3'>Nenhum item.</td></tr>";
        }
        while ($x = mysqli_fetch_assoc($ri)) {
          $iDev = ((int)$x['devolvido'] === 1);
        ?>
          <tr>
            <td class="fw-semibold">
              <?= htmlspecialchars($x['titulo'] ?? '—') ?>
              <div class="text-muted small"><?= htmlspecialchars($x['autor'] ?? '') ?></div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($x['ISBN'] ?? '-') ?></td>
            <td>
              <?php if ($iDev) { ?>
                <span class="badge-status badge-done">Devolvido</span>
              <?php } else { ?>
                <span class="badge-status badge-open">Aberto</span>
              <?php } ?>
            </td>
            <td class="text-muted"><?= htmlspecialchars($x['data_devolucao'] ?? '-') ?></td>
            <td class="text-end">
              <?php if (!$iDev && !$cancelado) { ?>
                <button class="btn btn-sm btn-outline-success" style="border-radius:12px;"
                        onclick="devolverItem(<?= (int)$x['item_id'] ?>)">
                  <i class="bi bi-check2-circle me-1"></i> Devolver
                </button>
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
  $footer .= "<button type='button' class='btn btn-outline-secondary' data-bs-dismiss='modal' style='border-radius:999px;'>Fechar</button>";

  if (!$cancelado && !$devolvido) {
    $footer .= "<a class='btn btn-outline-warning' style='border-radius:999px;' href='editar.php?id=".(int)$id."'>
                  <i class='bi bi-x-circle me-1'></i> Cancelar
                </a>";
  }

  if (!$cancelado && !$devolvido) {
    $footer .= "<a class='btn btn-outline-primary' style='border-radius:999px;' href='renovar.php?id=".(int)$id."'>
                  <i class='bi bi-arrow-repeat me-1'></i> Renovar
                </a>";
  }

  if (!$cancelado && !$devolvido) {
    $footer .= "<a class='btn btn-success' style='border-radius:999px;' href='devolver.php?id=".(int)$id."'>
                  <i class='bi bi-check2-circle me-1'></i> Devolver tudo
                </a>";
  }

  echo json_encode([
    'ok' => true,
    'html' => $html,
    'footer' => $footer
  ], JSON_UNESCAPED_UNICODE);
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

// por padrão, não mostra empréstimos cancelados (só quando filtrar por "cancelado")
if ($status !== 'cancelado') {
  $where[] = "(IFNULL(e.cancelado,0) = 0)";
}

if ($q !== '') {
  $where[] = "(u.nome LIKE ? OR l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?)";
  $like = "%{$q}%";
  $params = array_merge($params, [$like, $like, $like, $like]);
  $types .= "ssss";
}

$having = [];

if ($status === 'atrasado') {
  $where[] = "(e.data_prevista IS NOT NULL AND e.data_prevista < ?)";
  $params[] = $hoje;
  $types .= "s";
  $having[] = "SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) > 0";
} elseif ($status === 'aberto') {
  $where[] = "(e.data_prevista IS NULL OR e.data_prevista >= ?)";
  $params[] = $hoje;
  $types .= "s";
  $having[] = "SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) > 0";
} elseif ($status === 'devolvido') {
  $having[] = "SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) = 0";
} elseif ($status === 'cancelado') {
  $where[] = "(IFNULL(e.cancelado,0) = 1)";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";
$havingSql = count($having) ? ("HAVING " . implode(" AND ", $having)) : "";

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

$total_paginas = (int)ceil($total / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina > $total_paginas) $pagina = $total_paginas;

$sql = "
  SELECT
    e.id,
    e.data_emprestimo,
    e.data_prevista,
    IFNULL(e.cancelado,0) AS cancelado,
    e.cancelado_em,
    u.nome AS usuario_nome,
    COUNT(ei.id) AS qtd_itens,
    SUM(CASE WHEN ei.devolvido = 0 THEN 1 ELSE 0 END) AS abertos,
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

$inicio = $offset + 1;
$fim = min($offset + $por_pagina, $total);

ob_start();
while ($e = mysqli_fetch_assoc($r)) {
  $id = (int)$e['id'];

  $qtd = (int)($e['qtd_itens'] ?? 0);
  $abertos = (int)($e['abertos'] ?? 0);

  $cancelado = ((int)($e['cancelado'] ?? 0) === 1);
  $devolvido = (!$cancelado && $abertos === 0);
  $prevista = $e['data_prevista'] ?? null;
  $atrasado = (!$cancelado && !$devolvido && !empty($prevista) && $prevista < $hoje);

  $usuario = (string)($e['usuario_nome'] ?? '—');
  $titulos = (string)($e['livros_titulos'] ?? '—');

  // ✅ mostra quantos livros ainda estão em aberto quando o empréstimo está em aberto
  if ($cancelado) {
    $livroMostrar = ($qtd === 1) ? $titulos : ($qtd . " livros");
  } elseif ($devolvido) {
    $livroMostrar = ($qtd === 1) ? $titulos : ($qtd . " livros");
  } else {
    $livroMostrar = ($abertos === 1) ? "1 livro" : ($abertos . " livros");
  }

  $usuarioAttr = htmlspecialchars($usuario, ENT_QUOTES);
  $livroAttr   = htmlspecialchars($livroMostrar, ENT_QUOTES);
?>
  <tr class="row-click" data-emprestimo-id="<?= $id ?>">
    <td class="text-muted fw-semibold">#<?= $id ?></td>
    <td class="fw-semibold"><?= htmlspecialchars($usuario) ?></td>
    <td><?= htmlspecialchars($livroMostrar) ?></td>
    <td><?= htmlspecialchars($e['data_emprestimo'] ?? '-') ?></td>
    <td><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></td>
    <td><?= htmlspecialchars($e['ultima_devolucao'] ?? '-') ?></td>

    <td><?= badgeStatusEmprestimo($devolvido, $atrasado, $cancelado) ?></td>

    <td class="text-end">
      <?php if (!$devolvido && !$cancelado) { ?>
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
           href="editar.php?id=<?= $id ?>"
           title="Cancelar"
           data-stop-row="1">
          <i class="bi bi-x-circle"></i>
        </a>
      <?php } ?>

      <a class="icon-btn icon-btn--del"
         href="#"
         data-action="excluir"
         data-id="<?= $id ?>"
         data-livro="<?= $livroAttr ?>"
         data-usuario="<?= $usuarioAttr ?>"
         title="Excluir"
         data-stop-row="1">
        <i class="bi bi-trash"></i>
      </a>
    </td>
  </tr>
<?php
}
$htmlRows = ob_get_clean();

$paginacao = "
  <a class='btn btn-outline-secondary btn-sm ".($pagina <= 1 ? "disabled" : "")."' href='?p=".max(1,$pagina-1)."'>Anterior</a>
  <span class='btn btn-dark btn-sm' style='pointer-events:none;'>".$pagina."</span>
  <a class='btn btn-outline-secondary btn-sm ".($pagina >= $total_paginas ? "disabled" : "")."' href='?p=".min($total_paginas,$pagina+1)."'>Próxima</a>
";

$resumo = "Mostrando {$inicio}–{$fim} de {$total}";

echo json_encode([
  'ok' => true,
  'html' => $htmlRows ?: "<tr><td colspan='8' class='text-center text-muted py-4'>Nenhum empréstimo encontrado.</td></tr>",
  'paginacao' => $paginacao,
  'resumo' => $resumo
], JSON_UNESCAPED_UNICODE);
