<?php
$titulo_pagina = "Leitores";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head d-flex justify-content-between align-items-center">
      <h2 class="page-card__title m-0">Lista de Leitores</h2>
      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i> Novo Leitor
      </a>
    </div>

    <div class="px-4 pb-3">
        <div class="input-group mb-1" style="max-width: 600px;">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="text" id="inputBusca" class="form-control border-start-0" placeholder="Buscar por nome, CPF ou email...">
        </div>
        <small class="text-muted">A busca atualiza automaticamente enquanto você digita.</small>
    </div>

    <div class="table-base-wrap">
      <div class="table-responsive">
        <table class="table table-base align-middle mb-0">
          <thead>
            <tr>
              <th class="col-id">ID</th>
              <th>Nome</th>
              <th>CPF</th>
              <th>Email</th>
              <th>Telefone</th>
              <th>Status</th>
              <th class="text-end col-acoes">Ações</th>
            </tr>
          </thead>
          <tbody id="tabelaCorpo">
            </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center p-4 border-top">
      <div class="text-muted small" id="infoRegistros"></div>
      <nav>
        <ul class="pagination mb-0" id="paginacaoLista">
            </ul>
      </nav>
    </div>
  </div>
</div>

<div class="modal fade" id="modalDesativar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-x me-2"></i>Desativar leitor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Você está prestes a desativar:</p>
        <p class="fw-semibold mb-2" id="desativarNome">-</p>
        <div class="alert alert-warning mb-0">Ele não será apagado. Apenas ficará desativado.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-danger" id="desativarLink">Desativar</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalReativar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Reativar leitor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Você está prestes a reativar:</p>
        <p class="fw-semibold mb-2" id="reativarNome">-</p>
        <div class="alert alert-success mb-0">O leitor voltará a ficar ativo no sistema.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <a href="#" class="btn btn-success" id="reativarLink">Reativar</a>
      </div>
    </div>
  </div>
</div>

<script>
let paginaAtual = 1;

async function buscarDados(pagina = 1) {
    paginaAtual = pagina;
    const busca = document.getElementById('inputBusca').value;
    
    const response = await fetch(`buscar_leitores.php?q=${encodeURIComponent(busca)}&p=${pagina}`);
    const dados = await response.json();

    document.getElementById('tabelaCorpo').innerHTML = dados.html;
    document.getElementById('infoRegistros').textContent = `Total: ${dados.totalRegistros} leitores`;

    // Renderização da Paginação igual ao seu design
    let pagHtml = '';
    
    // Botão Anterior
    pagHtml += `<li class="page-item ${pagina === 1 ? 'disabled' : ''}">
        <button class="page-link" onclick="buscarDados(${pagina - 1})">Anterior</button>
    </li>`;

    // Números das páginas
    for (let i = 1; i <= dados.totalPaginas; i++) {
        pagHtml += `<li class="page-item ${i === pagina ? 'active' : ''}">
            <button class="page-link" onclick="buscarDados(${i})">${i}</button>
        </li>`;
    }

    // Botão Próxima
    pagHtml += `<li class="page-item ${pagina === dados.totalPaginas ? 'disabled' : ''}">
        <button class="page-link" onclick="buscarDados(${pagina + 1})">Próxima</button>
    </li>`;

    document.getElementById('paginacaoLista').innerHTML = pagHtml;
}

// Debounce para não sobrecarregar o banco
let timer;
document.getElementById('inputBusca').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => buscarDados(1), 300);
});

// Inicialização e delegação para Modais
document.addEventListener('DOMContentLoaded', () => buscarDados(1));
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-bs-toggle="modal"]');
    if (!btn) return;
    const id = btn.dataset.id;
    const nome = btn.dataset.nome;
    if (btn.dataset.bsTarget === '#modalDesativar') {
        document.getElementById('desativarNome').textContent = nome;
        document.getElementById('desativarLink').href = `excluir.php?id=${id}`;
    } else {
        document.getElementById('reativarNome').textContent = nome;
        document.getElementById('reativarLink').href = `reativar.php?id=${id}`;
    }
});
</script>

<?php include("../includes/footer.php"); ?>