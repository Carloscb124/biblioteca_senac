<?php
$titulo_pagina = "Importar Usuários (CSV)";
include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");

function so_digitos(string $v): string {
  return preg_replace('/\D+/', '', $v);
}

function cpf_valido_basico(string $cpf): bool {
  // Aqui é validação básica: 11 dígitos e não tudo igual.
  // Se quiser validação completa com dígitos verificadores eu faço também.
  if (strlen($cpf) !== 11) return false;
  if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
  return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {

  $fh = fopen($_FILES['csv']['tmp_name'], 'r');
  if (!$fh) {
    flash_set('danger', 'Não foi possível abrir o CSV.');
    header("Location: importar_usuarios.php");
    exit;
  }

  $primeira = fgets($fh);
  $delim = (substr_count($primeira, ';') >= substr_count($primeira, ',')) ? ';' : ',';
  rewind($fh);

  // Pula cabeçalho
  fgetcsv($fh, 0, $delim);

  $ins = 0; $upd = 0; $ign = 0;

  // Prepareds
  $stmtFindByCPF = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
  $stmtFindByEmail = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE email = ? LIMIT 1");

  $stmtInsert = mysqli_prepare($conn, "
    INSERT INTO usuarios (nome, email, telefone, cpf, perfil, ativo)
    VALUES (?, ?, ?, ?, ?, ?)
  ");

  // Update sem mexer no CPF
  $stmtUpdateSemCPF = mysqli_prepare($conn, "
    UPDATE usuarios
    SET nome = ?, email = ?, telefone = ?, perfil = ?, ativo = ?
    WHERE id = ?
  ");

  // Update com CPF (quando vier CPF válido)
  $stmtUpdateComCPF = mysqli_prepare($conn, "
    UPDATE usuarios
    SET nome = ?, email = ?, telefone = ?, cpf = ?, perfil = ?, ativo = ?
    WHERE id = ?
  ");

  mysqli_begin_transaction($conn);

  try {
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {

      $nome_raw     = trim((string)($row[0] ?? ''));
      $email_raw    = trim((string)($row[1] ?? ''));
      $telefone_raw = trim((string)($row[2] ?? ''));
      $cpf_raw      = trim((string)($row[3] ?? ''));
      $perfil_raw   = trim((string)($row[4] ?? 'Leitor'));
      $ativo_raw    = trim((string)($row[5] ?? ''));

      // Linha totalmente vazia? ignora
      if ($nome_raw === '' && $email_raw === '' && $telefone_raw === '' && $cpf_raw === '') {
        $ign++;
        continue;
      }

      $nome = $nome_raw !== '' ? $nome_raw : 'Sem Nome';
      $email = $email_raw !== '' ? mb_strtolower($email_raw, 'UTF-8') : null;

      // Se quiser manter telefone com formatação, remove a próxima linha
      $telefone = $telefone_raw !== '' ? so_digitos($telefone_raw) : '';

      // CPF: obrigatório no INSERT e precisa ser válido
      $cpf = so_digitos($cpf_raw);
      $cpf_ok = ($cpf !== '' && cpf_valido_basico($cpf));

      $perfil = $perfil_raw !== '' ? $perfil_raw : 'Leitor';
      $ativo = ($ativo_raw === 'Sim' || $ativo_raw === 'sim' || $ativo_raw === '1') ? 1 : 0;

      // Buscar existente
      $id_encontrado = null;

      if ($cpf_ok) {
        mysqli_stmt_bind_param($stmtFindByCPF, "s", $cpf);
        mysqli_stmt_execute($stmtFindByCPF);
        $res = mysqli_stmt_get_result($stmtFindByCPF);
        $r = $res ? mysqli_fetch_assoc($res) : null;
        if ($r) $id_encontrado = (int)$r['id'];
      }

      if (!$id_encontrado && $email !== null) {
        mysqli_stmt_bind_param($stmtFindByEmail, "s", $email);
        mysqli_stmt_execute($stmtFindByEmail);
        $res = mysqli_stmt_get_result($stmtFindByEmail);
        $r = $res ? mysqli_fetch_assoc($res) : null;
        if ($r) $id_encontrado = (int)$r['id'];
      }

      if ($id_encontrado) {
        // UPDATE
        $id = $id_encontrado;

        if ($cpf_ok) {
          mysqli_stmt_bind_param($stmtUpdateComCPF, "ssssssi",
            $nome, $email, $telefone, $cpf, $perfil, $ativo, $id
          );
          mysqli_stmt_execute($stmtUpdateComCPF);
        } else {
          // Sem CPF válido: não altera CPF existente
          mysqli_stmt_bind_param($stmtUpdateSemCPF, "ssssii",
            $nome, $email, $telefone, $perfil, $ativo, $id
          );
          mysqli_stmt_execute($stmtUpdateSemCPF);
        }

        $upd++;

      } else {
        // INSERT: aqui CPF é obrigatório
        if (!$cpf_ok) {
          $ign++;
          continue;
        }

        mysqli_stmt_bind_param($stmtInsert, "sssssi",
          $nome, $email, $telefone, $cpf, $perfil, $ativo
        );

        if (mysqli_stmt_execute($stmtInsert)) $ins++;
        else $ign++;
      }
    }

    fclose($fh);
    mysqli_commit($conn);

    flash_set('success', "Importação concluída: $ins novos, $upd atualizados, $ign ignorados.");
    header("Location: importar_usuarios.php");
    exit;

  } catch (Exception $e) {
    fclose($fh);
    mysqli_rollback($conn);
    flash_set('danger', 'Erro na importação: ' . $e->getMessage());
    header("Location: importar_usuarios.php");
    exit;
  }
}
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">Importar Usuários</h2>
      <a class="btn btn-pill btn-sm" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>

    <div class="alert alert-info mt-3 small">
      <i class="bi bi-info-circle me-2"></i>
      Campos aceitos: <strong>Nome; Email; Telefone; CPF; Perfil; Ativo</strong>.<br>
      Email e telefone podem ficar vazios. <strong>CPF é obrigatório para cadastrar novo usuário</strong>.
    </div>

    <form method="post" enctype="multipart/form-data" class="mt-4">
      <div class="mb-3">
        <label class="form-label fw-bold small">Arquivo CSV</label>
        <input type="file" name="csv" class="form-control form-control-sm" accept=".csv" required>
      </div>

      <button type="submit" class="btn btn-pill btn-sm px-4">
        <i class="bi bi-upload me-1"></i> Processar Importação
      </button>
    </form>
  </div>
</div>

<?php include("../../includes/footer.php"); ?>
