<?php
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aprovação de OS | Premium Detailing</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f0f4f8;color:#1a2a3a;min-height:100vh;padding:18px}
.public-wrap{max-width:860px;margin:0 auto}
.public-head{background:#0d1b2a;color:#fff;border-radius:10px 10px 0 0;padding:20px}
.brand{font-size:22px;font-weight:800;text-transform:uppercase;letter-spacing:.8px}.brand span{color:#e74c3c}
.doc{background:#fff;border-radius:0 0 10px 10px;box-shadow:0 10px 30px rgba(13,27,42,.12);overflow:hidden}
.sec{padding:18px;border-bottom:1px solid #d0d9e2}.sec:last-child{border-bottom:0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.field{background:#f7fafc;border:1px solid #d0d9e2;border-radius:8px;padding:12px}.field label{display:block;font-size:11px;color:#5a7080;text-transform:uppercase;font-weight:700;margin-bottom:5px}.field strong{font-size:16px;color:#0d1b2a}
table{width:100%;border-collapse:collapse;font-size:14px}th{background:#0d1b2a;color:#fff;text-align:left;padding:9px}td{border-bottom:1px solid #d0d9e2;padding:9px}.r{text-align:right;font-weight:700}
.total{display:flex;justify-content:flex-end;margin-top:12px;font-size:22px;font-weight:800;color:#0d1b2a}
.approve-form{display:grid;gap:12px}.approve-form input{width:100%;padding:12px;border:1px solid #d0d9e2;border-radius:8px;font-size:16px}.check{display:flex;align-items:flex-start;gap:8px;font-size:14px}.check input{width:auto;margin-top:3px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:14px 18px;font-weight:800;cursor:pointer;text-decoration:none}.btn-red{background:#c0392b;color:#fff}.btn-dark{background:#0d1b2a;color:#fff}.btn-row{display:flex;gap:10px;flex-wrap:wrap}.msg{padding:12px;border-radius:8px;margin-bottom:12px}.ok{background:#dcfce7;color:#166534}.err{background:#fee2e2;color:#991b1b}.muted{color:#5a7080}
@media(max-width:680px){body{padding:0}.public-head,.doc{border-radius:0}.grid{grid-template-columns:1fr}.btn-row{flex-direction:column}.btn{width:100%}table{font-size:12px}}
</style>
</head>
<body>
<div class="public-wrap">
  <div class="public-head"><div class="brand">Detailing <span>Performance</span></div><div style="opacity:.65;margin-top:4px">Aprovação digital de orçamento</div></div>
  <div class="doc">
    <div class="sec" id="msg"></div>
    <div id="conteudo"></div>
  </div>
</div>

<script>
const token = <?= json_encode($token) ?>;
let osData = null;
function moedaBR(v){return 'R$ '+(Number(v)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});}
function escapeHtml(str){return String(str??'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));}
function setMsg(text,type=''){document.getElementById('msg').innerHTML=text?`<div class="msg ${type}">${escapeHtml(text)}</div>`:'';}
async function carregar(){
  if(!token){setMsg('Token ausente.','err');return;}
  const res=await fetch('api/os_api.php?acao=obter_por_token&token='+encodeURIComponent(token));
  const json=await res.json();
  if(!json.sucesso){setMsg(json.mensagem||'Link inválido.','err');return;}
  osData=json;
  render();
}
function render(){
  const os=osData.os, c=osData.cliente;
  const aprovado=['aprovado','em_execucao','concluido','pago'].includes(os.status);
  const itens=Array.isArray(os.itens)?os.itens:[];
  const link=window.location.href;
  const tel=String(c.telefone||'').replace(/\D/g,'');
  const wa=`https://wa.me/${tel?'55'+tel:''}?text=${encodeURIComponent('Aprove minha OS: '+link)}`;
  setMsg(aprovado?'Esta OS já está aprovada.':'','ok');
  document.getElementById('conteudo').innerHTML=`
    <section class="sec"><div class="grid">
      <div class="field"><label>Cliente</label><strong>${escapeHtml(c.nome||os.cliente_nome||'-')}</strong></div>
      <div class="field"><label>Telefone</label><strong>${escapeHtml(c.telefone||os.cliente_tel||'-')}</strong></div>
      <div class="field"><label>OS</label><strong>${escapeHtml(os.codigo||os.id)}</strong></div>
      <div class="field"><label>Status</label><strong>${escapeHtml(os.status||'-')}</strong></div>
    </div></section>
    <section class="sec"><h3 style="margin-bottom:10px">Itens</h3>
      <table><thead><tr><th>Descrição</th><th>Qtd.</th><th>Unit.</th><th>Total</th></tr></thead><tbody>
      ${itens.length?itens.map(i=>`<tr><td>${escapeHtml(i.desc||'-')}</td><td>${escapeHtml(i.medida||'')}</td><td class="r">${moedaBR(i.vunit)}</td><td class="r">${moedaBR(i.total)}</td></tr>`).join(''):'<tr><td colspan="4" class="muted">Sem itens detalhados.</td></tr>'}
      </tbody></table><div class="total">${moedaBR(os.total_geral)}</div></section>
    <section class="sec">
      <form class="approve-form" onsubmit="aprovar(event)">
        <input name="nome" placeholder="Seu nome" value="${escapeHtml(c.nome||'')}" required ${aprovado?'disabled':''}>
        <input name="telefone" placeholder="Seu telefone" value="${escapeHtml(c.telefone||'')}" required ${aprovado?'disabled':''}>
        <label class="check"><input type="checkbox" id="termos" required ${aprovado?'disabled checked':''}> <span>Concordo com os valores, escopo e condições apresentados nesta OS.</span></label>
        <div class="btn-row">
          <button class="btn btn-red" type="submit" ${aprovado?'disabled':''}>APROVAR ESTA OS</button>
          <a class="btn btn-dark" target="_blank" href="${wa}">Compartilhar WhatsApp</a>
        </div>
      </form>
    </section>`;
}
async function aprovar(e){
  e.preventDefault();
  const fd=new FormData(e.target);
  if(!document.getElementById('termos').checked){setMsg('Você precisa concordar com os termos.','err');return;}
  const res=await fetch('api/os_api.php?acao=aprovar_por_token&token='+encodeURIComponent(token),{method:'POST',body:fd});
  const json=await res.json();
  if(!json.sucesso){setMsg(json.mensagem||'Erro ao aprovar OS.','err');return;}
  osData=json;
  setMsg(json.mensagem||'OS aprovada com sucesso.','ok');
  render();
}
carregar();
</script>
</body>
</html>
