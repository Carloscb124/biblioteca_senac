<?php
include("../../auth/auth_guard.php");
include("../../conexao.php");

$filename = "usuarios_biblioteca_" . date('d-m-Y') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$saida = fopen("php://output", "w");
fprintf($saida, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para acentos

// Cabeçalho com seus campos reais
fputcsv($saida, ["Nome", "Email", "Telefone", "CPF", "Perfil", "Ativo"], ";");

$sql = "SELECT nome, email, telefone, cpf, perfil, ativo FROM usuarios ORDER BY nome ASC";
$r = mysqli_query($conn, $sql);

while ($u = mysqli_fetch_assoc($r)) {
    fputcsv($saida, [
        $u['nome'],
        $u['email'],
        "\t" . $u['telefone'], // Força texto no Excel
        "\t" . $u['cpf'],      // Força texto no Excel
        $u['perfil'],
        $u['ativo'] ? 'Sim' : 'Não'
    ], ";");
}

fclose($saida);
exit;