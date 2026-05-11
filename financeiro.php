<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/financeiro_functions.php';

$page_title = 'Financeiro';
$active_nav = 'financeiro';
$os_lista = array_reverse(ler_db('os'));
$mes_atual = (int)date('m');
$ano_atual = (int)date('Y');
include 'includes/head.php';
?>

<style>
.finance-shell { display:grid; grid-template-columns:minmax(320px,.9fr) minmax(0,1.1fr); gap:18px; align-items:start; }
.finance-breakdown { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:14px; }
.finance-metric { background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px; }
.finance-metric .label { font-size:10px; font-weight:700; color:var(--muted); letter-spacing:1px; text-transform:uppercase; margin-bottom:4px; }
.finance-metric .value { font-family:'Barlow Condensed',sans-serif; font-size:22px; font-weight:800; color:var(--navy); line-height:1; }
.finance-metric.good .value { color:var(--green); }
.finance-metric.bad .value { color:var(--red); }
.chart-row { display:grid; grid-template-columns:minmax(120px, 180px) 1fr 92px; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid var(--border); }
.chart-row:last-child { border-bottom:none; }
.bar-track { height:14px; background:#e7edf3; border-radius:999px; overflow:hidden; }
.bar-fill { height:100%; background:linear-gradient(90deg,var(--green),#1abc9c); min-width:3px; }
.bar-fill.neg { background:var(--red); }
.pending-list { display:flex; flex-direction:column; gap:8px; }
.pending-item { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:10px 12px; background:var(--bg); border:1px solid var(--border); border-radius:8px; }
@media (max-width: 900px) {
  .finance-shell { grid-template-columns:1fr; }
  .finance-breakdown { grid-template-columns:1fr 1fr; }
}
@media (max-width: 640px) {
  .finance-breakdown { grid-template-columns:1fr; }
  .chart-row { grid-template-columns:1fr; gap:6px; }
  .pending-item { align-items:flex-start; flex-direction:column; }
}
</style>

<div class="finance-shell">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Calculadora de custo real</div>
    </div>
    <div class="card-body">
      <form id="finance-form" onsubmit="salvarCustos(event)">
        <?= csrf_field() ?>
        <div class="form-field" style="margin-bottom:14px">
          <label>OS</label>
          <select id="os_id" name="os_id" onchange="carregarCalculo()" required>
            <option value="">Selecione uma OS...</option>
            <?php foreach ($os_lista as $os): ?>
            <option value="<?= htmlspecialchars($os['id']) ?>">
              <?= htmlspecialchars(($os['codigo'] ?? 'OS') . ' - ' . ($os['cliente_nome'] ?? 'Sem cliente') . ' - ' . moeda((float)($os['total_geral'] ?? 0))) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-grid cols-2">
          <div class="form-field">
            <label>Custo material</label>
            <input type="number" step="0.01" min="0" id="custo_material" name="custo_material" oninput="recalcularLocal()">
          </div>
          <div class="form-field">
            <label>Horas mão de obra</label>
            <input type="number" step="0.25" min="0" id="horas_mao_obra" name="horas_mao_obra" oninput="recalcularLocal()">
          </div>
          <div class="form-field">
            <label>Valor hora</label>
            <input type="number" step="0.01" min="0" id="valor_hora" name="valor_hora" oninput="recalcularLocal()">
          </div>
          <div class="form-field">
            <label>Custo mão de obra</label>
            <input type="number" step="0.01" min="0" id="custo_mao_obra" name="custo_mao_obra" oninput="recalcularLocal()">
          </div>
          <div class="form-field">
            <label>Overhead (%)</label>
            <input type="number" step="0.01" min="0" id="overhead" name="overhead" oninput="recalcularLocal()">
          </div>
          <div class="form-field">
            <label>Margem desejada (%)</label>
            <input type="number" step="0.01" min="0" max="95" id="margem" name="margem" oninput="recalcularLocal()">
          </div>
          <div class="form-field span-2">
            <label>Custo real final</label>
            <input type="number" step="0.01" min="0" id="custo_real" name="custo_real" oninput="recalcularLocal()">
            <span class="hint">Use este campo quando o custo realizado for diferente do previsto.</span>
          </div>
        </div>
        <div class="finance-breakdown">
          <div class="finance-metric"><div class="label">Custo total previsto</div><div class="value" id="m-custo-total">R$ 0,00</div></div>
          <div class="finance-metric"><div class="label">Preço sugerido</div><div class="value" id="m-sugerido">R$ 0,00</div></div>
          <div class="finance-metric"><div class="label">Preço orçado</div><div class="value" id="m-orcado">R$ 0,00</div></div>
          <div class="finance-metric" id="m-lucro-box"><div class="label">Lucro real</div><div class="value" id="m-lucro">R$ 0,00</div></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
          <button class="btn btn-red" type="submit">💾 Salvar custos</button>
        </div>
      </form>
    </div>
  </div>

  <div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Resumo mensal</div>
        <div style="display:flex;gap:8px;align-items:center">
          <select id="rel-mes" onchange="carregarRelatorio()" style="font-size:12px;border:1px solid var(--border);border-radius:4px;padding:6px 8px">
            <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === $mes_atual ? 'selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
            <?php endfor; ?>
          </select>
          <input id="rel-ano" type="number" value="<?= $ano_atual ?>" onchange="carregarRelatorio()" style="width:84px;font-size:12px;border:1px solid var(--border);border-radius:4px;padding:6px 8px">
        </div>
      </div>
      <div class="card-body">
        <div class="finance-breakdown" style="margin-top:0">
          <div class="finance-metric"><div class="label">Receita</div><div class="value" id="r-receita">R$ 0,00</div></div>
          <div class="finance-metric"><div class="label">Custos</div><div class="value" id="r-custos">R$ 0,00</div></div>
          <div class="finance-metric" id="r-lucro-box"><div class="label">Lucro</div><div class="value" id="r-lucro">R$ 0,00</div></div>
          <div class="finance-metric"><div class="label">A receber</div><div class="value" id="r-pendentes">R$ 0,00</div></div>
          <div class="finance-metric"><div class="label">NF emitida/prevista</div><div class="value" id="r-nf-valor">R$ 0,00</div></div>
          <div class="finance-metric bad"><div class="label">Concluído sem NF</div><div class="value" id="r-nf-pendente">R$ 0,00</div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Rentabilidade por cliente</div></div>
      <div class="card-body" id="chart-clientes"></div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Pagamentos pendentes</div></div>
      <div class="card-body">
        <div class="pending-list" id="pendentes-list"></div>
      </div>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
let calculoAtual = null;

function moedaBR(v) {
  return 'R$ ' + (Number(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 });
}

async function carregarCalculo() {
  const osId = document.getElementById('os_id').value;
  if (!osId) return;
  const res = await fetch('api/financeiro_api.php?acao=calcular&os_id=' + encodeURIComponent(osId));
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao calcular OS.', 'error'); return; }
  calculoAtual = json.dados;
  preencherCalculo(calculoAtual);
}

function preencherCalculo(d) {
  document.getElementById('custo_material').value = d.custo_material.toFixed(2);
  document.getElementById('horas_mao_obra').value = d.horas_mao_obra.toFixed(2);
  document.getElementById('valor_hora').value = d.valor_hora.toFixed(2);
  document.getElementById('custo_mao_obra').value = d.custo_mao_obra.toFixed(2);
  document.getElementById('overhead').value = d.overhead.toFixed(2);
  document.getElementById('margem').value = d.margem.toFixed(2);
  document.getElementById('custo_real').value = d.custo_real.toFixed(2);
  renderMetricas(d);
}

function recalcularLocal() {
  if (!calculoAtual) return;
  const material = parseFloat(document.getElementById('custo_material').value) || 0;
  const horas = parseFloat(document.getElementById('horas_mao_obra').value) || 0;
  const valorHora = parseFloat(document.getElementById('valor_hora').value) || 0;
  let maoObra = parseFloat(document.getElementById('custo_mao_obra').value);
  if (document.activeElement.id === 'horas_mao_obra' || document.activeElement.id === 'valor_hora') {
    maoObra = horas * valorHora;
    document.getElementById('custo_mao_obra').value = maoObra.toFixed(2);
  }
  maoObra = Number.isFinite(maoObra) ? maoObra : 0;
  const overhead = parseFloat(document.getElementById('overhead').value) || 0;
  const margem = parseFloat(document.getElementById('margem').value) || 0;
  const custoTotal = (material + maoObra) * (1 + overhead / 100);
  const sugerido = margem >= 100 ? custoTotal : custoTotal / Math.max(0.01, 1 - margem / 100);
  const custoReal = parseFloat(document.getElementById('custo_real').value) || custoTotal;
  const orcado = calculoAtual.valor_orcado || 0;
  renderMetricas({ custo_total:custoTotal, valor_sugerido:sugerido, valor_orcado:orcado, lucro:orcado - custoReal, lucro_percentual: orcado ? ((orcado - custoReal) / orcado) * 100 : 0 });
}

function renderMetricas(d) {
  document.getElementById('m-custo-total').textContent = moedaBR(d.custo_total);
  document.getElementById('m-sugerido').textContent = moedaBR(d.valor_sugerido);
  document.getElementById('m-orcado').textContent = moedaBR(d.valor_orcado);
  document.getElementById('m-lucro').textContent = moedaBR(d.lucro) + ' (' + (Number(d.lucro_percentual) || 0).toFixed(1) + '%)';
  document.getElementById('m-lucro-box').className = 'finance-metric ' + (d.lucro >= 0 ? 'good' : 'bad');
}

async function salvarCustos(e) {
  e.preventDefault();
  const fd = new FormData(document.getElementById('finance-form'));
  const res = await fetch('api/financeiro_api.php?acao=salvar', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken },
    body: fd
  });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao salvar.', 'error'); return; }
  toast(json.mensagem || 'Custos salvos.', 'success');
  await carregarCalculo();
  await carregarRelatorio();
}

async function carregarRelatorio() {
  const mes = document.getElementById('rel-mes').value;
  const ano = document.getElementById('rel-ano').value;
  const res = await fetch(`api/financeiro_api.php?acao=relatorio&mes=${mes}&ano=${ano}`);
  const json = await res.json();
  if (!json.sucesso) return;
  const d = json.dados;
  document.getElementById('r-receita').textContent = moedaBR(d.totais.receita);
  document.getElementById('r-custos').textContent = moedaBR(d.totais.custos);
  document.getElementById('r-lucro').textContent = moedaBR(d.totais.lucro);
  document.getElementById('r-pendentes').textContent = moedaBR(d.totais.pendentes);
  document.getElementById('r-nf-valor').textContent = moedaBR(d.totais.nf_valor);
  document.getElementById('r-nf-pendente').textContent = moedaBR(d.totais.nf_sem_numero);
  document.getElementById('r-lucro-box').className = 'finance-metric ' + (d.totais.lucro >= 0 ? 'good' : 'bad');
  renderChart(d.por_cliente || []);
  renderPendentes(d.pendentes || []);
}

function renderChart(rows) {
  const max = Math.max(1, ...rows.map(r => Math.abs(r.lucro)));
  document.getElementById('chart-clientes').innerHTML = rows.length ? rows.map(r => {
    const width = Math.max(3, Math.round((Math.abs(r.lucro) / max) * 100));
    return `<div class="chart-row">
      <strong>${escapeHtml(r.cliente)}</strong>
      <div class="bar-track"><div class="bar-fill ${r.lucro < 0 ? 'neg' : ''}" style="width:${width}%"></div></div>
      <span style="text-align:right;font-weight:700;color:${r.lucro < 0 ? 'var(--red)' : 'var(--green)'}">${moedaBR(r.lucro)}</span>
    </div>`;
  }).join('') : '<div class="muted">Sem OS no período.</div>';
}

function renderPendentes(rows) {
  document.getElementById('pendentes-list').innerHTML = rows.length ? rows.map(r => `
    <div class="pending-item">
      <div><strong>${escapeHtml(r.codigo || '-')}</strong><br><span class="muted">${escapeHtml(r.cliente_nome || '-')} · ${escapeHtml(r.status || '')}</span></div>
      <div style="font-weight:800;color:var(--navy)">${moedaBR(r.valor)}</div>
    </div>
  `).join('') : '<div class="muted">Nenhum pagamento pendente no período.</div>';
}

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}

carregarRelatorio();
</script>

<?php include 'includes/foot.php'; ?>
