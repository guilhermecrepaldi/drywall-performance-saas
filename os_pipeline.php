<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/os_functions.php';

$page_title = 'Pipeline OS';
$active_nav = 'pipeline';
include 'includes/head.php';
?>

<style>
.pipeline-toolbar { display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:16px; }
.pipeline-filters { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.status-filters { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
.status-filters label { display:flex; align-items:center; gap:5px; background:var(--surface); border:1px solid var(--border); border-radius:6px; padding:6px 10px; font-size:12px; cursor:pointer; }
.kanban { display:grid; grid-template-columns:repeat(6, minmax(220px, 1fr)); gap:12px; align-items:start; overflow-x:auto; padding-bottom:8px; }
.kanban-col { background:var(--surface); border:1px solid var(--border); border-radius:8px; min-height:240px; overflow:hidden; }
.kanban-head { background:var(--navy); color:#fff; padding:10px 12px; display:flex; justify-content:space-between; align-items:center; gap:8px; }
.kanban-title { font-family:'Barlow Condensed',sans-serif; font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.7px; }
.kanban-count { background:rgba(255,255,255,.16); border-radius:999px; min-width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-weight:800; }
.kanban-body { padding:10px; display:flex; flex-direction:column; gap:10px; }
.os-card { border:1px solid var(--border); border-left:4px solid var(--accent); border-radius:8px; padding:10px; background:var(--surface-bright); cursor:pointer; box-shadow:var(--shadow-lg); transition: transform 0.15s, border-color 0.15s; }
.os-card.sem-nf { background:rgba(255, 82, 82, 0.05); border-color:var(--danger); border-left-color:var(--danger); }
.os-card:hover { border-color:var(--accent); transform:translateY(-2px); }
.os-card-title { display:flex; justify-content:space-between; gap:8px; font-weight:800; color:var(--navy); }
.os-card-meta { font-size:12px; color:var(--muted); margin-top:5px; line-height:1.45; }
.pipeline-modal-bg { display:none; position:fixed; inset:0; background:rgba(13,27,42,.6); z-index:800; align-items:center; justify-content:center; padding:18px; }
.pipeline-modal-bg.open { display:flex; }
.pipeline-modal { background:var(--surface); border:1px solid var(--border-bright); border-radius:var(--radius); width:100%; max-width:680px; overflow:hidden; box-shadow:var(--shadow-lg); }
.pipeline-modal-header { background:var(--surface-bright); color:var(--text-main); padding:14px 18px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); }
.pipeline-modal-title { font-size:16px; font-weight:700; }
.pipeline-modal-body { padding:18px; }
.pipeline-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:14px; }
.pipeline-link-box { background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:10px; word-break:break-all; font-size:11px; color:var(--accent); font-family:var(--font-mono); }
@media (max-width: 900px) { .kanban { grid-template-columns:repeat(2, minmax(240px,1fr)); overflow-x:visible; } }
@media (max-width: 768px) {
  .kanban { grid-template-columns:1fr; }
  .pipeline-filters { flex-direction:column; align-items:stretch; width:100%; }
  .pipeline-filters .form-field { width:100%; }
  .pipeline-modal { max-height:92vh; overflow:auto; }
}
</style>

<div class="pipeline-toolbar">
  <div class="pipeline-filters">
    <div class="form-field">
      <label>Cliente</label>
      <input type="text" id="f-cliente" placeholder="Filtrar cliente..." oninput="renderPipeline()">
    </div>
    <div class="form-field">
      <label>Período</label>
      <select id="f-periodo" onchange="renderPipeline()">
        <option value="">Todos</option>
        <option value="30">Últimos 30 dias</option>
        <option value="90">Últimos 90 dias</option>
      </select>
    </div>
  </div>
  <button class="btn btn-outline" onclick="carregarPipeline()">Atualizar</button>
</div>

<div class="status-filters" id="status-filters"></div>
<div class="kanban" id="kanban"></div>

<div class="pipeline-modal-bg" id="pipeline-modal">
  <div class="pipeline-modal">
    <div class="pipeline-modal-header">
      <div class="pipeline-modal-title" id="modal-title">OS</div>
      <button class="agenda-close" onclick="fecharPipelineModal()" type="button">×</button>
    </div>
    <div class="pipeline-modal-body">
      <div id="modal-content"></div>
      <div class="pipeline-actions" id="modal-actions"></div>
      <div id="modal-link" style="margin-top:12px"></div>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
let pipelineData = [];
let statusLabels = {};
let osAtual = null;

function moedaBR(v) { return 'R$ ' + (Number(v)||0).toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function escapeHtml(str) { return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s])); }
function publicUrl(token) { return `${window.location.origin}/aprovar_os_publico.php?token=${encodeURIComponent(token)}`; }

async function carregarPipeline() {
  const res = await fetch('api/os_api.php?acao=listar');
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao carregar pipeline.', 'error'); return; }
  pipelineData = json.dados || [];
  statusLabels = json.status || {};
  renderStatusFilters();
  renderPipeline();
}

function renderStatusFilters() {
  const html = Object.entries(statusLabels).map(([key,label]) => `
    <label><input type="checkbox" class="status-filter" value="${key}" checked onchange="renderPipeline()"> ${escapeHtml(label)}</label>
  `).join('');
  document.getElementById('status-filters').innerHTML = html;
}

function filteredData() {
  const ativos = Array.from(document.querySelectorAll('.status-filter:checked')).map(el => el.value);
  const cliente = document.getElementById('f-cliente').value.toLowerCase().trim();
  const periodo = parseInt(document.getElementById('f-periodo').value || '0', 10);
  const minTime = periodo ? Date.now() - periodo * 86400000 : null;
  return pipelineData.filter(os => {
    const statusOk = ativos.includes(os.status || 'rascunho');
    const clienteOk = !cliente || String(os.cliente_nome || '').toLowerCase().includes(cliente);
    const data = new Date(String(os.criado_em || os.emissao || '').replace(' ', 'T')).getTime();
    const periodoOk = !minTime || (data && data >= minTime);
    return statusOk && clienteOk && periodoOk;
  });
}

function renderPipeline() {
  const data = filteredData();
  const kanban = document.getElementById('kanban');
  kanban.innerHTML = Object.entries(statusLabels).map(([status,label]) => {
    const cards = data.filter(os => (os.status || 'rascunho') === status);
    return `<section class="kanban-col">
      <div class="kanban-head"><div class="kanban-title">${escapeHtml(label)}</div><div class="kanban-count">${cards.length}</div></div>
      <div class="kanban-body">${cards.map(renderCard).join('') || '<div class="muted" style="font-size:12px">Sem OS</div>'}</div>
    </section>`;
  }).join('');
}

function renderCard(os) {
  const semNf = ['concluido', 'pago'].includes(os.status || '') && !Number(os.nota_fiscal || 0) && !String(os.nota_fiscal_numero || '').trim();
  return `<div class="os-card ${semNf ? 'sem-nf' : ''}" onclick="abrirOsModal('${escapeHtml(os.id)}')">
    <div class="os-card-title"><span>${escapeHtml(os.codigo || os.id)}</span><span>${moedaBR(os.total_geral)}</span></div>
    <div class="os-card-meta">${escapeHtml(os.cliente_nome || 'Sem cliente')}<br>${escapeHtml(os.emissao || os.criado_em || '')}${semNf ? '<br><strong style="color:#be123c">Concluída sem NF</strong>' : ''}</div>
  </div>`;
}

function abrirOsModal(id) {
  osAtual = pipelineData.find(os => String(os.id) === String(id));
  if (!osAtual) return;
  document.getElementById('modal-title').textContent = `${osAtual.codigo || osAtual.id} · ${statusLabels[osAtual.status] || osAtual.status}`;
  document.getElementById('modal-content').innerHTML = `
    <div class="form-grid cols-2">
      <div><strong>Cliente</strong><br>${escapeHtml(osAtual.cliente_nome || '-')}</div>
      <div><strong>Valor</strong><br>${moedaBR(osAtual.total_geral)}</div>
      <div><strong>Telefone</strong><br>${escapeHtml(osAtual.cliente_tel || '-')}</div>
      <div><strong>Emissão</strong><br>${escapeHtml(osAtual.emissao || osAtual.criado_em || '-')}</div>
      <div><strong>NF</strong><br>${escapeHtml(osAtual.nota_fiscal_numero || (Number(osAtual.nota_fiscal || 0) ? 'Marcada' : 'Pendente'))}</div>
    </div>
    <div style="margin-top:12px"><strong>Status</strong><br>${escapeHtml(statusLabels[osAtual.status] || osAtual.status || '-')}</div>
  `;
  document.getElementById('modal-actions').innerHTML = Object.entries(statusLabels).map(([status,label]) => `
    <button class="btn ${status === osAtual.status ? 'btn-primary' : 'btn-outline'} btn-sm" onclick="moverStatus('${status}')">${escapeHtml(label)}</button>
  `).join('') + '<button class="btn btn-red btn-sm" onclick="gerarLink()">Gerar Link Aprovação</button>';
  renderLinkBox();
  document.getElementById('pipeline-modal').classList.add('open');
}

async function moverStatus(status) {
  const fd = new FormData();
  fd.append('os_id', osAtual.id);
  fd.append('novo_status', status);
  fd.append('csrf_token', csrfToken);
  const res = await fetch('api/os_api.php?acao=atualizar_status', { method:'POST', headers:{'X-CSRF-Token':csrfToken}, body:fd });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao mover OS.', 'error'); return; }
  toast(json.mensagem || 'Status atualizado.', 'success');
  fecharPipelineModal();
  carregarPipeline();
}

async function gerarLink() {
  const fd = new FormData();
  fd.append('os_id', osAtual.id);
  fd.append('csrf_token', csrfToken);
  const res = await fetch('api/os_api.php?acao=gerar_token', { method:'POST', headers:{'X-CSRF-Token':csrfToken}, body:fd });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao gerar link.', 'error'); return; }
  osAtual.token_aprovacao = json.token;
  const idx = pipelineData.findIndex(os => os.id === osAtual.id);
  if (idx >= 0) pipelineData[idx].token_aprovacao = json.token;
  renderLinkBox();
}

function renderLinkBox() {
  const el = document.getElementById('modal-link');
  if (!osAtual?.token_aprovacao) { el.innerHTML = ''; return; }
  const link = publicUrl(osAtual.token_aprovacao);
  const whatsText = encodeURIComponent(`Olá! Segue o link para visualizar e aprovar a OS ${osAtual.codigo || ''}: ${link}`);
  const tel = String(osAtual.cliente_tel || '').replace(/\D/g, '');
  const wa = `https://wa.me/${tel ? '55' + tel : ''}?text=${whatsText}`;
  el.innerHTML = `<div class="pipeline-link-box">${escapeHtml(link)}</div>
    <div class="pipeline-actions">
      <button class="btn btn-outline btn-sm" onclick="navigator.clipboard?.writeText('${escapeHtml(link)}');toast('Link copiado.','success')">Copiar link</button>
      <a class="btn btn-red btn-sm" target="_blank" href="${wa}">WhatsApp</a>
    </div>`;
}

function fecharPipelineModal() { document.getElementById('pipeline-modal').classList.remove('open'); }
document.getElementById('pipeline-modal').addEventListener('click', e => { if (e.target.id === 'pipeline-modal') fecharPipelineModal(); });
carregarPipeline();
</script>

<?php include 'includes/foot.php'; ?>
