<?php
include("../conexao.php");

header('Content-Type: application/json; charset=utf-8');

function esc($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$por_pagina = 10;
$q = trim($_GET['q'] ?? '');
$p = (int)($_GET['p'] ?? 1);
if ($p < 1) $p = 1;

$offset = ($p - 1) * $por_pagina;

$where = "";
$params = [];
$types = "";

if ($q !== "") {
  $where = "WHERE l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?";
  $like = "%{$q}%";
  $params = [$like, $like, $like];
  $types = "sss";
}

/* total */
$sqlCount = "SELECT COUNT(*) AS total FROM livros l $where";
$stmt = mysqli_prepare($conn, $sqlCount);
if ($types) mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);

$paginas = (int)ceil($total / $por_pagina);
if ($paginas < 1) $paginas = 1;
if ($p > $paginas) $p = $paginas;

/* dados (com categoria via JOIN) */
$sql = "
  SELECT
    l.id, l.titulo, l.autor, l.ano_publicacao, l.ISBN,
    l.qtd_total, l.qtd_disp, l.disponivel,
    c.codigo AS cdd_codigo, c.descricao AS cdd_descricao
  FROM livros l
  LEFT JOIN cdd c ON c.id = l.categoria
  $where
  ORDER BY l.id DESC
  LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);

if ($types) {
  $types2 = $types . "ii";
  $params2 = array_merge($params, [$por_pagina, $offset]);
  mysqli_stmt_bind_param($stmt, $types2, ...$params2);
} else {
  mysqli_stmt_bind_param($stmt, "ii", $por_pagina, $offset);
}

mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

/* monta rows_html */
$rows_html = '';
while ($l = mysqli_fetch_assoc($r)) {
  $id = (int)$l['id'];
  $titulo = esc($l['titulo']);
  $autor  = esc($l['autor'] ?? '-');
  $ano    = esc($l['ano_publicacao'] ?? '-');
  $isbn   = esc($l['ISBN'] ?? '-');

  $qtd_total = (int)($l['qtd_total'] ?? 1);
  $qtd_disp  = (int)($l['qtd_disp'] ?? 0);

  $cat = '-';
  if (!empty($l['cdd_codigo']) || !empty($l['cdd_descricao'])) {
    $cat = esc(trim(($l['cdd_codigo'] ?? '') . ' - ' . ($l['cdd_descricao'] ?? '')));
  }

  // badge do disponível: mostra X/Y
  $badge = ($qtd_disp > 0)
    ? "<span class='badge-soft-ok'>{$qtd_disp}/{$qtd_total}</span>"
    : "<span class='badge-soft-no'>{$qtd_disp}/{$qtd_total}</span>";

  $rows_html .= "
    <tr>
      <td class='text-muted fw-semibold'>#{$id}</td>
      <td class='fw-semibold'>{$titulo}</td>
      <td>{$autor}</td>
      <td class='text-muted small'>{$cat}</td>
      <td>{$ano}</td>
      <td class='text-muted small'>{$isbn}</td>
      <td>{$badge}</td>
      <td class='text-end'>
        <a class='icon-btn icon-btn--edit' href='editar.php?id={$id}' title='Editar'>
          <i class='bi bi-pencil'></i>
        </a>
        <a class='icon-btn icon-btn--del' href='excluir.php?id={$id}'
           onclick=\"return confirm('Excluir este livro?')\" title='Excluir'>
          <i class='bi bi-trash'></i>
        </a>
      </td>
    </tr>
  ";
}

/* paginação HTML simples (mantendo seu esquema data-page) */
$pagination_html = '';
if ($paginas > 1) {
  $pagination_html .= "<nav><ul class='pagination justify-content-center mb-0'>";

  $prev = $p - 1;
  $next = $p + 1;

  $disabledPrev = ($p <= 1) ? " disabled" : "";
  $disabledNext = ($p >= $paginas) ? " disabled" : "";

  $pagination_html .= "
    <li class='page-item{$disabledPrev}'>
      <a class='page-link' href='#' data-page='{$prev}' aria-label='Anterior'>&laquo;</a>
    </li>
  ";

  // janela de páginas
  $start = max(1, $p - 2);
  $end   = min($paginas, $p + 2);

  for ($i = $start; $i <= $end; $i++) {
    $active = ($i === $p) ? " active" : "";
    $pagination_html .= "
      <li class='page-item{$active}'>
        <a class='page-link' href='#' data-page='{$i}'>{$i}</a>
      </li>
    ";
  }

  $pagination_html .= "
    <li class='page-item{$disabledNext}'>
      <a class='page-link' href='#' data-page='{$next}' aria-label='Próxima'>&raquo;</a>
    </li>
  ";

  $pagination_html .= "</ul></nav>";
}

echo json_encode([
  'rows_html' => $rows_html,
  'pagination_html' => $pagination_html,
  'total' => $total,
  'page' => $p,
  'pages' => $paginas
]);
