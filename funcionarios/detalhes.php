<?php
// funcionarios/detalhes.php
require_once("../auth/auth_guard.php");
require_admin();

include("../conexao.php");

header("Content-Type: application/json; charset=UTF-8");

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
  echo json_encode(["ok" => false, "msg" => "ID inválido."], JSON_UNESCAPED_UNICODE);
  exit;
}

$stmt = mysqli_prepare($conn, "
  SELECT id, nome, email, cpf, telefone, cargo, ativo
  FROM funcionarios
  WHERE id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$f = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$f) {
  echo json_encode(["ok" => false, "msg" => "Funcionário não encontrado."], JSON_UNESCAPED_UNICODE);
  exit;
}

$ativo = ((int)$f["ativo"] === 1);
$cargo = strtoupper(trim($f["cargo"] ?? "BIBLIOTECARIO"));
if ($cargo !== "ADMIN" && $cargo !== "BIBLIOTECARIO") $cargo = "BIBLIOTECARIO";

$cargoLabel = ($cargo === "ADMIN") ? "ADMIN" : "BIBLIOTECÁRIO";
$statusText = $ativo ? "Ativo" : "Desativado";
$statusClass = $ativo ? "badge rounded-pill text-bg-success" : "badge rounded-pill text-bg-danger";

$cpf = trim((string)($f["cpf"] ?? ""));
$tel = trim((string)($f["telefone"] ?? ""));

$cpfShow = $cpf !== "" ? htmlspecialchars($cpf) : "Não informado";
$telShow = $tel !== "" ? htmlspecialchars($tel) : "Não informado";

ob_start();
?>
<div class="d-flex gap-3 align-items-start">
  <div class="user-avatar flex-shrink-0">
    <i class="bi bi-person-circle"></i>
  </div>

  <div class="flex-grow-1">
    <h4 class="mb-1 fw-bold"><?= htmlspecialchars($f["nome"]) ?></h4>

    <div class="d-flex flex-wrap gap-2 mb-2">
      <span class="<?= $statusClass ?> px-3 py-2"><?= $statusText ?></span>

      <?php if ($cargo === "ADMIN"): ?>
        <span class="badge rounded-pill text-bg-dark px-3 py-2"><?= $cargoLabel ?></span>
      <?php else: ?>
        <span class="badge rounded-pill text-bg-light border px-3 py-2"><?= $cargoLabel ?></span>
      <?php endif; ?>
    </div>

    <div class="row g-2">
      <div class="col-12 col-md-6">
        <div class="text-muted small">Email</div>
        <div class="fw-semibold"><?= htmlspecialchars($f["email"]) ?></div>
      </div>

      <div class="col-12 col-md-3">
        <div class="text-muted small">CPF</div>
        <div class="fw-semibold"><?= $cpfShow ?></div>
      </div>

      <div class="col-12 col-md-3">
        <div class="text-muted small">Telefone</div>
        <div class="fw-semibold"><?= $telShow ?></div>
      </div>
    </div>
  </div>
</div>
<?php
$html = ob_get_clean();

$footer = "
  <a class='btn btn-pill btn-outline-primary' href='editar.php?id=".(int)$f["id"]."'>
    <i class='bi bi-pencil me-1'></i> Editar
  </a>
";

echo json_encode(["ok" => true, "html" => $html, "footer" => $footer], JSON_UNESCAPED_UNICODE);
exit;
