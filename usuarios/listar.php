<?php
$titulo_pagina = "Leitores";
include("../auth/auth_guard.php");
include("../conexao.php");
include("../includes/header.php");
?>

<div class="container my-4">
  <div class="page-card">
    <div class="page-card__head d-flex justify-content-between align-items-center">
      <div>
        <h2 class="page-card__title m-0">Lista de Leitores</h2>
        <div class="text-muted small mt-1">Clique na linha para ver detalhes.</div>
      </div>

      <a class="btn btn-pill" href="cadastrar.php">
        <i class="bi bi-plus-lg"></i> Novo Leitor
      </a>
    </div>

    <!-- BUSCA -->
    <div class="row g-2 align-items-center mb-3">
      <div class="col-12 col-md-6">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0">
            <i class="bi bi-search"></i>
          </span>
          <input
            type="text"
            id="inputBusca"
            class="form-control border-start-0"
            placeholder="Buscar por nome, CPF ou email..."
            autocomplete="off">
        </div>
        <small class="text-muted">A busca atualiza automaticamente enquanto você digita.</small>
      </div>
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
          <tbody id="tabelaCorpo"></tbody>
        </table>
      </div>
    </div>

    <!-- ✅ Rodapé: paginação à esquerda e sem "Total" -->
    <div class="d-flex justify-content-start align-items-center p-4 border-top">
      <nav>
        <ul class="pagination mb-0" id="paginacaoLista"></ul>
      </nav>
    </div>
  </div>
</div>

<!-- ✅ MODAL DETALHES -->
<div class="modal fade" id="modalDetalhesLeitor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 18px;">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-vcard me-2"></i> Detalhes do leitor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="d-flex gap-3 align-items-center mb-3">
          <div style="width:72px;height:72px;border-radius:18px;background:rgba(0,0,0,.04);display:flex;align-items:center;justify-content:center;border:1px solid rgba(0,0,0,.08);">
            <i class="bi bi-person-circle" style="font-size: 40px; opacity: .55;"></i>
          </div>

          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="fw-bold" style="font-size: 22px;" id="detNome">-</div>
              <span class="badge rounded-pill" id="detStatusBadge" style="padding:.45rem .75rem;">-</span>
              <span class="badge rounded-pill bg-dark" style="padding:.45rem .75rem;">LEITOR</span>
            </div>
            <div class="text-muted small mt-1" id="detId">#-</div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-4">
            <div class="text-muted small">Email</div>
            <div class="fw-semibold" id="detEmail">-</div>
          </div>
          <div class="col-12 col-md-4">
            <div class="text-muted small">CPF</div>
            <div class="fw-semibold" id="detCpf">-</div>
          </div>
          <div class="col-12 col-md-4">
            <div class="text-muted small">Telefone</div>
            <div class="fw-semibold" id="detTelefone">-</div>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <a href="#" class="btn btn-pill" id="detBtnEditar">
          <i class="bi bi-pencil me-1"></i> Editar
        </a>
      </div>
    </div>
  </div>
</div>

<!-- MODAL DESATIVAR -->
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

<!-- MODAL REATIVAR -->
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

    const totalPaginas = Math.max(1, parseInt(dados.totalPaginas || 1, 10));

    let pagHtml = '';

    pagHtml += `<li class="page-item ${pagina === 1 ? 'disabled' : ''}">
    <button class="page-link" onclick="buscarDados(${pagina - 1})">Anterior</button>
  </li>`;

    for (let i = 1; i <= totalPaginas; i++) {
      pagHtml += `<li class="page-item ${i === pagina ? 'active' : ''}">
      <button class="page-link" onclick="buscarDados(${i})">${i}</button>
    </li>`;
    }

    pagHtml += `<li class="page-item ${pagina === totalPaginas ? 'disabled' : ''}">
    <button class="page-link" onclick="buscarDados(${pagina + 1})">Próxima</button>
  </li>`;

    document.getElementById('paginacaoLista').innerHTML = pagHtml;
  }

  // Debounce da busca
  let timer;
  document.getElementById('inputBusca').addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => buscarDados(1), 300);
  });

  document.addEventListener('DOMContentLoaded', () => buscarDados(1));

  /* =========================
     Clique na linha -> abre modal detalhes
     Ignora cliques em ações (editar/desativar/reativar)
  ========================= */
  document.addEventListener('click', (e) => {

    // botões que abrem modal de desativar/reativar
    const btn = e.target.closest('[data-bs-toggle="modal"]');
    if (btn) {
      const id = btn.dataset.id;
      const nome = btn.dataset.nome;

      if (btn.dataset.bsTarget === '#modalDesativar') {
        document.getElementById('desativarNome').textContent = nome;
        document.getElementById('desativarLink').href = `excluir.php?id=${id}`;
      } else if (btn.dataset.bsTarget === '#modalReativar') {
        document.getElementById('reativarNome').textContent = nome;
        document.getElementById('reativarLink').href = `reativar.php?id=${id}`;
      }
      return;
    }

    // não abrir detalhes se clicou na coluna de ações (inclui o editar)
    if (e.target.closest('.col-acoes')) return;

    // se clicou na linha
    const tr = e.target.closest('tr.row-click');
    if (!tr) return;

    const id = tr.dataset.id || '';
    const nome = tr.dataset.nome || '-';
    const cpf = tr.dataset.cpf || '';
    const email = tr.dataset.email || '';
    const telefone = tr.dataset.telefone || '';
    const ativo = (tr.dataset.ativo === '1');

    document.getElementById('detId').textContent = `#${id}`;
    document.getElementById('detNome').textContent = nome;
    document.getElementById('detCpf').textContent = cpf !== '' ? cpf : '-';
    document.getElementById('detEmail').textContent = email !== '' ? email : '-';
    document.getElementById('detTelefone').textContent = telefone !== '' ? telefone : '-';
    document.getElementById('detBtnEditar').href = `editar.php?id=${id}`;

    const badge = document.getElementById('detStatusBadge');
    if (ativo) {
      badge.className = "badge rounded-pill bg-success";
      badge.textContent = "Ativo";
    } else {
      badge.className = "badge rounded-pill bg-secondary";
      badge.textContent = "Desativado";
    }

    const modalEl = document.getElementById('modalDetalhesLeitor');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
</script>

<?php include("../includes/footer.php"); ?>