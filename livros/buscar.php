<?php
// livros/buscar.php
// Retorna JSON com:
// - rows_html: linhas da tabela (HTML pronto)
// - pagination_html: paginação (HTML pronto)
// Obs: cada <tr> vem com data-id="X" pra abrir o modal de detalhes ao clicar.

include("../auth/auth_guard.php");
include("../conexao.php");

header("Content-Type: application/json; charset=UTF-8");

// ===== Config de paginação =====
$por_pagina = 10;
$p = (int)($_GET['p'] ?? 1);
if ($p < 1) $p = 1;

$q = trim($_GET['q'] ?? '');
$offset = ($p - 1) * $por_pagina;

// ===== Helpers =====
function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function onlyDigits($s) {
  return preg_replace('/\D+/', '', (string)$s);
}

// ===== Monta WHERE da busca (prepared) =====
$where = [];
$params = [];
$types = "";

if ($q !== '') {
  $where[] = "(l.titulo LIKE ? OR l.autor LIKE ? OR l.ISBN LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= "sss";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ===== Total de resultados (pra paginação) =====
$sqlCount = "SELECT COUNT(*) AS total FROM livros l $whereSql";
$stmtC = mysqli_prepare($conn, $sqlCount);
if ($types !== "") {
  mysqli_stmt_bind_param($stmtC, $types, ...$params);
}
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
$total = (int)(mysqli_fetch_assoc($resC)['total'] ?? 0);

$total_paginas = max(1, (int)ceil($total / $por_pagina));
if ($p > $total_paginas) $p = $total_paginas;
$offset = ($p - 1) * $por_pagina;

// ===== Lista =====
// Order: ativos primeiro (disponivel=1), depois mais recentes
$sql = "
  SELECT
    l.id, l.titulo, l.autor, l.ano_publicacao, l.ISBN,
    l.categoria, l.qtd_total, l.qtd_disp, l.disponivel,
    l.capa_url
  FROM livros l
  $whereSql
  ORDER BY l.disponivel DESC, l.id DESC
  LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);

// Bind filtros + limit/offset
$types2 = $types . "ii";
$params2 = $params;
$params2[] = $por_pagina;
$params2[] = $offset;

mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

// ===== Paginação simples com data-page (pra JS interceptar) =====
function montaPaginacao($p, $total_paginas) {
  if ($total_paginas <= 1) return "";

  $out = '<nav><ul class="pagination pagination-green mb-0">';

  // anterior
  $out .= '<li class="page-item '.($p <= 1 ? 'disabled' : '').'">';
  $out .= '<a class="page-link" href="#" data-page="'.max(1, $p - 1).'">Anterior</a></li>';

  // janela de páginas
  $janela = 2;
  $ini = max(1, $p - $janela);
  $fim = min($total_paginas, $p + $janela);

  for ($i = $ini; $i <= $fim; $i++) {
    $out .= '<li class="page-item '.($i === $p ? 'active' : '').'">';
    $out .= '<a class="page-link" href="#" data-page="'.$i.'">'.$i.'</a></li>';
  }

  // próxima
  $out .= '<li class="page-item '.($p >= $total_paginas ? 'disabled' : '').'">';
  $out .= '<a class="page-link" href="#" data-page="'.min($total_paginas, $p + 1).'">Próxima</a></li>';

  $out .= '</ul></nav>';
  return $out;
}

// ===== Render das linhas =====
$rows_html = "";

while ($l = mysqli_fetch_assoc($r)) {
  $id = (int)$l['id'];

  $titulo = (string)$l['titulo'];
  $autor  = (string)($l['autor'] ?? '');
  $ano    = $l['ano_publicacao'] ?? '';

  $isbnRaw = (string)($l['ISBN'] ?? '');
  $isbn = onlyDigits($isbnRaw);

  // quantidades
  $qtdTotal = (int)($l['qtd_total'] ?? 0);
  $qtdDisp  = (int)($l['qtd_disp'] ?? 0);

  $dispTxt  = "{$qtdDisp}/{$qtdTotal}";
  $badge = ($qtdDisp > 0) ? "badge-soft-ok" : "badge-soft-no";

  $disponivel = ((int)($l['disponivel'] ?? 1) === 1);

  // categoria (por enquanto só mostra o valor cru; se quiser bonito, faz JOIN no CDD)
  $cat = $l['categoria'];
  $catTxt = ($cat && (int)$cat > 0) ? h($cat) : "-";

  // ===== CAPA =====
  // Prioridade:
  // 1) capa_url do banco (manual/upload)
  // 2) OpenLibrary
  // 3) Google (fallback)
  $capaUrl = trim((string)($l['capa_url'] ?? ''));

  if ($capaUrl === "" && $isbn !== "") {
    $capaUrl = "https://covers.openlibrary.org/b/isbn/{$isbn}-S.jpg?default=false";
  }

  $googleFallback = ($isbn !== "")
    ? "https://books.google.com/books/content?vid=ISBN{$isbn}&printsec=frontcover&img=1&zoom=1&source=gbs_api"
    : "";

  // Evita caso bizarro: isbn vazio + url de openlibrary
  if ($isbn === "" && $capaUrl !== "" && function_exists("str_contains") && str_contains($capaUrl, "openlibrary.org")) {
    $capaUrl = "";
  }

  // HTML da imagem (onerror cai pro Google e depois placeholder)
  if ($capaUrl !== "") {
    $img = '<img class="cover-thumb" loading="lazy" alt="Capa" src="'.h($capaUrl).'"';

    if ($googleFallback !== "") {
      // 1º erro: tenta Google
      // 2º erro: vira placeholder
      $img .= ' onerror="this.onerror=null;this.src=\''.h($googleFallback).'\';this.setAttribute(\'data-fallback\',\'1\');"';
      $img .= ' onload="this.classList.add(\'cover-thumb\');"';

      // Se o Google também falhar, o CSS/JS pode substituir, mas aqui já dá um jeitinho:
      $img .= ' data-gf="'.h($googleFallback).'"';
    } else {
      $img .= ' onerror="this.onerror=null;this.outerHTML=\'<div class=&quot;cover-placeholder&quot;>Sem capa</div>\';"';
    }

    $img .= '>';
  } else {
    $img = '<div class="cover-placeholder">Sem capa</div>';
  }

  // ===== AÇÕES =====
  $btnEditar = '
    <a class="icon-btn icon-btn--edit" href="editar.php?id='.$id.'" title="Editar">
      <i class="bi bi-pencil"></i>
    </a>
  ';

  // Se tá ativo: "baixar". Se tá baixado: "reativar".
  if ($disponivel) {
    $btn2 = '
      <a class="icon-btn icon-btn--del"
        href="#"
        data-action="baixar"
        data-id="'.$id.'"
        data-titulo="'.h($titulo).'"
        title="Baixar do acervo">
        <i class="bi bi-box-arrow-down"></i>
      </a>
    ';
  } else {
    $btn2 = '
      <a class="icon-btn icon-btn--edit"
        href="#"
        data-action="reativar"
        data-id="'.$id.'"
        data-titulo="'.h($titulo).'"
        title="Reativar">
        <i class="bi bi-arrow-up-circle"></i>
      </a>
    ';
  }

  // ===== Linha da tabela =====
  // IMPORTANTE: data-id no TR -> é isso que o modal de detalhes usa!
  // Também deixei o título como <a> (só visual), mas quem manda é o click no <tr>.
  $rows_html .= '
    <tr data-id="'.$id.'" class="'.($disponivel ? '' : 'row-disabled').'">
      <td>'.$img.'</td>

      <td class="cell-wrap fw-semibold">
        <a href="#" class="book-title-link" style="text-decoration:none;color:inherit;">
          '.h($titulo).'
        </a>
      </td>

      <td class="cell-wrap">'.h($autor).'</td>
      <td class="cell-wrap">'.$catTxt.'</td>
      <td>'.h($ano).'</td>
      <td>'.h($isbnRaw).'</td>
      <td><span class="'.$badge.'">'.h($dispTxt).'</span></td>
      <td class="text-end">'.$btnEditar.$btn2.'</td>
    </tr>
  ';
}

// Se não veio nada, retorna vazio bonitinho
if (trim($rows_html) === "") {
  $rows_html = '
    <tr>
      <td colspan="8" class="text-center text-muted py-4">Nenhum livro encontrado.</td>
    </tr>
  ';
}

echo json_encode([
  "rows_html" => $rows_html,
  "pagination_html" => montaPaginacao($p, $total_paginas)
], JSON_UNESCAPED_UNICODE);
