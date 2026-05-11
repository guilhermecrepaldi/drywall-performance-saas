<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/helpers.php';

$page_title = 'Agenda';
$active_nav = 'agenda';
$os_lista = ler_db('os');
include 'includes/head.php';
?>

<style>
.agenda-shell { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr); gap:18px; align-items:start; }
.agenda-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; flex-wrap:wrap; }
.agenda-month-title { font-family:'Barlow Condensed',sans-serif; font-size:24px; font-weight:800; color:var(--navy); text-transform:uppercase; }
.calendar-grid { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); border-top:1px solid var(--border); border-left:1px solid var(--border); background:#fff; }
.calendar-weekday { background:var(--navy); color:rgba(255,255,255,.76); font-family:'Barlow Condensed',sans-serif; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; padding:9px 8px; text-align:center; }
.calendar-day { min-height:104px; border-right:1px solid var(--border); border-bottom:1px solid var(--border); padding:8px; display:flex; flex-direction:column; gap:6px; background:#fff; cursor:pointer; transition:background .15s; }
.calendar-day:hover { background:var(--bg); }
.calendar-day.muted { background:#f8fafc; color:#a2b0bd; }
.calendar-day.today { outline:2px solid var(--red); outline-offset:-2px; }
.day-head { display:flex; align-items:center; justify-content:space-between; gap:6px; }
.day-num { font-family:'Barlow Condensed',sans-serif; font-size:16px; font-weight:800; color:var(--navy); }
.day-count { background:var(--red); color:#fff; border-radius:999px; min-width:22px; height:22px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; }
.day-events { display:flex; flex-direction:column; gap:4px; overflow:hidden; }
.day-event { font-size:11px; line-height:1.2; padding:4px 6px; border-radius:4px; background:#edf7f4; color:#0d6e5a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.agenda-filters { display:grid; grid-template-columns:repeat(4,minmax(120px,1fr)); gap:10px; margin-bottom:14px; }
.agenda-list-mobile { display:none; }
.agenda-modal-bg { display:none; position:fixed; inset:0; z-index:700; background:rgba(13,27,42,.58); align-items:center; justify-content:center; padding:18px; }
.agenda-modal-bg.open { display:flex; }
.agenda-modal { background:#fff; border-radius:8px; width:100%; max-width:760px; box-shadow:0 24px 70px rgba(0,0,0,.25); overflow:hidden; }
.agenda-modal-header { background:var(--navy); color:#fff; padding:14px 18px; display:flex; align-items:center; justify-content:space-between; }
.agenda-modal-title { font-family:'Barlow Condensed',sans-serif; font-size:16px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; }
.agenda-modal-body { padding:18px; max-height:78vh; overflow:auto; }
.agenda-modal-actions { display:flex; justify-content:space-between; gap:10px; margin-top:18px; flex-wrap:wrap; border-top:1px solid var(--border); padding-top:14px; }
.agenda-close { background:none; border:0; color:rgba(255,255,255,.7); font-size:24px; line-height:1; cursor:pointer; }
.tipo-pill { display:inline-flex; align-items:center; padding:3px 8px; border-radius:4px; background:#e8f8f3; color:#0d6e5a; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
@media (max-width: 900px) {
  .agenda-shell { grid-template-columns:1fr; }
  .agenda-filters { grid-template-columns:1fr 1fr; }
}
@media (max-width: 768px) {
  .calendar-grid { grid-template-columns:1fr; }
  .calendar-weekday { display:none; }
  .calendar-day { min-height:auto; border-right:1px solid var(--border); padding:12px; }
  .calendar-day.muted { display:none; }
  .day-head::before { content:attr(data-weekday); color:var(--muted); font-size:11px; font-weight:700; text-transform:uppercase; margin-right:auto; }
  .agenda-filters { grid-template-columns:1fr; }
  .agenda-table-wrap { display:none; }
  .agenda-list-mobile { display:flex; flex-direction:column; gap:10px; }
  .agenda-card-item { border:1px solid var(--border); border-radius:8px; padding:12px; background:#fff; display:flex; flex-direction:column; gap:8px; }
  .agenda-modal { max-height:92vh; overflow:auto; }
  .agenda-modal-body .form-grid.cols-2 { grid-template-columns:1fr; }
  .agenda-modal-body .span-2 { grid-column:span 1; }
  .btn { min-height:38px; justify-content:center; }
}
</style>

<div class="agenda-toolbar">
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <button class="btn btn-outline btn-sm" onclick="mudarMes(-1)">‹</button>
    <div class="agenda-month-title" id="agenda-title">Agenda</div>
    <button class="btn btn-outline btn-sm" onclick="mudarMes(1)">›</button>
  </div>
  <button class="btn btn-red" onclick="abrirNovoEvento()">+ Novo evento</button>
</div>

<div class="agenda-shell">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Calendário mensal</div>
      <button class="btn btn-ghost btn-sm" onclick="voltarHoje()">Hoje</button>
    </div>
    <div class="calendar-grid" id="calendar-grid"></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">Eventos do mês</div>
      <span id="agenda-count" style="font-size:12px;color:var(--muted)">0 eventos</span>
    </div>
    <div class="card-body">
      <div class="agenda-filters">
        <div class="form-field">
          <label>De</label>
          <input type="date" id="filtro-de" onchange="renderizarLista()">
        </div>
        <div class="form-field">
          <label>Até</label>
          <input type="date" id="filtro-ate" onchange="renderizarLista()">
        </div>
        <div class="form-field">
          <label>Status</label>
          <select id="filtro-status" onchange="renderizarLista()">
            <option value="">Todos</option>
            <option value="agendado">Agendado</option>
            <option value="confirmado">Confirmado</option>
            <option value="em_andamento">Em andamento</option>
            <option value="concluido">Concluído</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
        <div class="form-field">
          <label>Tipo</label>
          <select id="filtro-tipo" onchange="renderizarLista()">
            <option value="">Todos</option>
            <option value="visita">Visita</option>
            <option value="medicao">Medição</option>
            <option value="instalacao">Instalação</option>
            <option value="acompanhamento">Acompanhamento</option>
            <option value="outro">Outro</option>
          </select>
        </div>
      </div>
      <div class="form-field" style="margin-bottom:14px">
        <label>Cliente</label>
        <select id="filtro-cliente" onchange="renderizarLista()">
          <option value="">Todos os clientes</option>
        </select>
      </div>

      <div class="table-wrap agenda-table-wrap">
        <table>
          <thead>
            <tr><th>Data</th><th>Tipo</th><th>Título</th><th>Cliente</th><th>Status</th><th></th></tr>
          </thead>
          <tbody id="agenda-tbody"></tbody>
        </table>
      </div>
      <div class="agenda-list-mobile" id="agenda-mobile-list"></div>
    </div>
  </div>
</div>

<div class="agenda-modal-bg" id="agenda-modal">
  <div class="agenda-modal">
    <div class="agenda-modal-header">
      <div class="agenda-modal-title" id="modal-title">Novo evento</div>
      <button class="agenda-close" onclick="fecharModal()" type="button">×</button>
    </div>
    <div class="agenda-modal-body">
      <form id="agenda-form" onsubmit="salvarEvento(event)">
        <?= csrf_field() ?>
        <input type="hidden" id="evento-id" name="id">
        <div class="form-grid cols-2">
          <div class="form-field span-2">
            <label>Título *</label>
            <input type="text" id="evento-titulo" name="titulo" required>
          </div>
          <div class="form-field">
            <label>Data e hora *</label>
            <input type="datetime-local" id="evento-data" name="data_inicio" required>
          </div>
          <div class="form-field">
            <label>Tipo</label>
            <select id="evento-tipo" name="tipo">
              <option value="visita">Visita</option>
              <option value="medicao">Medição</option>
              <option value="instalacao">Instalação</option>
              <option value="acompanhamento">Acompanhamento</option>
              <option value="outro">Outro</option>
            </select>
          </div>
          <div class="form-field">
            <label>Status</label>
            <select id="evento-status" name="status">
              <option value="agendado">Agendado</option>
              <option value="confirmado">Confirmado</option>
              <option value="em_andamento">Em andamento</option>
              <option value="concluido">Concluído</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div class="form-field">
            <label>Cliente</label>
            <select id="evento-cliente" name="cliente_id">
              <option value="">Sem cliente</option>
            </select>
          </div>
          <div class="form-field span-2">
            <label>OS vinculada</label>
            <select id="evento-os" name="os_id">
              <option value="">Sem OS</option>
              <?php foreach ($os_lista as $os): ?>
              <option value="<?= htmlspecialchars($os['id']) ?>">
                <?= htmlspecialchars(($os['codigo'] ?? 'OS') . ' - ' . ($os['cliente_nome'] ?? 'Sem cliente')) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field span-2">
            <label>Observações</label>
            <textarea id="evento-descricao" name="descricao"></textarea>
          </div>
        </div>
        <div class="agenda-modal-actions">
          <button type="button" id="btn-delete" class="btn btn-ghost" style="color:var(--red);display:none" onclick="deletarEventoAtual()">Excluir</button>
          <div style="display:flex;gap:10px;margin-left:auto">
            <button type="button" class="btn btn-outline" onclick="fecharModal()">Cancelar</button>
            <button type="submit" class="btn btn-red">Salvar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
let dataAtual = new Date();
let eventos = [];
let clientes = [];

const nomesMes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const semana = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
const tipoLabels = { visita:'Visita', medicao:'Medição', instalacao:'Instalação', acompanhamento:'Acompanhamento', outro:'Outro' };
const statusLabels = { agendado:'Agendado', confirmado:'Confirmado', em_andamento:'Em andamento', concluido:'Concluído', cancelado:'Cancelado' };

function pad(n) { return String(n).padStart(2, '0'); }
function dataKey(d) { return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
function toDatetimeLocal(valor) { return valor ? valor.replace(' ', 'T').slice(0, 16) : ''; }
function formatarData(valor) {
  if (!valor) return '-';
  const d = new Date(valor.replace(' ', 'T'));
  return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

async function carregarClientes() {
  const res = await fetch('api/clientes.php');
  clientes = await res.json();
  const opts = '<option value="">Sem cliente</option>' + clientes.map(c => `<option value="${c.id}">${escapeHtml(c.nome || '')}</option>`).join('');
  document.getElementById('evento-cliente').innerHTML = opts;
  document.getElementById('filtro-cliente').innerHTML = '<option value="">Todos os clientes</option>' + clientes.map(c => `<option value="${c.id}">${escapeHtml(c.nome || '')}</option>`).join('');
}

async function carregarAgenda() {
  const mes = dataAtual.getMonth() + 1;
  const ano = dataAtual.getFullYear();
  const res = await fetch(`api/agenda_api.php?acao=listar&mes=${pad(mes)}&ano=${ano}`);
  const json = await res.json();
  eventos = json.sucesso ? json.dados : [];
  renderizarCalendario();
  renderizarLista();
}

function renderizarCalendario() {
  document.getElementById('agenda-title').textContent = `${nomesMes[dataAtual.getMonth()]} ${dataAtual.getFullYear()}`;
  const grid = document.getElementById('calendar-grid');
  grid.innerHTML = semana.map(d => `<div class="calendar-weekday">${d}</div>`).join('');

  const ano = dataAtual.getFullYear();
  const mes = dataAtual.getMonth();
  const primeiro = new Date(ano, mes, 1);
  const inicio = new Date(ano, mes, 1 - primeiro.getDay());
  const hojeKey = dataKey(new Date());
  const eventosPorDia = eventos.reduce((acc, ev) => {
    const key = (ev.data_inicio || '').slice(0, 10);
    acc[key] = acc[key] || [];
    acc[key].push(ev);
    return acc;
  }, {});

  for (let i = 0; i < 42; i++) {
    const dia = new Date(inicio);
    dia.setDate(inicio.getDate() + i);
    const key = dataKey(dia);
    const lista = eventosPorDia[key] || [];
    const muted = dia.getMonth() !== mes ? ' muted' : '';
    const today = key === hojeKey ? ' today' : '';
    const eventosHtml = lista.slice(0, 3).map(ev => `<div class="day-event">${escapeHtml(ev.titulo)}</div>`).join('');
    grid.insertAdjacentHTML('beforeend', `
      <div class="calendar-day${muted}${today}" onclick="abrirNovoEvento('${key}')">
        <div class="day-head" data-weekday="${semana[dia.getDay()]}">
          <span class="day-num">${dia.getDate()}</span>
          ${lista.length ? `<span class="day-count">${lista.length}</span>` : ''}
        </div>
        <div class="day-events">${eventosHtml}</div>
      </div>
    `);
  }
}

function eventosFiltrados() {
  const de = document.getElementById('filtro-de').value;
  const ate = document.getElementById('filtro-ate').value;
  const status = document.getElementById('filtro-status').value;
  const tipo = document.getElementById('filtro-tipo').value;
  const cliente = document.getElementById('filtro-cliente').value;
  return eventos.filter(ev => {
    const dia = (ev.data_inicio || '').slice(0, 10);
    return (!de || dia >= de) && (!ate || dia <= ate) &&
      (!status || ev.status === status) &&
      (!tipo || ev.tipo === tipo) &&
      (!cliente || String(ev.cliente_id || '') === cliente);
  });
}

function renderizarLista() {
  const lista = eventosFiltrados();
  document.getElementById('agenda-count').textContent = `${lista.length} evento${lista.length === 1 ? '' : 's'}`;
  const rows = lista.map(ev => `
    <tr>
      <td class="muted">${formatarData(ev.data_inicio)}</td>
      <td><span class="tipo-pill">${tipoLabels[ev.tipo] || ev.tipo}</span></td>
      <td><strong>${escapeHtml(ev.titulo)}</strong></td>
      <td>${escapeHtml(ev.cliente_nome || '-')}<br><span class="muted" style="font-size:11px">${escapeHtml(ev.cliente_telefone || '')}</span></td>
      <td>${statusLabels[ev.status] || ev.status}</td>
      <td><button class="btn btn-outline btn-sm" onclick="editarEvento(${ev.id})">Editar</button></td>
    </tr>
  `).join('');
  document.getElementById('agenda-tbody').innerHTML = rows || '<tr><td colspan="6" class="muted">Nenhum evento encontrado.</td></tr>';
  document.getElementById('agenda-mobile-list').innerHTML = lista.map(ev => `
    <div class="agenda-card-item">
      <div style="display:flex;justify-content:space-between;gap:10px">
        <strong>${escapeHtml(ev.titulo)}</strong>
        <span class="tipo-pill">${tipoLabels[ev.tipo] || ev.tipo}</span>
      </div>
      <div class="muted">${formatarData(ev.data_inicio)}</div>
      <div>${escapeHtml(ev.cliente_nome || '-')}</div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
        <span>${statusLabels[ev.status] || ev.status}</span>
        <button class="btn btn-outline btn-sm" onclick="editarEvento(${ev.id})">Editar</button>
      </div>
    </div>
  `).join('') || '<div class="muted">Nenhum evento encontrado.</div>';
}

function mudarMes(delta) {
  dataAtual = new Date(dataAtual.getFullYear(), dataAtual.getMonth() + delta, 1);
  carregarAgenda();
}

function voltarHoje() {
  dataAtual = new Date();
  carregarAgenda();
}

function abrirNovoEvento(data = '') {
  document.getElementById('agenda-form').reset();
  document.getElementById('evento-id').value = '';
  document.getElementById('evento-status').value = 'agendado';
  document.getElementById('modal-title').textContent = 'Novo evento';
  document.getElementById('btn-delete').style.display = 'none';
  if (data) document.getElementById('evento-data').value = `${data}T09:00`;
  document.getElementById('agenda-modal').classList.add('open');
}

async function editarEvento(id) {
  const res = await fetch(`api/agenda_api.php?acao=obter&id=${id}`);
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Evento não encontrado.', 'error'); return; }
  const ev = json.dados;
  document.getElementById('evento-id').value = ev.id;
  document.getElementById('evento-titulo').value = ev.titulo || '';
  document.getElementById('evento-data').value = toDatetimeLocal(ev.data_inicio);
  document.getElementById('evento-tipo').value = ev.tipo || 'visita';
  document.getElementById('evento-status').value = ev.status || 'agendado';
  document.getElementById('evento-cliente').value = ev.cliente_id || '';
  document.getElementById('evento-os').value = ev.os_id || '';
  document.getElementById('evento-descricao').value = ev.descricao || '';
  document.getElementById('modal-title').textContent = 'Editar evento';
  document.getElementById('btn-delete').style.display = 'inline-flex';
  document.getElementById('agenda-modal').classList.add('open');
}

async function salvarEvento(e) {
  e.preventDefault();
  const form = document.getElementById('agenda-form');
  const id = document.getElementById('evento-id').value;
  const fd = new FormData(form);
  const acao = id ? 'atualizar' : 'criar';
  const res = await fetch(`api/agenda_api.php?acao=${acao}`, {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken },
    body: fd
  });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao salvar evento.', 'error'); return; }
  toast(json.mensagem || 'Evento salvo.', 'success');
  fecharModal();
  carregarAgenda();
}

async function deletarEventoAtual() {
  const id = document.getElementById('evento-id').value;
  if (!id || !confirm('Excluir este evento?')) return;
  const fd = new FormData();
  fd.append('id', id);
  fd.append('csrf_token', csrfToken);
  const res = await fetch('api/agenda_api.php?acao=deletar', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken },
    body: fd
  });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao excluir evento.', 'error'); return; }
  toast(json.mensagem || 'Evento excluído.', 'success');
  fecharModal();
  carregarAgenda();
}

function fecharModal() { document.getElementById('agenda-modal').classList.remove('open'); }
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}

document.getElementById('agenda-modal').addEventListener('click', e => {
  if (e.target.id === 'agenda-modal') fecharModal();
});

carregarClientes().then(carregarAgenda);
</script>

<?php include 'includes/foot.php'; ?>
