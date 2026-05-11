<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/helpers.php';
$page_title = 'Clientes';
$active_nav = 'clientes';

$acao    = $_GET['action'] ?? 'list';
$msg_ok  = '';
$msg_err = '';

function cliente_salvar_foto(array $arquivo, string $foto_atual = ''): array {
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['sucesso' => true, 'foto_url' => $foto_atual];
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'mensagem' => 'Erro no upload da foto.'];
    }
    if (($arquivo['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['sucesso' => false, 'mensagem' => 'A foto deve ter no máximo 2MB.'];
    }
    $ext = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['sucesso' => false, 'mensagem' => 'Use foto JPG, PNG ou WEBP.'];
    }
    $dir = __DIR__ . '/uploads/clientes';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $nome = 'cliente_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    if (!move_uploaded_file($arquivo['tmp_name'], $dir . '/' . $nome)) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível salvar a foto.'];
    }
    return ['sucesso' => true, 'foto_url' => 'uploads/clientes/' . $nome];
}

// ── Salvar cliente ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cliente'])) {
    csrf_required();
    $id_edicao = $_POST['id_edicao'] ?? null;

    $foto = cliente_salvar_foto($_FILES['foto_cliente'] ?? ['error' => UPLOAD_ERR_NO_FILE], $_POST['foto_atual'] ?? '');
    if (!$foto['sucesso']) {
        $msg_err = $foto['mensagem'];
    }

    $novo = [
        'id'         => $id_edicao ? (int)$id_edicao : proximo_id_cliente(),
        'nome'       => trim($_POST['nome'] ?? ''),
        'cpf_cnpj'   => trim($_POST['cpf_cnpj'] ?? ''),
        'telefone'   => trim($_POST['telefone'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'tipo'       => $_POST['tipo'] ?? 'PF',
        'endereco'   => trim($_POST['endereco'] ?? ''),
        'bairro'     => trim($_POST['bairro'] ?? ''),
        'cidade'     => trim($_POST['cidade'] ?? ''),
        'cep'        => trim($_POST['cep'] ?? ''),
        'origem_lead'=> trim($_POST['origem_lead'] ?? ''),
        'foto_url'   => $foto['foto_url'] ?? ($_POST['foto_atual'] ?? ''),
        'obs'        => trim($_POST['obs'] ?? ''),
        'criado_em'  => $id_edicao ? ($_POST['criado_em'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
        'atualizado_em' => date('Y-m-d H:i:s'),
    ];

    if ($msg_err) {
        // Mantem mensagem do upload.
    } elseif (empty($novo['nome'])) {
        $msg_err = 'Nome é obrigatório.';
    } else {
        if (salvar_db('clientes', $novo, 'id')) {
            header('Location: clientes.php?ok=1');
            exit;
        } else {
            $msg_err = 'Erro ao salvar cliente.';
        }
    }
}

// ── Excluir cliente ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_cliente'])) {
    csrf_required();
    if (deletar_db('clientes', ['id' => $_POST['id'] ?? ''])) {
        header('Location: clientes.php?ok=del');
        exit;
    } else {
        header('Location: clientes.php?ok=0');
        exit;
    }
}

if (isset($_GET['ok'])) $msg_ok = $_GET['ok'] === 'del' ? 'Cliente removido.' : 'Cliente salvo com sucesso.';

// ── Dados para edição ──────────────────────────────────────
$editando = null;
if ($acao === 'edit' && isset($_GET['id'])) {
    $rows = ler_db('clientes', ['id' => $_GET['id']]);
    if (!empty($rows)) { $editando = $rows[0]; $acao = 'form'; }
}

// ── Lista de clientes ──────────────────────────────────────
$clientes   = ler_db('clientes');
$busca      = trim($_GET['q'] ?? '');
$os_lista   = ler_db('os');

if ($busca) {
    $clientes = array_filter($clientes, fn($c) =>
        str_contains(strtolower($c['nome'] ?? ''), strtolower($busca)) ||
        str_contains($c['cpf_cnpj'] ?? '', $busca) ||
        str_contains($c['telefone'] ?? '', $busca)
    );
}

// Conta OS por cliente
$os_por_cliente = [];
foreach ($os_lista as $os) {
    $cid = $os['cliente_id'] ?? null;
    if ($cid) $os_por_cliente[$cid] = ($os_por_cliente[$cid] ?? 0) + 1;
}

if ($acao === 'form' && $editando) {
    $page_title = 'Cliente: ' . ($editando['nome'] ?? 'Detalhe');
    $breadcrumbs = [
        ['label' => 'Home', 'url' => 'index.php'],
        ['label' => 'Clientes', 'url' => 'clientes.php'],
        ['label' => $editando['nome'] ?? 'Detalhe', 'url' => ''],
    ];
} elseif ($acao === 'form') {
    $page_title = 'Novo Cliente';
    $breadcrumbs = [
        ['label' => 'Home', 'url' => 'index.php'],
        ['label' => 'Clientes', 'url' => 'clientes.php'],
        ['label' => 'Novo', 'url' => ''],
    ];
}

include 'includes/head.php';
?>

<style>
.crm-tabs { display:flex; gap:8px; border-bottom:1px solid var(--border); margin-bottom:18px; flex-wrap:wrap; }
.crm-tab-btn { border:0; background:transparent; color:var(--text-muted); font-family:'Barlow',sans-serif; font-weight:700; font-size:12px; padding:10px 14px; cursor:pointer; border-bottom:3px solid transparent; }
.crm-tab-btn.active { color:var(--accent); border-bottom-color:var(--accent); }
.crm-tab-content { display:none; }
.crm-tab-content.active { display:block; }
.timeline-filters { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.timeline-filters label { display:flex; align-items:center; gap:6px; padding:6px 10px; background:var(--navy2); border:1px solid var(--border); border-radius:6px; font-size:12px; cursor:pointer; color:var(--text-main); }
.timeline { border-left:2px solid var(--border); margin-left:10px; padding-left:15px; display:flex; flex-direction:column; gap:10px; }
.timeline-item { position:relative; padding:10px 12px; border:1px solid var(--border); border-left:4px solid var(--border); border-radius:8px; background:var(--navy2); cursor:pointer; }
.timeline-item::before { content:''; position:absolute; left:-24px; top:16px; width:10px; height:10px; border-radius:50%; background:var(--border); border:2px solid var(--navy3); }
.timeline-item.agenda { border-left-color:#8ab4f8; }
.timeline-item.agenda::before { background:#8ab4f8; }
.timeline-item.os { border-left-color:#81c995; }
.timeline-item.os::before { background:#81c995; }
.timeline-item.followup { border-left-color:#fdd663; }
.timeline-item.followup::before { background:#fdd663; }
.timeline-main { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
.timeline-desc { font-weight:700; color:var(--text-main); }
.timeline-meta { font-size:11px; color:var(--text-muted); margin-top:4px; }
.timeline-details { display:none; margin-top:8px; padding-top:8px; border-top:1px solid var(--border); color:var(--text-muted); font-size:13px; line-height:1.5; }
.timeline-item.open .timeline-details { display:block; }
.tipo-icon { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:var(--navy3); margin-right:8px; flex-shrink:0; }
.proximas-list { display:grid; gap:10px; }
.proxima-card { border:1px solid var(--border); border-radius:8px; padding:12px; background:var(--navy2); color:var(--text-main); }
@media (max-width: 768px) {
  .crm-tabs { flex-direction:column; border-bottom:0; }
  .crm-tab-btn { width:100%; text-align:left; border:1px solid var(--border); border-radius:6px; background:#fff; }
  .crm-tab-btn.active { border-color:var(--red); }
  .crm-tab-content { padding:10px 0; }
  .timeline-main { flex-direction:column; }
}
</style>

<?php if ($msg_ok): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg_ok) ?></div>
<?php endif; ?>
<?php if ($msg_err): ?>
<div class="alert alert-error">✗ <?= htmlspecialchars($msg_err) ?></div>
<?php endif; ?>

<?php if ($acao === 'form' || $acao === 'new'): ?>
<!-- ═══ FORMULÁRIO DE CLIENTE ═══════════════════════════════ -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><?= $editando ? 'Editar Cliente' : 'Novo Cliente' ?></div>
    <a href="clientes.php" class="btn btn-outline btn-sm">← Voltar</a>
  </div>
  <div class="card-body">
    <?php if ($editando): ?>
    <div class="crm-tabs">
      <button type="button" class="crm-tab-btn active" data-tab="dados" onclick="abrirClienteTab('dados')">Dados</button>
      <button type="button" class="crm-tab-btn" data-tab="historico" onclick="abrirClienteTab('historico')">Histórico</button>
      <button type="button" class="crm-tab-btn" data-tab="proximas" onclick="abrirClienteTab('proximas')">Próximas Ações</button>
    </div>
    <?php endif; ?>
 
    <div id="dados-tab" class="crm-tab-content active">
    <form method="POST" action="clientes.php" enctype="multipart/form-data">
      <input type="hidden" name="salvar_cliente" value="1">
      <?= csrf_field() ?>
      <?php if ($editando): ?>
      <input type="hidden" name="id_edicao" value="<?= $editando['id'] ?>">
      <input type="hidden" name="criado_em" value="<?= $editando['criado_em'] ?? '' ?>">
      <input type="hidden" name="foto_atual" value="<?= htmlspecialchars($editando['foto_url'] ?? '') ?>">
      <?php endif; ?>
 
      <?php if ($editando): ?>
      <div style="background:var(--navy3);border:1px solid var(--border);border-radius:4px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:var(--text-muted)">
        ID do cliente: <strong style="color:var(--accent);font-family:'Barlow Condensed',sans-serif;font-size:15px">
          <?= sprintf('%03d', $editando['id']) ?>
        </strong>
      </div>
      <?php endif; ?>

      <div class="form-grid cols-2" style="margin-bottom:14px">
        <div class="form-field span-2">
          <label>Foto do cliente</label>
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <?php if (!empty($editando['foto_url'])): ?>
            <img src="<?= htmlspecialchars($editando['foto_url']) ?>" alt="Foto do cliente" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
            <?php endif; ?>
            <input type="file" name="foto_cliente" accept="image/jpeg,image/png,image/webp">
          </div>
          <span class="hint">Imagem interna do cadastro. Máximo 2MB.</span>
        </div>
        <div class="form-field span-2">
          <label>Nome / Razão Social *</label>
          <input type="text" name="nome" required value="<?= htmlspecialchars($editando['nome'] ?? '') ?>" placeholder="Nome completo ou razão social">
        </div>
        <div class="form-field">
          <label>Tipo</label>
          <select name="tipo">
            <option value="PF" <?= ($editando['tipo']??'PF') === 'PF' ? 'selected' : '' ?>>Pessoa Física</option>
            <option value="PJ" <?= ($editando['tipo']??'') === 'PJ' ? 'selected' : '' ?>>Pessoa Jurídica</option>
          </select>
        </div>
        <div class="form-field">
          <label>CPF / CNPJ</label>
          <input type="text" name="cpf_cnpj" value="<?= htmlspecialchars($editando['cpf_cnpj'] ?? '') ?>" placeholder="000.000.000-00">
        </div>
        <div class="form-field">
          <label>WhatsApp / Telefone</label>
          <input type="text" name="telefone" value="<?= htmlspecialchars($editando['telefone'] ?? '') ?>" placeholder="(11) 00000-0000">
        </div>
        <div class="form-field">
          <label>E-mail</label>
          <input type="email" name="email" value="<?= htmlspecialchars($editando['email'] ?? '') ?>" placeholder="email@exemplo.com">
        </div>
        <div class="form-field">
          <label>Origem do cliente</label>
          <select name="origem_lead">
            <option value="">Não informado</option>
            <?php foreach (origens_lead() as $key => $label): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= ($editando['origem_lead'] ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field span-2">
          <label>Endereço</label>
          <input type="text" name="endereco" value="<?= htmlspecialchars($editando['endereco'] ?? '') ?>" placeholder="Rua, número, complemento">
        </div>
        <div class="form-field">
          <label>Bairro</label>
          <input type="text" name="bairro" value="<?= htmlspecialchars($editando['bairro'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Cidade / UF</label>
          <input type="text" name="cidade" value="<?= htmlspecialchars($editando['cidade'] ?? '') ?>" placeholder="São Paulo / SP">
        </div>
        <div class="form-field">
          <label>CEP</label>
          <input type="text" name="cep" value="<?= htmlspecialchars($editando['cep'] ?? '') ?>" placeholder="00000-000">
        </div>
        <div class="form-field span-2">
          <label>Observações</label>
          <textarea name="obs"><?= htmlspecialchars($editando['obs'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-red">💾 Salvar cliente</button>
        <a href="clientes.php" class="btn btn-outline">Cancelar</a>
      </div>
    </form>
    </div>
 
    <?php if ($editando): ?>
    <div id="historico-tab" class="crm-tab-content">
      <div class="timeline-filters">
        <label><input type="checkbox" class="hist-filter" value="agenda" checked onchange="renderHistoricoCliente()"> Agenda</label>
        <label><input type="checkbox" class="hist-filter" value="os" checked onchange="renderHistoricoCliente()"> OS</label>
        <label><input type="checkbox" class="hist-filter" value="followup" checked onchange="renderHistoricoCliente()"> Follow-ups</label>
      </div>
      <div id="historico-list" class="timeline"></div>
    </div>
 
    <div id="proximas-tab" class="crm-tab-content">
      <div id="proximas-list" class="proximas-list"></div>
    </div>
    <?php endif; ?>
  </div>
</div>
 
<?php if ($editando): ?>
<script>
const clienteHistoricoId = <?= (int)$editando['id'] ?>;
let historicoCliente = [];
const historicoIcones = { agenda:'📅', os:'📋', followup:'⏰' };
const historicoLabels = { agenda:'Agenda', os:'OS', followup:'Follow-up' };
 
function abrirClienteTab(tab) {
  document.querySelectorAll('.crm-tab-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tab));
  document.querySelectorAll('.crm-tab-content').forEach(el => el.classList.remove('active'));
  document.getElementById(tab + '-tab').classList.add('active');
  if ((tab === 'historico' || tab === 'proximas') && historicoCliente.length === 0) {
    carregarHistoricoCliente();
  }
}
 
async function carregarHistoricoCliente() {
  const list = document.getElementById('historico-list');
  list.innerHTML = '<div class="muted">Carregando histórico...</div>';
  const res = await fetch('api/clientes_api.php?acao=historico&cliente_id=' + clienteHistoricoId);
  const json = await res.json();
  if (!json.sucesso) {
    list.innerHTML = '<div class="alert alert-error">✗ ' + escapeHtml(json.mensagem || 'Erro ao carregar histórico.') + '</div>';
    return;
  }
  historicoCliente = json.historico || [];
  renderHistoricoCliente();
  renderProximasAcoes();
}
 
function filtrosHistoricoAtivos() {
  return Array.from(document.querySelectorAll('.hist-filter:checked')).map(el => el.value);
}
 
function renderHistoricoCliente() {
  const ativos = filtrosHistoricoAtivos();
  const filtrado = historicoCliente.filter(item => ativos.includes(item.tipo));
  const list = document.getElementById('historico-list');
  if (!filtrado.length) {
    list.innerHTML = '<div class="muted">Nenhum evento encontrado para os filtros selecionados.</div>';
    return;
  }
  list.innerHTML = filtrado.map((item, idx) => `
    <div class="timeline-item ${escapeHtml(item.tipo)}" onclick="this.classList.toggle('open')">
      <div class="timeline-main">
        <div>
          <div class="timeline-desc"><span class="tipo-icon">${historicoIcones[item.tipo] || '•'}</span>${escapeHtml(item.descricao)}</div>
          <div class="timeline-meta">${formatarDataHistorico(item.data)} · ${historicoLabels[item.tipo] || item.tipo} · ${escapeHtml(item.usuario || '-')}</div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="event.stopPropagation();document.querySelectorAll('.timeline-item')[${idx}]?.classList.toggle('open')">Detalhes</button>
      </div>
      <div class="timeline-details">${escapeHtml(item.detalhes?.descricao_completa || item.descricao || 'Sem detalhes adicionais.')}</div>
    </div>
  `).join('');
}
 
function renderProximasAcoes() {
  const agora = new Date();
  const proximas = historicoCliente
    .filter(item => item.tipo === 'followup' && !Number(item.detalhes?.concluido || 0) && item.data && new Date(String(item.data).replace(' ', 'T')) >= agora)
    .sort((a, b) => new Date(String(a.data).replace(' ', 'T')) - new Date(String(b.data).replace(' ', 'T')));
  document.getElementById('proximas-list').innerHTML = proximas.length ? proximas.map(item => `
    <div class="proxima-card">
      <strong>${escapeHtml(item.descricao)}</strong>
      <div class="timeline-meta">${formatarDataHistorico(item.data)} · ${escapeHtml(item.usuario || '-')}</div>
    </div>
  `).join('') : '<div class="muted">Nenhuma próxima ação cadastrada.</div>';
}
 
function formatarDataHistorico(valor) {
  if (!valor) return '-';
  const d = new Date(String(valor).replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return escapeHtml(valor);
  return d.toLocaleString('pt-BR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}
 
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
}
</script>
<?php endif; ?>
 
<?php else: ?>
<!-- ═══ LISTA DE CLIENTES ════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px;background:var(--surface);padding:16px 20px;border-radius:var(--radius);border:1px solid var(--border)">
  <form method="GET" style="display:flex;gap:12px;flex-wrap:nowrap;align-items:center">
    <div class="search-wrap" style="position:relative">
      <input type="text" name="q" value="<?= htmlspecialchars($busca ?? '') ?>" placeholder="Pesquisar cliente, tel ou cidade..." style="min-width:300px;background:var(--bg)">
    </div>
    <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
  </form>
  <a href="clientes.php?action=new" class="btn btn-primary">+ Novo Cliente</a>
</div>
 
<div class="card">
  <div class="card-header">
    <div class="card-title">Clientes</div>
    <span style="font-size:12px;color:var(--muted)"><?= count($clientes) ?> registros</span>
  </div>
  <?php if (empty($clientes)): ?>
  <div class="empty-state">
    <div class="icon">👥</div>
    <h3>Nenhum cliente cadastrado</h3>
    <p>Cadastre seu primeiro cliente para começar a criar OS.</p>
    <a href="clientes.php?action=new" class="btn btn-red" style="margin-top:14px">+ Novo Cliente</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>NOME_CLIENTE</th>
          <th>CONTATO</th>
          <th>LOCALIDADE</th>
          <th>TIPO</th>
          <th>CADASTRO</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_reverse($clientes) as $c): ?>
        <tr>
          <td class="mono"><?= sprintf('%03d', $c['id']) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <?php if (!empty($c['foto_url'])): ?>
              <img src="<?= htmlspecialchars($c['foto_url']) ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
              <?php endif; ?>
              <div>
            <strong><?= htmlspecialchars($c['nome']) ?></strong>
            <?php if (!empty($c['email'])): ?>
            <br><span class="muted" style="font-size:11px"><?= htmlspecialchars($c['email']) ?></span>
            <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="muted"><?= htmlspecialchars(origens_lead()[$c['origem_lead'] ?? ''] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($c['telefone'] ?? '—') ?></td>
          <td class="muted"><?= htmlspecialchars($c['bairro'] ?? '—') ?></td>
          <td>
            <a href="os.php?cliente_id=<?= $c['id'] ?>" style="font-weight:600;color:var(--accent)">
              <?= $os_por_cliente[$c['id']] ?? 0 ?> OS
            </a>
          </td>
          <td class="muted"><?= isset($c['criado_em']) ? date('d/m/Y', strtotime($c['criado_em'])) : '—' ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="os.php?action=new&cliente_id=<?= $c['id'] ?>" class="btn btn-red btn-sm" title="Nova OS">+ OS</a>
              <a href="clientes.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
              <form method="POST" action="clientes.php" style="display:inline"
                    onsubmit="return confirm('Remover <?= htmlspecialchars($c['nome']) ?>?')">
                <?= csrf_field() ?>
                <input type="hidden" name="excluir_cliente" value="1">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">✕</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'includes/foot.php'; ?>
