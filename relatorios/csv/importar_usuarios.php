<?php
$titulo_pagina = "Importar Usuários (CSV)";
include("../../auth/auth_guard.php");
include("../../conexao.php");
include("../../includes/header.php");


if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    $fh = fopen($_FILES['csv']['tmp_name'], 'r');
    $primeira = fgets($fh);
    $delim = (substr_count($primeira, ';') >= substr_count($primeira, ',')) ? ';' : ',';
    rewind($fh);
    fgetcsv($fh, 0, $delim); // Pula cabeçalho

    $ins = 0; $upd = 0;

    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        $nome     = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
        $email    = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
        $telefone = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
        $cpf      = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
        $perfil   = mysqli_real_escape_string($conn, trim($row[4] ?? 'Leitor'));
        $ativo    = (isset($row[5]) && (trim($row[5]) === 'Sim' || $row[5] == '1')) ? 1 : 0;

        // Ignora linhas totalmente vazias
        if (empty($nome) && empty($cpf) && empty($email)) continue;

        // LÓGICA DE VERIFICAÇÃO:
        $id_encontrado = null;

        if (!empty($cpf)) {
            // Primeiro tenta achar pelo CPF
            $check = mysqli_query($conn, "SELECT id FROM usuarios WHERE cpf = '$cpf' LIMIT 1");
            if ($res = mysqli_fetch_assoc($check)) $id_encontrado = $res['id'];
        } 
        
        if (!$id_encontrado && !empty($email)) {
            // Se não achou pelo CPF, tenta pelo e-mail
            $check = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$email' LIMIT 1");
            if ($res = mysqli_fetch_assoc($check)) $id_encontrado = $res['id'];
        }

        // TRATAMENTO DE VALORES VAZIOS PARA O BANCO (NULL em vez de string vazia '')
        // Isso evita o erro de "Duplicate entry ''" no e-mail
        $email_sql = empty($email) ? "NULL" : "'$email'";
        $cpf_sql   = empty($cpf) ? "NULL" : "'$cpf'";

        if ($id_encontrado) {
            // ATUALIZA
            $sqlUpd = "UPDATE usuarios SET 
                        nome='$nome', 
                        email=$email_sql, 
                        telefone='$telefone', 
                        cpf=$cpf_sql, 
                        perfil='$perfil', 
                        ativo=$ativo 
                       WHERE id=$id_encontrado";
            mysqli_query($conn, $sqlUpd);
            $upd++;
        } else {
            // INSERE
            $sqlIns = "INSERT INTO usuarios (nome, email, telefone, cpf, perfil, ativo) 
                       VALUES ('$nome', $email_sql, '$telefone', $cpf_sql, '$perfil', $ativo)";
            if (mysqli_query($conn, $sqlIns)) {
                $ins++;
            }
        }
    }
    fclose($fh);
    flash_set('success', "Importação concluída: $ins novos, $upd atualizados.");
    header("Location: importar_usuarios.php");
    exit;
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
            O e-mail e telefone podem ficar vazios no Excel.
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