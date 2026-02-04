<?php
$titulo_pagina = "Importar/Exportar CSV";
include("../../auth/auth_guard.php");
include("../../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head">
      <h2 class="page-card__title">CSV</h2>
      <a class="btn btn-pill" href="<?= $base ?>/relatorios/index.php">
        <i class="bi bi-arrow-left"></i>
        Voltar aos Relatórios
      </a>
    </div>

    <div class="row g-3">

      <div class="col-12 col-lg-6">
        <div class="p-3 rounded-4 border bg-white">
          <h5 class="fw-bold mb-2"><i class="bi bi-download me-2"></i>Exportar</h5>
          <p class="text-muted mb-3">Baixe arquivos CSV do sistema (para Excel, Google Sheets, etc.).</p>

          <div class="d-grid gap-2">
            <a class="btn btn-pill" href="exportar_livros.php">
              <i class="bi bi-book"></i>
              Exportar Livros (Acervo)
            </a>

            <a class="btn btn-pill" href="exportar_emprestimos.php">
              <i class="bi bi-arrow-repeat"></i>
              Exportar Empréstimos (Histórico)
            </a>
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="p-3 rounded-4 border bg-white">
          <h5 class="fw-bold mb-2"><i class="bi bi-upload me-2"></i>Importar</h5>
          <p class="text-muted mb-3">Envie um CSV para cadastrar dados em lote.</p>

          <div class="d-grid gap-2">
            <a class="btn btn-pill" href="importar_livros.php">
              <i class="bi bi-book"></i>
              Importar Livros
            </a>

            <a class="btn btn-pill" href="importar_emprestimos.php">
              <i class="bi bi-arrow-repeat"></i>
              Importar Empréstimos
            </a>
            
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include("../../includes/footer.php"); ?>
