<?php
// os_print.php — versão de impressão da OS (abre em nova aba)
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$id = $_GET['id'] ?? '';
if (!$id) { header('Location: os.php'); exit; }
if (!os_print_token_valid($id, $_GET['token'] ?? null)) {
    auth_required();
}

$rows = ler_db('os', ['id' => $id]);
if (empty($rows)) { die('OS não encontrada.'); }
$os = $rows[0];
if (isset($os['itens']) && is_string($os['itens'])) $os['itens'] = json_decode($os['itens'], true);
foreach (['incluso', 'nao_incluso'] as $jsonField) {
    if (isset($os[$jsonField]) && is_string($os[$jsonField])) {
        $os[$jsonField] = json_decode($os[$jsonField], true) ?: [];
    }
}

$seg_labels = segmentos();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OS <?= htmlspecialchars($os['codigo']) ?> — <?= htmlspecialchars($os['cliente_nome']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* (mesmo CSS de impressão do orcamento.html) */
:root{--navy:#0d1b2a;--navy2:#162032;--red:#c0392b;--border:#d0d9e2;--bg:#f0f4f8;--muted:#5a7080}
*{box-sizing:border-box;margin:0;padding:0}
html{font-size:13px}
body{font-family:'Barlow',sans-serif;background:#f0f4f8;color:#1a2a3a;padding:24px 16px 48px}

.toolbar{max-width:860px;margin:0 auto 16px;display:flex;justify-content:space-between;align-items:center}
.toolbar button{font-family:'Barlow',sans-serif;font-size:12px;font-weight:600;padding:8px 18px;border-radius:4px;border:none;cursor:pointer}
.btn-print{background:var(--navy);color:#fff}
.btn-back{background:transparent;border:1px solid var(--border)!important;color:var(--muted)}

.doc{max-width:860px;margin:0 auto;background:#fff;box-shadow:0 2px 20px rgba(0,0,0,.1)}

/* Header */
.doc-header{background:var(--navy);display:grid;grid-template-columns:160px 1fr 170px;min-height:72px}
.logo-area{background:#fff;display:flex;align-items:center;justify-content:center;padding:12px 20px;border-right:3px solid var(--red)}
.logo-area img{height:38px}
.logo-txt .l1{font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:20px;color:var(--navy);text-transform:uppercase;letter-spacing:1px;line-height:1}
.logo-txt .l2{font-size:9px;color:var(--red);letter-spacing:2px;text-transform:uppercase;margin-top:3px}
.hdr-center{padding:14px 20px;display:flex;flex-direction:column;justify-content:center}
.doc-type{font-size:9px;color:rgba(255,255,255,.35);letter-spacing:2px;text-transform:uppercase;margin-bottom:3px}
.doc-title{font-family:'Barlow Condensed',sans-serif;font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px}
.hdr-right{padding:14px 18px;display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:3px;border-left:1px solid rgba(255,255,255,.08)}
.os-label{font-size:9px;color:rgba(255,255,255,.3);letter-spacing:1.5px;text-transform:uppercase}
.os-num{font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:800;color:#fff}
.os-dates{font-size:10px;color:rgba(255,255,255,.55)}

.red-stripe{height:3px;background:var(--red)}
.co-bar{background:#162032;padding:6px 20px;display:flex;gap:24px;flex-wrap:wrap}
.co-bar span{font-size:10px;color:rgba(255,255,255,.4)}
.co-bar strong{color:rgba(255,255,255,.75)}

/* Sections */
.sec{padding:14px 20px;border-bottom:1px solid var(--border)}
.sec-lbl{display:flex;align-items:center;gap:7px;margin-bottom:10px}
.sec-lbl h3{font-family:'Barlow Condensed',sans-serif;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--navy)}
.sec-lbl::before{content:'';width:3px;height:12px;background:var(--red);border-radius:2px;flex-shrink:0}

.field-grid{display:grid;gap:8px}
.cols-2{grid-template-columns:1fr 1fr}
.cols-3{grid-template-columns:1fr 1fr 1fr}
.span-2{grid-column:span 2}
.field label{font-size:9px;font-weight:700;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;display:block;margin-bottom:3px}
.field .val{font-size:13px;color:#0d1b2a;border-bottom:1px solid var(--border);padding-bottom:3px;min-height:20px}

/* Tabela */
.svc-table{width:100%;border-collapse:collapse;font-size:12px}
.svc-table thead tr{background:var(--navy)}
.svc-table thead th{padding:7px 8px;font-family:'Barlow Condensed',sans-serif;font-size:9px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.7);text-align:left}
.svc-table tbody tr{border-bottom:1px solid var(--border)}
.svc-table tbody td{padding:6px 8px}
.svc-table tbody td.r{text-align:right;font-weight:600;color:var(--navy)}

.totais{display:flex;justify-content:flex-end;margin-top:10px}
.totais-inner{min-width:240px;border:1px solid var(--border);border-radius:4px;overflow:hidden}
.t-row{display:flex;justify-content:space-between;align-items:center;padding:7px 12px;border-bottom:1px solid var(--border);font-size:12px}
.t-row:last-child{border-bottom:none}
.t-row.total{background:var(--navy)}
.t-row.total .t-l{font-family:'Barlow Condensed',sans-serif;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.7)}
.t-row.total .t-v{font-family:'Barlow Condensed',sans-serif;font-size:18px;font-weight:800;color:#fff}
.t-l{color:var(--muted)} .t-v{font-weight:600;color:var(--navy)}

.badges{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.badge-item{font-size:10px;padding:3px 9px;border-radius:3px;background:var(--navy);color:#fff}
.badge-ni{background:#f0f4f8;color:var(--muted);border:1px solid var(--border)}

/* Pagamento */
.pagto-opts{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}
.pagto-pill{font-size:11px;padding:4px 12px;border-radius:20px;border:1px solid var(--border);color:var(--muted)}
.pagto-pill.sel{background:var(--navy);color:#fff;border-color:var(--navy)}

/* Footer */
.doc-footer{background:var(--navy);padding:14px 20px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:20px}
.nbr-txt{font-size:9px;color:rgba(255,255,255,.35);line-height:1.7}
.nbr-txt strong{font-size:10px;color:rgba(255,255,255,.6);display:block;margin-bottom:2px}
.assin-wrap{display:flex;gap:36px}
.assin{display:flex;flex-direction:column;align-items:center;gap:3px}
.assin-line{width:120px;border-bottom:1px solid rgba(255,255,255,.2);margin-bottom:3px}
.assin-lbl{font-size:9px;color:rgba(255,255,255,.35);text-align:center;line-height:1.4}

@media print{
  @page{size:A4;margin:8mm 10mm}
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body{background:#fff!important;padding:0!important;font-size:10px!important}
  .toolbar{display:none!important}
  .doc{box-shadow:none!important}
  .doc-header{min-height:55px!important}
  .logo-area{padding:8px 14px!important}
  .logo-area img{height:30px!important}
  .doc-title{font-size:16px!important}
  .hdr-right{min-width:140px!important}
  .os-num{font-size:15px!important}
  .sec{padding:8px 14px!important}
  .field .val{font-size:11px!important}
  .svc-table{font-size:10px!important}
  .t-row.total .t-v{font-size:14px!important}
  .doc-footer{padding:10px 14px!important}
}
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="btn-back" onclick="history.back()">← Voltar</button>
  <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>
</div>

<div class="doc">
  <!-- HEADER -->
  <div class="doc-header">
    <div class="logo-area">
      <img src="assets/logo.png" alt="Drywall Performance"
           onerror="this.style.display='none';document.getElementById('lt').style.display='block'">
      <div id="lt" class="logo-txt" style="display:none">
        <div class="l1">DRYWALL</div><div class="l2">PERFORMANCE</div>
      </div>
    </div>
    <div class="hdr-center">
      <div class="doc-type">Documento comercial</div>
      <div class="doc-title">Orçamento de Serviços</div>
    </div>
    <div class="hdr-right">
      <div class="os-label">OS Nº</div>
      <div class="os-num"><?= htmlspecialchars($os['codigo']) ?></div>
      <div class="os-dates">
        Emissão: <?= isset($os['emissao']) ? date('d/m/Y', strtotime($os['emissao'])) : '—' ?><br>
        Validade: <?= isset($os['validade']) ? date('d/m/Y', strtotime($os['validade'])) : '—' ?>
      </div>
    </div>
  </div>
  <div class="red-stripe"></div>
  <div class="co-bar">
    <span><strong>DRYWALL PERFORMANCE LTDA</strong> · CNPJ 66.472.550/0001-11</span>
    <span>Guilherme Crepaldi · (11) 91359-5985</span>
    <span>drywallperformance.com.br · @drywallperformance</span>
  </div>

  <!-- 01 CLIENTE -->
  <div class="sec">
    <div class="sec-lbl"><h3>01. Dados do Cliente</h3></div>
    <div class="field-grid cols-2">
      <div class="field span-2"><label>Nome / Razão Social</label><div class="val"><?= htmlspecialchars($os['cliente_nome'] ?? '') ?></div></div>
      <div class="field"><label>CPF / CNPJ</label><div class="val"><?= htmlspecialchars($os['cliente_cpf'] ?? '') ?></div></div>
      <div class="field"><label>WhatsApp</label><div class="val"><?= htmlspecialchars($os['cliente_tel'] ?? '') ?></div></div>
      <div class="field span-2"><label>Endereço</label><div class="val"><?= htmlspecialchars($os['cliente_end'] ?? '') ?></div></div>
      <div class="field"><label>Bairro</label><div class="val"><?= htmlspecialchars($os['cliente_bairro'] ?? '') ?></div></div>
      <div class="field"><label>Cidade / UF / CEP</label><div class="val"><?= htmlspecialchars($os['cliente_cidade'] ?? '') ?></div></div>
    </div>
  </div>

  <!-- 02 OBRA -->
  <div class="sec">
    <div class="sec-lbl"><h3>02. Dados da Obra</h3></div>
    <div class="field-grid cols-3">
      <div class="field"><label>Tipo de obra</label><div class="val"><?= htmlspecialchars($os['obra_tipo'] ?? '') ?></div></div>
      <div class="field"><label>Segmento</label><div class="val"><?= htmlspecialchars($os['obra_segmento'] ?? ($seg_labels[$os['segmento_cod']??'R'] ?? '')) ?></div></div>
      <div class="field"><label>Prazo estimado</label><div class="val"><?= htmlspecialchars($os['obra_prazo'] ?? '') ?></div></div>
      <div class="field"><label>Início previsto</label><div class="val"><?= isset($os['obra_inicio']) && $os['obra_inicio'] ? date('d/m/Y', strtotime($os['obra_inicio'])) : '' ?></div></div>
      <div class="field"><label>Pé-direito</label><div class="val"><?= htmlspecialchars($os['obra_pe_dir'] ?? '') ?></div></div>
      <div class="field"><label>Acesso</label><div class="val"><?= htmlspecialchars($os['obra_acesso'] ?? '') ?></div></div>
    </div>
  </div>

  <!-- 03 SERVIÇOS -->
  <div class="sec">
    <div class="sec-lbl"><h3>03. Planilha de Serviços</h3></div>
    <table class="svc-table">
      <thead>
        <tr>
          <th style="width:28px">#</th>
          <th style="width:120px">Item</th>
          <th>Serviço</th>
          <th style="width:120px">CNAE</th>
          <th style="width:60px">Unid.</th>
          <th style="width:70px">Medida</th>
          <th style="width:80px">Valor Unit.</th>
          <th style="width:80px">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($os['itens'] ?? []) as $i => $it): ?>
        <tr>
          <td style="text-align:center;color:var(--muted)"><?= $i+1 ?></td>
          <td><?= htmlspecialchars($it['categoria'] ?? $it['tipo'] ?? '') ?></td>
          <td><?= htmlspecialchars($it['desc'] ?? '') ?></td>
          <td><?= htmlspecialchars($it['cnae'] ?? '') ?></td>
          <td><?= htmlspecialchars($it['unid'] ?? '') ?></td>
          <td style="text-align:right"><?= number_format($it['medida'] ?? 0, 2, ',', '.') ?></td>
          <td class="r"><?= moeda($it['vunit'] ?? 0) ?></td>
          <td class="r"><?= moeda($it['total'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!empty($os['incluso'])): ?>
    <div class="badges">
      <span style="font-size:10px;color:var(--muted);align-self:center">INCLUSO:</span>
      <?php foreach ($os['incluso'] as $inc): ?>
      <span class="badge-item">✦ <?= htmlspecialchars($inc) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($os['nao_incluso'])): ?>
    <div class="badges">
      <span style="font-size:10px;color:var(--muted);align-self:center">NÃO INCLUSO:</span>
      <?php foreach ($os['nao_incluso'] as $ni): ?>
      <span class="badge-item badge-ni"><?= htmlspecialchars($ni) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="totais">
      <div class="totais-inner">
        <div class="t-row"><span class="t-l">Subtotal</span><span class="t-v"><?= moeda($os['subtotal'] ?? 0) ?></span></div>
        <div class="t-row"><span class="t-l">Desconto</span><span class="t-v"><?= moeda($os['desconto'] ?? 0) ?></span></div>
        <div class="t-row total"><span class="t-l">TOTAL GERAL</span><span class="t-v"><?= moeda($os['total_geral'] ?? 0) ?></span></div>
      </div>
    </div>
  </div>

  <!-- 04 PAGAMENTO -->
  <div class="sec">
    <div class="sec-lbl"><h3>04. Condições de Pagamento</h3></div>
    <div class="pagto-opts">
      <?php foreach (['PIX / TED','Cartão','Dinheiro','Parcelado','Empreitada','Não acordado'] as $f): ?>
      <span class="pagto-pill <?= ($os['pagto_forma'] ?? '') === $f ? 'sel' : '' ?>"><?= $f ?></span>
      <?php endforeach; ?>
    </div>
    <div class="field-grid cols-3">
      <div class="field"><label>Entrada</label><div class="val"><?= htmlspecialchars($os['pagto_entrada'] ?? '') ?></div></div>
      <div class="field"><label>Saldo</label><div class="val"><?= htmlspecialchars($os['pagto_saldo'] ?? '') ?></div></div>
      <div class="field"><label>Data do saldo</label><div class="val"><?= isset($os['pagto_data']) && $os['pagto_data'] ? date('d/m/Y', strtotime($os['pagto_data'])) : '' ?></div></div>
    </div>
    <?php if (!empty($os['pagto_obs'])): ?>
    <div class="field" style="margin-top:8px"><label>Observações de pagamento</label><div class="val"><?= nl2br(htmlspecialchars($os['pagto_obs'])) ?></div></div>
    <?php endif; ?>
    <?php if (!empty($os['nota_fiscal'])): ?>
    <div style="margin-top:10px;font-size:11px;color:var(--navy)">🧾 <strong>Nota Fiscal inclusa</strong> · CNAE 4330-4/04 · 4330-4/99 · 4744-0/99</div>
    <?php endif; ?>
  </div>

  <!-- 05 OBS -->
  <?php if (!empty($os['obs_tecnicas'])): ?>
  <div class="sec">
    <div class="sec-lbl"><h3>05. Observações Finais</h3></div>
    <div style="font-size:12px;line-height:1.65;color:#1a2a3a"><?= nl2br(htmlspecialchars($os['obs_tecnicas'])) ?></div>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div class="doc-footer">
    <div class="nbr-txt">
      <strong>PADRÃO TÉCNICO</strong>
      DRYWALL PERFORMANCE segue as normatizações NBR 15758 e NBR 14715.<br>
      Todos os produtos são normatizados e passam pelo processo de auditoria.<br>
      Instalação certificada SENAI.
    </div>
    <div class="assin-wrap">
      <div class="assin"><div class="assin-line"></div><div class="assin-lbl">Assinatura do Cliente</div></div>
      <div class="assin"><div class="assin-line"></div><div class="assin-lbl">Guilherme Crepaldi<br>Responsável Técnico</div></div>
    </div>
  </div>
</div>

<script>
// Abre diálogo de impressão automaticamente ao carregar
window.addEventListener('load', () => {
  setTimeout(() => window.print(), 600);
});
</script>
</body>
</html>
