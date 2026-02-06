<?php
include("../auth/auth_guard.php");
include("../conexao.php");

header("Content-Type: application/json; charset=UTF-8");

$por_pagina = 10;
$q = trim($_GET['q'] ?? '');
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;

$offset = ($pagina - 1) * $por_pagina;
$temBusca = ($q !== '');

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function only_digits($v) { return preg_replace('/\D+/', '', (string)$v); }

function cover_url_isbn_small($isbn_digits) {
  if ($isbn_digits === '') return '';
  return "https://covers.openlibrary.org/b/isbn/" . rawurlencode($isbn_digits) . "-S.jpg?default=false";
}
function cover_fallback_svg_datauri() {
  $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='38' height='56'>
    <rect width='100%' height='100%' fill='#f1ece2'/>
    <text x='50%' y='50%' dominant-baseline='middle' text-anchor='middle'
      font-size='10' fill='#8a7f73'>Sem capa</text>
  </svg>";
  return "data:image/svg+xml;utf8," . rawurlencode($svg);
}

// COUNT
if ($temBusca) {
  $sqlCount = "SELECT COUNT(*) AS total FROM livros
               WHERE titulo LIKE ? OR autor LIKE ? OR ISBN LIKE ?";
  $stmtCount = mysqli_prepare($conn, $sqlCount);
  $like = "%{$q}%";
  mysqli_stmt_bind_param($stmtCount, "sss", $like, $like, $like);
  mysqli_stmt_execute($stmtCount);
  $resCount = mysqli_stmt_get_result($stmtCount);
  $total_registros = (int)mysqli_fetch_assoc($resCount)['total'];
} else {
  $resCount = mysqli_query($conn, "SELECT COUNT(*) AS total FROM livros");
  $total_registros = (int)mysqli_fetch_assoc($resCount)['total'];
}

$total_paginas = (int)ceil($total_registros / $por_pagina);
if ($total_paginas < 1) $total_paginas = 1;
if ($pagina > $total_paginas) $pagina = $total_paginas;

$offset = ($pagina - 1) * $por_pagina;

// SELECT
if ($temBusca) {
  $sql = "
    SELECT l.*, c.codigo AS cdd_codigo, c.descricao AS cdd_descricao
    FROM livros l
    LEFT JOIN cdd c ON c.id = l.categoria
    WHERE l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?
    ORDER BY l.disponivel DESC, l.id DESC
    LIMIT ? OFFSET ?
  ";
  $stmt = mysqli_prepare($conn, $sql);
  $like = "%{$q}%";
  mysqli_stmt_bind_param($stmt, "sssii", $like, $like, $like, $por_pagina, $offset);
  mysqli_stmt_execute($stmt);
  $r = mysqli_stmt_get_result($stmt);
} else {
  $sql = "
    SELECT l.*, c.codigo AS cdd_codigo, c.descricao AS cdd_descricao
    FROM livros l
    LEFT JOIN cdd c ON c.id = l.categoria
    ORDER BY l.disponivel DESC, l.id DESC
    LIMIT $por_pagina OFFSET $offset
  ";
  $r = mysqli_query($conn, $sql);
}

$fallback = esc(cover_fallback_svg_datauri());

$rows_html = '';
while ($l = mysqli_fetch_assoc($r)) {
  $id = (int)$l['id'];

  $tituloRaw = (string)($l['titulo'] ?? 'Livro');
  $titulo = esc($tituloRaw);

  $autor = esc($l['autor'] ?? '-');

  $cdd = '-';
  if (!empty($l['cdd_codigo']) || !empty($l['cdd_descricao'])) {
    $cdd = esc(trim(($l['cdd_codigo'] ?? '') . ' - ' . ($l['cdd_descricao'] ?? '')));
  }

  $ano = esc($l['ano_publicacao'] ?? '-');

  $isbn_digits = only_digits($l['ISBN'] ?? '');
  $isbn_show = $isbn_digits !== '' ? esc($isbn_digits) : '-';

  $qtd_total = (int)($l['qtd_total'] ?? 1);
  $qtd_disp  = (int)($l['qtd_disp'] ?? 0);

  $badge = "<span class='badge-soft-ok'>{$qtd_disp}/{$qtd_total}</span>";
  if ($qtd_disp <= 0) $badge = "<span class='badge-soft-no'>0/{$qtd_total}</span>";

  $cover = cover_url_isbn_small($isbn_digits);
  $coverEsc = esc($cover);

  $coverHtml = "
    <div style='width:38px;height:56px;border-radius:10px;border:1px solid #e7e1d6;background:#fbf8f2;overflow:hidden;display:flex;align-items:center;justify-content:center;'>
      " . ($coverEsc !== ''
        ? "<img src='{$coverEsc}' alt='Capa' style='width:100%;height:100%;object-fit:cover;'
             onerror=\"this.onerror=null; this.src='{$fallback}';\">"
        : "<img src='{$fallback}' alt='Sem capa' style='width:100%;height:100%;object-fit:cover;'>"
      ) . "
    </div>
  ";

  $desativado = ((int)($l['disponivel'] ?? 1) === 0);
  $rowStyle = $desativado ? "style='opacity:.65;'" : "";

  $acaoBtn = "";
  if (!$desativado) {
    // BAIXAR
    $acaoBtn = "
      <a class='icon-btn icon-btn--del'
         href='#'
         data-action='baixar'
         data-id='{$id}'
         data-titulo=\"" . esc($tituloRaw) . "\"
         title='Baixar do acervo'>
        <i class='bi bi-box-arrow-down'></i>
      </a>
    ";
  } else {
    // REATIVAR
    $acaoBtn = "
      <a class='icon-btn icon-btn--edit'
         href='#'
         data-action='reativar'
         data-id='{$id}'
         data-titulo=\"" . esc($tituloRaw) . "\"
         title='Reativar livro'>
        <i class='bi bi-arrow-up-circle'></i>
      </a>
    ";
  }

  $rows_html .= "
    <tr {$rowStyle}>
      <td>{$coverHtml}</td>
      <td class='fw-semibold'>{$titulo}</td>
      <td>{$autor}</td>
      <td class='text-muted small'>{$cdd}</td>
      <td>{$ano}</td>
      <td class='text-muted small'>{$isbn_show}</td>
      <td>{$badge}</td>

      <td class='text-end'>
        <a class='icon-btn icon-btn--edit' href='editar.php?id={$id}' title='Editar'>
          <i class='bi bi-pencil'></i>
        </a>
        {$acaoBtn}
      </td>
    </tr>
  ";
}

// PAGINAÇÃO
$pagination_html = '';
if ($total_registros > 0) {
  $inicio = max(1, $pagina - 2);
  $fim = min($total_paginas, $pagina + 2);

  $pagination_html .= "<nav><ul class='pagination justify-content-center mb-0'>";

  $prevDisabled = ($pagina <= 1) ? "disabled" : "";
  $prevPage = max(1, $pagina - 1);
  $pagination_html .= "<li class='page-item {$prevDisabled}'>
    <a class='page-link' href='#' data-page='{$prevPage}'>Anterior</a>
  </li>";

  if ($inicio > 1) {
    $pagination_html .= "<li class='page-item'><a class='page-link' href='#' data-page='1'>1</a></li>";
    if ($inicio > 2) $pagination_html .= "<li class='page-item disabled'><span class='page-link'>…</span></li>";
  }

  for ($i = $inicio; $i <= $fim; $i++) {
    $active = ($i === $pagina) ? "active" : "";
    $pagination_html .= "<li class='page-item {$active}'>
      <a class='page-link' href='#' data-page='{$i}'>{$i}</a>
    </li>";
  }

  if ($fim < $total_paginas) {
    if ($fim < $total_paginas - 1) $pagination_html .= "<li class='page-item disabled'><span class='page-link'>…</span></li>";
    $pagination_html .= "<li class='page-item'><a class='page-link' href='#' data-page='{$total_paginas}'>{$total_paginas}</a></li>";
  }

  $nextDisabled = ($pagina >= $total_paginas) ? "disabled" : "";
  $nextPage = min($total_paginas, $pagina + 1);
  $pagination_html .= "<li class='page-item {$nextDisabled}'>
    <a class='page-link' href='#' data-page='{$nextPage}'>Próxima</a>
  </li>";

  $pagination_html .= "</ul></nav>";

  $inicioReg = min($total_registros, $offset + 1);
  $fimReg = min($total_registros, $offset + $por_pagina);

  $pagination_html .= "
    <div class='text-center text-muted mt-2' style='font-size: 13px;'>
      Mostrando {$inicioReg}–{$fimReg} de {$total_registros} livros
    </div>
  ";
}

echo json_encode([
  "rows_html" => $rows_html,
  "pagination_html" => $pagination_html,
], JSON_UNESCAPED_UNICODE);
