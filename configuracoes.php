<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/config_functions.php';

$page_title = 'Configurações';
$active_nav = 'configuracoes';
$config = obter_configuracoes()['config'] ?? [];
$backups = listar_backups_mysql();
include 'includes/head.php';
?>

<style>
.config-layout { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:18px; align-items:start; }
.config-section-title { font-family:'Barlow Condensed',sans-serif; font-size:15px; font-weight:800; color:var(--navy); text-transform:uppercase; margin:6px 0 14px; display:flex; align-items:center; gap:8px; }
.config-section-title::before { content:''; width:3px; height:16px; background:var(--red); border-radius:2px; }
.logo-preview { width:160px; min-height:80px; border:1px dashed var(--border); border-radius:8px; display:flex; align-items:center; justify-content:center; background:var(--bg); overflow:hidden; color:var(--muted); font-size:12px; }
.logo-preview img { max-width:100%; max-height:120px; object-fit:contain; }
.backup-list { display:flex; flex-direction:column; gap:8px; }
.backup-item { display:flex; justify-content:space-between; align-items:center; gap:10px; border:1px solid var(--border); border-radius:8px; padding:10px; background:#fff; }
@media(max-width:900px){.config-layout{grid-template-columns:1fr}.form-grid.cols-2{grid-template-columns:1fr}.form-field.span-2{grid-column:span 1}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
  <h2 style="font-family:'Barlow Condensed',sans-serif;color:var(--navy);font-size:26px">⚙️ Configurações da Empresa</h2>
</div>

<div class="config-layout">
  <div class="card">
    <div class="card-header"><div class="card-title">Dados e padrões</div></div>
    <div class="card-body">
      <form id="config-form" enctype="multipart/form-data" onsubmit="salvarConfig(event)">
        <?= csrf_field() ?>
        <div class="config-section-title">Empresa</div>
        <div class="form-grid cols-2">
          <div class="form-field span-2">
            <label>Nome *</label>
            <input type="text" name="nome" id="nome" required value="<?= htmlspecialchars($config['nome'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>CNPJ</label>
            <input type="text" name="cnpj" id="cnpj" value="<?= htmlspecialchars($config['cnpj'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>Telefone</label>
            <input type="tel" name="telefone" id="telefone" value="<?= htmlspecialchars($config['telefone'] ?? '') ?>">
          </div>
          <div class="form-field">
            <label>E-mail</label>
            <input type="email" name="email" id="email" value="<?= htmlspecialchars($config['email'] ?? '') ?>">
          </div>
          <div class="form-field span-2">
            <label>Endereço</label>
            <textarea name="endereco" id="endereco"><?= htmlspecialchars($config['endereco'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="config-section-title">Operacional</div>
        <div class="form-grid cols-2">
          <div class="form-field">
            <label>Taxa margem padrão (%)</label>
            <input type="number" name="margem_padrao" id="margem_padrao" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string)($config['margem_padrao'] ?? '25')) ?>">
          </div>
          <div class="form-field">
            <label>Logo</label>
            <input type="file" name="logo" id="logo" accept=".jpg,.jpeg,.png" onchange="previewLogo()">
            <span class="hint">JPG ou PNG até 2MB.</span>
          </div>
          <div class="form-field span-2">
            <label>Texto padrão em OS</label>
            <textarea name="texto_padrao_os" id="texto_padrao_os" rows="4"><?= htmlspecialchars($config['texto_padrao_os'] ?? '') ?></textarea>
          </div>
          <div class="form-field span-2">
            <label>Assinatura padrão PDF</label>
            <textarea name="assinatura_pdf" id="assinatura_pdf" rows="3"><?= htmlspecialchars($config['assinatura_pdf'] ?? '') ?></textarea>
          </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;margin-top:18px;flex-wrap:wrap">
          <div class="logo-preview" id="logo-preview">
            <?php if (!empty($config['logo_url'])): ?>
              <img src="<?= htmlspecialchars($config['logo_url']) ?>" alt="Logo atual">
            <?php else: ?>
              Sem logo
            <?php endif; ?>
          </div>
          <button class="btn btn-red" type="submit">Salvar Configurações</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Backup MySQL</div></div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--muted);line-height:1.5;margin-bottom:14px">Faça backup do seu banco de dados antes de mudanças importantes.</p>
      <button class="btn btn-primary" style="width:100%;justify-content:center" onclick="gerarBackup()">Download Backup MySQL</button>
      <div style="margin-top:18px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1px">Backups anteriores</div>
      <div class="backup-list" id="backup-list" style="margin-top:10px">
        <?php if (empty($backups)): ?>
          <div class="muted">Nenhum backup gerado ainda.</div>
        <?php else: foreach ($backups as $b): ?>
          <div class="backup-item">
            <div><strong><?= htmlspecialchars($b['arquivo']) ?></strong><br><span class="muted"><?= htmlspecialchars($b['data']) ?> · <?= htmlspecialchars((string)$b['tamanho_kb']) ?> KB</span></div>
            <a class="btn btn-outline btn-sm" href="api/config_api.php?acao=download_backup&arquivo=<?= urlencode($b['arquivo']) ?>">Baixar</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';

function validarLogo(file) {
  if (!file) return true;
  const okType = ['image/jpeg', 'image/png'].includes(file.type) || /\.(jpe?g|png)$/i.test(file.name);
  if (!okType) { toast('Logo deve ser JPG ou PNG.', 'error'); return false; }
  if (file.size > 2 * 1024 * 1024) { toast('Logo deve ter no máximo 2MB.', 'error'); return false; }
  return true;
}

function previewLogo() {
  const file = document.getElementById('logo').files[0];
  if (!validarLogo(file)) { document.getElementById('logo').value = ''; return; }
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => document.getElementById('logo-preview').innerHTML = `<img src="${e.target.result}" alt="Prévia da logo">`;
  reader.readAsDataURL(file);
}

async function salvarConfig(e) {
  e.preventDefault();
  const form = document.getElementById('config-form');
  const margem = parseFloat(document.getElementById('margem_padrao').value);
  const email = document.getElementById('email').value;
  const logo = document.getElementById('logo').files[0];
  if (!document.getElementById('nome').value.trim()) { toast('Nome da empresa é obrigatório.', 'error'); return; }
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('E-mail inválido.', 'error'); return; }
  if (!Number.isFinite(margem) || margem < 0 || margem > 100) { toast('Margem deve estar entre 0 e 100.', 'error'); return; }
  if (!validarLogo(logo)) return;

  const res = await fetch('api/config_api.php?acao=atualizar', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken },
    body: new FormData(form)
  });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao salvar configurações.', 'error'); return; }
  toast(json.mensagem || 'Configurações salvas.', 'success');
}

async function gerarBackup() {
  const fd = new FormData();
  fd.append('csrf_token', csrfToken);
  const res = await fetch('api/config_api.php?acao=backup', { method:'POST', headers:{'X-CSRF-Token':csrfToken}, body:fd });
  const json = await res.json();
  if (!json.sucesso) { toast(json.mensagem || 'Erro ao gerar backup.', 'error'); return; }
  toast('Backup gerado.', 'success');
  window.location.href = json.url;
}
</script>

<?php include 'includes/foot.php'; ?>
