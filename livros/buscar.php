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

function esc($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ===== COUNT =====
if ($temBusca) {
  $sqlCount = "SELECT COUNT(*) AS total
              FROM livros
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

// recalcula offset se ajustou página
$offset = ($pagina - 1) * $por_pagina;

// ===== SELECT PAGINADO =====
if ($temBusca) {
  $sql = "SELECT * FROM livros
          WHERE titulo LIKE ? OR autor LIKE ? OR ISBN LIKE ?
          ORDER BY id DESC
          LIMIT ? OFFSET ?";
  $stmt = mysqli_prepare($conn, $sql);
  $like = "%{$q}%";
  mysqli_stmt_bind_param($stmt, "sssii", $like, $like, $like, $por_pagina, $offset);
  mysqli_stmt_execute($stmt);
  $r = mysqli_stmt_get_result($stmt);
} else {
  $sql = "SELECT * FROM livros
          ORDER BY id DESC
          LIMIT $por_pagina OFFSET $offset";
  $r = mysqli_query($conn, $sql);
}

// ===== ROWS HTML =====
$rows_html = '';
while ($l = mysqli_fetch_assoc($r)) {
  $id = (int)$l['id'];
  $titulo = esc($l['titulo']);
  $autor = esc($l['autor'] ?? '-');
  $ano = esc($l['ano_publicacao'] ?? '-');
  $isbn = esc($l['ISBN'] ?? '-');
  $disp = (int)($l['disponivel'] ?? 0);

  $badge = $disp === 1
    ? '<span class="badge-soft-ok">Disponível</span>'
    : '<span class="badge-soft-no">Indisponível</span>';

  $rows_html .= "
    <tr>
      <td class='text-muted fw-semibold'>#{$id}</td>
      <td class='fw-semibold'>{$titulo}</td>
      <td>{$autor}</td>
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

// ===== PAGINAÇÃO HTML =====
$pagination_html = '';
if ($total_registros > 0) {
  $inicio = max(1, $pagina - 2);
  $fim = min($total_paginas, $pagina + 2);

  $pagination_html .= "<nav><ul class='pagination justify-content-center mb-0'>";

  // anterior
  $prevDisabled = ($pagina <= 1) ? "disabled" : "";
  $prevPage = max(1, $pagina - 1);
  $pagination_html .= "<li class='page-item {$prevDisabled}'>
    <a class='page-link' href='#' data-page='{$prevPage}'>Anterior</a>
  </li>";

  // primeira + reticências
  if ($inicio > 1) {
    $pagination_html .= "<li class='page-item'>
      <a class='page-link' href='#' data-page='1'>1</a>
    </li>";
    if ($inicio > 2) {
      $pagination_html .= "<li class='page-item disabled'><span class='page-link'>…</span></li>";
    }
  }

  // miolo
  for ($i = $inicio; $i <= $fim; $i++) {
    $active = ($i === $pagina) ? "active" : "";
    $pagination_html .= "<li class='page-item {$active}'>
      <a class='page-link' href='#' data-page='{$i}'>{$i}</a>
    </li>";
  }

  // última + reticências
  if ($fim < $total_paginas) {
    if ($fim < $total_paginas - 1) {
      $pagination_html .= "<li class='page-item disabled'><span class='page-link'>…</span></li>";
    }
    $pagination_html .= "<li class='page-item'>
      <a class='page-link' href='#' data-page='{$total_paginas}'>{$total_paginas}</a>
    </li>";
  }

  // próxima
  $nextDisabled = ($pagina >= $total_paginas) ? "disabled" : "";
  $nextPage = min($total_paginas, $pagina + 1);
  $pagination_html .= "<li class='page-item {$nextDisabled}'>
    <a class='page-link' href='#' data-page='{$nextPage}'>Próxima</a>
  </li>";

  $pagination_html .= "</ul></nav>";

  // contador
  $inicioReg = ($total_registros === 0) ? 0 : min($total_registros, $offset + 1);
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
