<?php
// emprestimos/buscar.php
// Retorna JSON com:
// - rows: HTML das linhas da tabela (tbody)
// - pagination: HTML da paginação
// - summary: texto de resumo

include("../auth/auth_guard.php");
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

$hoje = date('Y-m-d');

// ===============================
// PAGINAÇÃO
// ===============================
$por_pagina = 10;
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;
$offset = ($pagina - 1) * $por_pagina;

// ===============================
// FILTROS
// ===============================
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

// ===============================
// MONTA WHERE + PARAMS (prepared statements)
// ===============================
$where = [];
$params = [];
$types = "";

// Busca por usuário ou livro
if ($q !== '') {
  $where[] = "(u.nome LIKE ? OR l.titulo LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $types .= "ss";
}

// Filtro por status
if ($status === 'devolvido') {
  $where[] = "e.devolvido = 1";
} elseif ($status === 'atrasado') {
  $where[] = "(e.devolvido = 0 AND e.data_prevista IS NOT NULL AND e.data_prevista < ?)";
  $params[] = $hoje;
  $types .= "s";
} elseif ($status === 'aberto') {
  $where[] = "(e.devolvido = 0 AND (e.data_prevista IS NULL OR e.data_prevista >= ?))";
  $params[] = $hoje;
  $types .= "s";
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

// ===============================
// COUNT TOTAL (pra paginação)
// ===============================
$sqlCount = "
  SELECT COUNT(*) AS total
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN livros l ON l.id = e.id_livro
  $whereSql
";
$stmtC = mysqli_prepare($conn, $sqlCount);
if ($types !== "") mysqli_stmt_bind_param($stmtC, $types, ...$params);
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
$total = (int)(mysqli_fetch_assoc($resC)['total'] ?? 0);

// Ajusta página caso passe do limite
$total_paginas = max(1, (int)ceil($total / $por_pagina));
if ($pagina > $total_paginas) $pagina = $total_paginas;
$offset = ($pagina - 1) * $por_pagina;

// ===============================
// SELECT PAGINADO
// ===============================
$sql = "
  SELECT
    e.id,
    e.data_emprestimo,
    e.data_prevista,
    e.data_devolucao,
    e.devolvido,
    u.nome AS usuario_nome,
    l.titulo AS livro_titulo
  FROM emprestimos e
  JOIN usuarios u ON u.id = e.id_usuario
  JOIN livros l ON l.id = e.id_livro
  $whereSql
  ORDER BY e.id DESC
  LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);

// bind filtros + limit/offset
$types2 = $types . "ii";
$params2 = $params;
$params2[] = $por_pagina;
$params2[] = $offset;

mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

// ===============================
// MONTA HTML DAS LINHAS (tbody)
// Importante: aqui a gente gera os botões com data-action
// pra abrir os MODAIS no listar.php
// ===============================
$rows = "";

if ($total === 0) {
  $rows = '<tr><td colspan="8" class="text-center text-muted py-4">Nenhum empréstimo encontrado.</td></tr>';
} else {
  while ($e = mysqli_fetch_assoc($r)) {
    $id = (int)$e['id'];

    $devolvido = (int)$e['devolvido'];
    $prevista  = $e['data_prevista'] ?? null;
    $atrasado  = ($devolvido === 0 && !empty($prevista) && $prevista < $hoje);

    // Escapes:
    // - HTML normal nas células
    // - ENT_QUOTES nos data-* (pra não quebrar atributo)
    $usuarioCell = htmlspecialchars($e['usuario_nome']);
    $livroCell   = htmlspecialchars($e['livro_titulo']);

    $usuarioAttr = htmlspecialchars($e['usuario_nome'], ENT_QUOTES);
    $livroAttr   = htmlspecialchars($e['livro_titulo'], ENT_QUOTES);

    ob_start();
?>
    <tr>
      <td class="text-muted fw-semibold">#<?= $id ?></td>
      <td class="fw-semibold"><?= $usuarioCell ?></td>
      <td><?= $livroCell ?></td>

      <td><?= htmlspecialchars($e['data_emprestimo']) ?></td>
      <td><?= htmlspecialchars($e['data_prevista'] ?? '-') ?></td>
      <td><?= htmlspecialchars($e['data_devolucao'] ?? '-') ?></td>

      <td>
        <?php if ($devolvido === 1) { ?>
          <span class="badge-status badge-done">
            <i class="bi bi-check-circle"></i> Devolvido
          </span>
        <?php } elseif ($atrasado) { ?>
          <span class="badge-status badge-late">
            <i class="bi bi-exclamation-circle"></i> Atrasado
          </span>
        <?php } else { ?>
          <span class="badge-status badge-open">
            <i class="bi bi-clock-history"></i> Aberto
          </span>
        <?php } ?>
      </td>

      <td class="text-end">
        <?php if ($devolvido === 0) { ?>
          <!-- Editar (normal) -->
          <a class="icon-btn icon-btn--edit"
             href="editar.php?id=<?= $id ?>"
             title="Editar">
            <i class="bi bi-pencil"></i>
          </a>

          <!-- Devolver (abre modal) -->
          <a class="icon-btn icon-btn--edit"
             href="#"
             data-action="devolver"
             data-id="<?= $id ?>"
             data-livro="<?= $livroAttr ?>"
             data-usuario="<?= $usuarioAttr ?>"
             title="Devolver">
            <i class="bi bi-check2-circle"></i>
          </a>
        <?php } ?>

        <!-- Excluir (abre modal) -->
        <a class="icon-btn icon-btn--del"
           href="#"
           data-action="excluir"
           data-id="<?= $id ?>"
           data-livro="<?= $livroAttr ?>"
           data-usuario="<?= $usuarioAttr ?>"
           title="Excluir">
          <i class="bi bi-trash"></i>
        </a>
      </td>
    </tr>
<?php
    $rows .= ob_get_clean();
  }
}

// ===============================
// PAGINAÇÃO (HTML)
// ===============================
function montaLink($p, $q, $status) {
  $qs = [];
  if ($p) $qs['p'] = $p;
  if ($q !== '') $qs['q'] = $q;
  if ($status !== '') $qs['status'] = $status;
  return "listar.php?" . http_build_query($qs);
}

$pagination = '<ul class="pagination pagination-green mb-0">';

$pagination .= '<li class="page-item ' . (($pagina <= 1) ? 'disabled' : '') . '">
  <a class="page-link" href="' . montaLink($pagina - 1, $q, $status) . '">Anterior</a>
</li>';

$janela = 2;
$ini = max(1, $pagina - $janela);
$fimPag = min($total_paginas, $pagina + $janela);

for ($p = $ini; $p <= $fimPag; $p++) {
  $active = ($p === $pagina) ? 'active' : '';
  $pagination .= '<li class="page-item ' . $active . '">
    <a class="page-link" href="' . montaLink($p, $q, $status) . '">' . $p . '</a>
  </li>';
}

$pagination .= '<li class="page-item ' . (($pagina >= $total_paginas) ? 'disabled' : '') . '">
  <a class="page-link" href="' . montaLink($pagina + 1, $q, $status) . '">Próxima</a>
</li>';

$pagination .= '</ul>';

// ===============================
// RESUMO
// ===============================
$inicio = ($total === 0) ? 0 : ($offset + 1);
$fim = min($offset + $por_pagina, $total);
$summary = "Mostrando {$inicio}–{$fim} de {$total} empréstimos";

// ===============================
// SAÍDA JSON
// ===============================
echo json_encode([
  "rows" => $rows,
  "pagination" => $pagination,
  "summary" => $summary
], JSON_UNESCAPED_UNICODE);
