<?php
$titulo_pagina = "Importar Empréstimos (CSV)";
include_once __DIR__ . "/../../auth/auth_guard.php";
include_once __DIR__ . "/../../conexao.php";
include_once __DIR__ . "/../../includes/header.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $arquivo = $_FILES['csv']['tmp_name'];
    $handle = fopen($arquivo, "r");
    
    $primeiraLinha = fgets($handle);
    $delim = (substr_count($primeiraLinha, ';') >= substr_count($primeiraLinha, ',')) ? ';' : ',';
    rewind($handle);
    fgetcsv($handle, 0, $delim); // Pula cabeçalho

    $inseridos = 0; 
    $ignorados = 0;
    $nao_encontrados = 0;

    while (($linha = fgetcsv($handle, 0, $delim)) !== false) {
        // Captura e limpa os dados básicos
        $email = trim($linha[2] ?? ''); 
        $isbn  = trim($linha[4] ?? ''); 
        
        if (empty($email) || empty($isbn)) continue;

        // Busca o Usuário
        $resU = mysqli_query($conn, "SELECT id FROM usuarios WHERE email = '$email' LIMIT 1");
        $dadosU = mysqli_fetch_assoc($resU);

        // Busca o Livro
        $resL = mysqli_query($conn, "SELECT id FROM livros WHERE ISBN = '$isbn' LIMIT 1");
        $dadosL = mysqli_fetch_assoc($resL);

        // TRAVA DE SEGURANÇA: Só prossegue se ambos existirem no banco
        if ($dadosU && $dadosL) {
            $idU = $dadosU['id'];
            $idL = $dadosL['id'];

            $data_emp   = $linha[5] ?? date('Y-m-d');
            $data_prev  = $linha[6] ?? date('Y-m-d', strtotime('+7 days'));
            $status_txt = trim($linha[8] ?? ''); 
            $devolvido  = ($status_txt === 'Devolvido' || $status_txt === 'Sim' || $status_txt == '1') ? 1 : 0;

            // ANTI-DUPLICAÇÃO: Verifica se já existe esse empréstimo ATIVO (não devolvido)
            $check = mysqli_query($conn, "SELECT id FROM emprestimos WHERE id_usuario = $idU AND id_livro = $idL AND devolvido = 0");
            
            if (mysqli_num_rows($check) == 0) {
                $sqlInsert = "INSERT INTO emprestimos (id_usuario, id_livro, data_emprestimo, data_prevista, devolvido) 
                              VALUES ($idU, $idL, '$data_emp', '$data_prev', $devolvido)";
                mysqli_query($conn, $sqlInsert);
                
                // Se o empréstimo for novo e não devolvido, baixa o estoque
                if ($devolvido == 0) {
                    mysqli_query($conn, "UPDATE livros SET qtd_disp = qtd_disp - 1 WHERE id = $idL");
                }
                $inseridos++;
            } else {
                $ignorados++;
            }
        } else {
            // Conta como ignorado se o e-mail ou ISBN não estiverem no sistema
            $nao_encontrados++;
        }
    }
    fclose($handle);
    flash_set('success', "Importação finalizada. Inseridos: $inseridos | Já existiam: $ignorados | Não encontrados: $nao_encontrados");
    header("Location: importar_emprestimos.php");
    exit;
}
?>

<div class="container my-4">
    <div class="page-card">
        <div class="page-card__head">
            <h2 class="page-card__title">Importar Histórico</h2>
            <a class="btn btn-pill" href="index.php"><i class="bi bi-arrow-left"></i> Voltar</a>
        </div>
        
        <div class="alert alert-info mt-3">
            <i class="bi bi-info-circle me-2"></i>
            O sistema valida se o <strong>E-mail</strong> e o <strong>ISBN</strong> existem antes de importar.
        </div>

        <form method="post" enctype="multipart/form-data" class="mt-3">
            <div class="mb-3">
                <label class="form-label fw-bold small">Arquivo de Empréstimos (CSV)</label>
                <input type="file" name="csv" class="form-control form-control-sm" accept=".csv" required>
            </div>
            
            <button type="submit" class="btn btn-pill btn-sm px-4">
                <i class="bi bi-upload me-1"></i> Processar Ficheiro
            </button>
        </form>
    </div>
</div>

<?php include_once __DIR__ . "/../../includes/footer.php"; ?>