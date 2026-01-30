<?php
$titulo_pagina = "Início";
include("includes/header.php");
?>

<section class="hero-wrap">
  <div class="container hero-grid">

    <!-- CARD ESQUERDA -->
    <div class="hero-card">
      <h1 class="hero-title">
        Sistema <span>Biblioteca</span>
      </h1>

      <p class="hero-sub">
        Bem-vindo ao Sistema de Gerenciamento. Organização e conhecimento em um só lugar.
      </p>

      <hr class="hero-line">

      <p class="hero-tip">
        <span class="tip-ic">ⓘ</span>
        Use o menu superior para navegar entre os módulos.
      </p>

      <a class="btn hero-btn" href="<?= $base ?>/livros/listar.php">
        <span class="btn-ic">📖</span> Ver Acervo
      </a>
    </div>

    <!-- ILUSTRAÇÃO DIREITA -->
    <div class="hero-illus">
      <img src="<?= $base ?>/assets/reader.png" alt="Ilustração leitura">
    </div>

  </div>
</section>

<?php include("includes/footer.php"); ?>
