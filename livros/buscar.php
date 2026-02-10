<?php
include("../auth/auth_guard.php");
include("../conexao.php");

header("Content-Type: application/json; charset=UTF-8");

$por_pagina = 10;
$p = (int)($_GET['p'] ?? 1);
if ($p < 1) $p = 1;

$q = trim($_GET['q'] ?? '');
$offset = ($p - 1) * $por_pagina;

// ===== Monta WHERE da busca =====
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

// ===== Total =====
$sqlCount = "SELECT COUNT(*) AS total FROM livros l $whereSql";
$stmtC = mysqli_prepare($conn, $sqlCount);
if ($types !== "") mysqli_stmt_bind_param($stmtC, $types, ...$params);
mysqli_stmt_execute($stmtC);
$resC = mysqli_stmt_get_result($stmtC);
$total = (int)(mysqli_fetch_assoc($resC)['total'] ?? 0);

$total_paginas = max(1, (int)ceil($total / $por_pagina));
if ($p > $total_paginas) $p = $total_paginas;
$offset = ($p - 1) * $por_pagina;

// ===== Lista =====
// ORDER BY: disponivel DESC (ativos primeiro), depois id DESC
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

$types2 = $types . "ii";
$params2 = $params;
$params2[] = $por_pagina;
$params2[] = $offset;

mysqli_stmt_bind_param($stmt, $types2, ...$params2);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

// ===== Helpers =====
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function onlyDigits($s){ return preg_replace('/\D+/', '', (string)$s); }

// monta paginação simples (data-page)
function montaPaginacao($p, $total_paginas){
  if ($total_paginas <= 1) return "";
  $out = '<nav><ul class="pagination pagination-green mb-0">';

  // anterior
  $out .= '<li class="page-item '.($p<=1?'disabled':'').'">';
  $out .= '<a class="page-link" href="#" data-page="'.max(1,$p-1).'">Anterior</a></li>';

  // janela
  $janela = 2;
  $ini = max(1, $p - $janela);
  $fim = min($total_paginas, $p + $janela);

  for($i=$ini;$i<=$fim;$i++){
    $out .= '<li class="page-item '.($i===$p?'active':'').'">';
    $out .= '<a class="page-link" href="#" data-page="'.$i.'">'.$i.'</a></li>';
  }

  // próxima
  $out .= '<li class="page-item '.($p>=$total_paginas?'disabled':'').'">';
  $out .= '<a class="page-link" href="#" data-page="'.min($total_paginas,$p+1).'">Próxima</a></li>';

  $out .= '</ul></nav>';
  return $out;
}

// ===== Render linhas =====
$rows_html = "";
while ($l = mysqli_fetch_assoc($r)) {
  $id = (int)$l['id'];
  $titulo = (string)$l['titulo'];
  $autor  = (string)($l['autor'] ?? '');
  $ano    = $l['ano_publicacao'] ?? '';
  $isbnRaw = (string)($l['ISBN'] ?? '');
  $isbn = onlyDigits($isbnRaw);

  $qtdTotal = (int)($l['qtd_total'] ?? 0);
  $qtdDisp  = (int)($l['qtd_disp'] ?? 0);
  $dispTxt  = "{$qtdDisp}/{$qtdTotal}";
  $badge = ($qtdDisp > 0) ? "badge-soft-ok" : "badge-soft-no";

  $disponivel = ((int)$l['disponivel'] === 1);

  // Categoria (pode ser id do CDD; aqui só mostra como texto básico se você já monta "codigo - descricao" no SELECT do seu projeto)
  $cat = $l['categoria'];
  $catTxt = ($cat && (int)$cat > 0) ? h($cat) : "-";

  // ===== Capa =====
  // Prioridade: capa_url do banco (manual/upload) > OpenLibrary > Google
  $capaUrl = trim((string)($l['capa_url'] ?? ''));
  if ($capaUrl === "" && $isbn !== "") {
    $capaUrl = "https://covers.openlibrary.org/b/isbn/{$isbn}-S.jpg?default=false";
  }
  $googleFallback = ($isbn !== "")
    ? "https://books.google.com/books/content?vid=ISBN{$isbn}&printsec=frontcover&img=1&zoom=1&source=gbs_api"
    : "";

  if ($isbn === "" && $capaUrl !== "" && str_contains($capaUrl, "openlibrary.org")) {
    // se o ISBN tá vazio, evita URL quebrada de openlibrary
    $capaUrl = "";
  }

  // HTML da capa (onerror cai pro Google e depois placeholder)
  if ($capaUrl !== "") {
    $img = '<img class="cover-thumb" loading="lazy" alt="Capa" src="'.h($capaUrl).'"';
    if ($googleFallback !== "") {
      $img .= ' onerror="this.onerror=null;this.src=\''.h($googleFallback).'\';this.classList.add(\'cover-thumb\');"';
      $img .= ' onload="this.classList.add(\'cover-thumb\');"';
    } else {
      $img .= ' onerror="this.onerror=null;this.outerHTML=\'<div class=&quot;cover-placeholder&quot;>Sem capa</div>\';"';
    }
    $img .= '>';
  } else {
    $img = '<div class="cover-placeholder">Sem capa</div>';
  }

  // Ações: ativo mostra "baixar", baixado mostra "reativar"
  $btnEditar = '
    <a class="icon-btn icon-btn--edit" href="editar.php?id='.$id.'" title="Editar">
      <i class="bi bi-pencil"></i>
    </a>
  ';

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

  $rows_html .= '
    <tr>
      <td>'.$img.'</td>
      <td class="cell-wrap fw-semibold">'.h($titulo).'</td>
      <td class="cell-wrap">'.h($autor).'</td>
      <td class="cell-wrap">'.$catTxt.'</td>
      <td>'.h($ano).'</td>
      <td>'.h($isbnRaw).'</td>
      <td><span class="'.$badge.'">'.h($dispTxt).'</span></td>
      <td class="text-end">'.$btnEditar.$btn2.'</td>
    </tr>
  ';
}

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
]);
