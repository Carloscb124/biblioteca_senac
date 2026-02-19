<?php
include("../conexao.php");

$busca  = $_GET['q'] ?? '';
$pagina = (int)($_GET['p'] ?? 1);
$limite = 10;
$offset = ($pagina - 1) * $limite;

$termo = "%$busca%";
$queryBase = "FROM usuarios WHERE (nome LIKE ? OR cpf LIKE ? OR email LIKE ?)";

$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as total " . $queryBase);
mysqli_stmt_bind_param($stmtCount, "sss", $termo, $termo, $termo);
mysqli_stmt_execute($stmtCount);
$resCount = mysqli_stmt_get_result($stmtCount);
$totalRegistros = (int)mysqli_fetch_assoc($resCount)['total'];
$totalPaginas = (int)ceil($totalRegistros / $limite);

$stmt = mysqli_prepare($conn, "
  SELECT id, nome, cpf, email, telefone, ativo
  " . $queryBase . "
  ORDER BY id DESC
  LIMIT ? OFFSET ?
");
mysqli_stmt_bind_param($stmt, "sssii", $termo, $termo, $termo, $limite, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$html = "";
if (mysqli_num_rows($result) > 0) {
  while ($u = mysqli_fetch_assoc($result)) {
    $id = (int)$u['id'];
    $ativo = ((int)$u['ativo'] === 1);

    $nome = trim((string)$u['nome']);
    $cpf = trim((string)$u['cpf']);
    $email = trim((string)$u['email']);
    $telefone = trim((string)$u['telefone']);

    // Para HTML (células)
    $nomeTxt = htmlspecialchars($nome);
    $cpfTxt = $cpf !== "" ? htmlspecialchars($cpf) : "<span class='text-muted'>-</span>";
    $emailTxt = $email !== "" ? htmlspecialchars($email) : "<span class='text-muted'>-</span>";
    $telTxt = $telefone !== "" ? htmlspecialchars($telefone) : "<span class='text-muted'>-</span>";

    // Para dataset (atributos data-*)
    $nomeAttr = htmlspecialchars($nome, ENT_QUOTES);
    $cpfAttr = htmlspecialchars($cpf, ENT_QUOTES);
    $emailAttr = htmlspecialchars($email, ENT_QUOTES);
    $telAttr = htmlspecialchars($telefone, ENT_QUOTES);

    $statusBadge = $ativo
      ? "<span class='badge-soft-ok'>Ativo</span>"
      : "<span class='badge-soft-no'>Desativado</span>";

    $botaoAcao = $ativo
      ? "<button type='button' class='icon-btn icon-btn--del' data-bs-toggle='modal' data-bs-target='#modalDesativar' data-id='$id' data-nome='$nomeTxt'><i class='bi bi-person-x'></i></button>"
      : "<button type='button' class='icon-btn icon-btn--ok' data-bs-toggle='modal' data-bs-target='#modalReativar' data-id='$id' data-nome='$nomeTxt'><i class='bi bi-person-check'></i></button>";

    // ✅ linha clicável para abrir o modal de detalhes
    $html .= "
      <tr class='row-click'
          role='button'
          data-id='{$id}'
          data-nome='{$nomeAttr}'
          data-cpf='{$cpfAttr}'
          data-email='{$emailAttr}'
          data-telefone='{$telAttr}'
          data-ativo='" . ($ativo ? "1" : "0") . "'>
        <td class='text-muted fw-semibold'>#$id</td>
        <td class='fw-semibold'>$nomeTxt</td>
        <td class='text-muted'>$cpfTxt</td>
        <td>$emailTxt</td>
        <td>$telTxt</td>
        <td>$statusBadge</td>
        <td class='text-end col-acoes'>
          <a class='icon-btn icon-btn--edit' href='editar.php?id=$id' title='Editar'><i class='bi bi-pencil'></i></a>
          $botaoAcao
        </td>
      </tr>
    ";
  }
} else {
  $html = "<tr><td colspan='7' class='text-center text-muted py-4'>Nenhum leitor encontrado.</td></tr>";
}

header('Content-Type: application/json');
echo json_encode([
  'html' => $html,
  'totalPaginas' => $totalPaginas,
  'paginaAtual' => $pagina,
  'totalRegistros' => $totalRegistros
]);
