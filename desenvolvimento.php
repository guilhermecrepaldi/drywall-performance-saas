<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/helpers.php';

$page_title = 'Desenvolvimento';
$active_nav = 'desenvolvimento';
$msg_ok = '';
$msg_err = '';

function dt_local_to_mysql(?string $valor): ?string {
    $valor = trim((string)$valor);
    if ($valor === '') return null;
    return str_replace('T', ' ', $valor) . (strlen($valor) === 16 ? ':00' : '');
}

function dt_mysql_to_local(?string $valor): string {
    if (!$valor) return '';
    return str_replace(' ', 'T', substr($valor, 0, 16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_required();
    try {
        if (isset($_POST['salvar_dev'])) {
            $dados = [
                'id' => $_POST['id'] ?? '',
                'empresa' => trim($_POST['empresa'] ?? ''),
                'contato' => trim($_POST['contato'] ?? ''),
                'telefone' => trim($_POST['telefone'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'origem' => trim($_POST['origem'] ?? ''),
                'segmento' => trim($_POST['segmento'] ?? ''),
                'etapa' => trim($_POST['etapa'] ?? 'lead'),
                'status' => trim($_POST['status'] ?? 'novo'),
                'prioridade' => trim($_POST['prioridade'] ?? 'media'),
                'ultimo_contato' => dt_local_to_mysql($_POST['ultimo_contato'] ?? ''),
                'proximo_retorno' => dt_local_to_mysql($_POST['proximo_retorno'] ?? ''),
                'interesse' => trim($_POST['interesse'] ?? ''),
                'ultimo_resultado' => trim($_POST['ultimo_resultado'] ?? ''),
                'proxima_acao' => trim($_POST['proxima_acao'] ?? ''),
                'observacoes' => trim($_POST['observacoes'] ?? ''),
            ];
            if ($dados['empresa'] === '') {
                throw new RuntimeException('Empresa/nome é obrigatório.');
            }
            if ($dados['id'] === '') unset($dados['id']);
            salvar_db('desenvolvimento', $dados, 'id');
            header('Location: desenvolvimento.php?ok=1');
            exit;
        }
 
        if (isset($_POST['excluir_dev'])) {
            deletar_db('desenvolvimento', ['id' => $_POST['id'] ?? '']);
            header('Location: desenvolvimento.php?ok=del');
            exit;
        }

        if (isset($_POST['converter_cliente'])) {
            $rows = ler_db('desenvolvimento', ['id' => $_POST['id'] ?? '']);
            if (!$rows) throw new RuntimeException('Registro não encontrado.');
            $dev = $rows[0];
            $cliente = [
                'id' => proximo_id_cliente(),
                'nome' => $dev['empresa'],
                'telefone' => $dev['telefone'] ?? '',
                'email' => $dev['email'] ?? '',
                'tipo' => 'PJ',
                'origem_lead' => $dev['origem'] ?? '',
                'obs' => trim("Contato: " . ($dev['contato'] ?? '') . "\nInteresse: " . ($dev['interesse'] ?? '') . "\nOrigem: Desenvolvimento"),
            ];
            salvar_db('clientes', $cliente, 'id');
            salvar_db('desenvolvimento', [
                'id' => $dev['id'],
                'empresa' => $dev['empresa'],
                'contato' => $dev['contato'],
                'telefone' => $dev['telefone'],
                'email' => $dev['email'],
                'origem' => $dev['origem'],
                'segmento' => $dev['segmento'],
                'etapa' => 'trabalho',
                'status' => 'ganho',
                'prioridade' => $dev['prioridade'],
                'ultimo_contato' => $dev['ultimo_contato'],
                'proximo_retorno' => $dev['proximo_retorno'],
                'interesse' => $dev['interesse'],
                'ultimo_resultado' => $dev['ultimo_resultado'],
                'proxima_acao' => $dev['proxima_acao'],
                'observacoes' => $dev['observacoes'],
                'cliente_id' => $cliente['id'],
            ], 'id');
            header('Location: clientes.php?action=edit&id=' . urlencode((string)$cliente['id']));
            exit;
        }
    } catch (Throwable $e) {
        error_log('desenvolvimento erro: ' . $e->getMessage());
        $msg_err = $e->getMessage();
    }
}

if (isset($_GET['ok'])) {
    $msg_ok = $_GET['ok'] === 'del' ? 'Registro removido.' : 'Registro salvo.';
}

$editando = null;
if (isset($_GET['edit'])) {
    $rows = ler_db('desenvolvimento', ['id' => $_GET['edit']]);
    $editando = $rows[0] ?? null;
}

$q = trim($_GET['q'] ?? '');
$f_etapa = trim($_GET['etapa'] ?? '');
$f_status = trim($_GET['status'] ?? '');
$lista = ler_db('desenvolvimento');

$lista = array_filter($lista, function($row) use ($q, $f_etapa, $f_status) {
    $ok = true;
    if ($q !== '') {
        $hay = mb_strtolower(($row['empresa'] ?? '') . ' ' . ($row['contato'] ?? '') . ' ' . ($row['telefone'] ?? '') . ' ' . ($row['interesse'] ?? ''));
        $ok = str_contains($hay, mb_strtolower($q));
    }
    if ($ok && $f_etapa !== '') $ok = ($row['etapa'] ?? '') === $f_etapa;
    if ($ok && $f_status !== '') $ok = ($row['status'] ?? '') === $f_status;
    return $ok;
});

$lista = array_reverse(array_values($lista));
$resumo = [];
foreach (ler_db('desenvolvimento') as $row) {
    $etapa = $row['etapa'] ?? 'lead';
    $resumo[$etapa] = ($resumo[$etapa] ?? 0) + 1;
}

include 'includes/head.php';
?>

<style>
.dev-grid { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:18px; align-items:start; }
.dev-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:16px; }
.dev-stat { border:1px solid var(--border); border-radius:8px; background:#fff; padding:12px; }
.dev-stat .label { font-size:10px; font-weight:800; text-transform:uppercase; color:var(--muted); letter-spacing:.8px; }
.dev-stat .value { font-family:'Barlow Condensed',sans-serif; font-size:26px; font-weight:900; color:var(--navy); }
.dev-card { border:1px solid var(--border); border-radius:8px; background:#fff; padding:12px; display:flex; flex-direction:column; gap:8px; }
.dev-card-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
.dev-meta { display:flex; gap:6px; flex-wrap:wrap; font-size:11px; color:var(--muted); }
.dev-pill { border-radius:999px; padding:3px 8px; background:var(--bg); border:1px solid var(--border); }
.dev-pill.hot { background:#fff1f2; border-color:#fecdd3; color:#be123c; }
.dev-actions { display:flex; gap:6px; flex-wrap:wrap; }
@media(max-width: 980px){.dev-grid{grid-template-columns:1fr}.dev-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width: 640px){.dev-summary{grid-template-columns:1fr}.dev-card-top{flex-direction:column}}
</style>

<?php if ($msg_ok): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert alert-error">✗ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<div class="dev-summary">
  <?php foreach (desenvolvimento_etapas() as $key => $label): ?>
    <?php if (($resumo[$key] ?? 0) > 0): ?>
    <div class="dev-stat">
      <div class="label"><?= htmlspecialchars($label) ?></div>
      <div class="value"><?= (int)$resumo[$key] ?></div>
    </div>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$resumo): ?>
  <div class="dev-stat"><div class="label">Prospecções</div><div class="value">0</div></div>
  <?php endif; ?>
</div>

<div class="dev-grid">
  <div>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
        <div class="search-bar"><input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar empresa, contato, telefone..."></div>
        <select name="etapa" style="font-size:12px;border:1px solid var(--border);border-radius:4px;padding:6px 10px">
          <option value="">Todas as etapas</option>
          <?php foreach (desenvolvimento_etapas() as $key => $label): ?><option value="<?= $key ?>" <?= $f_etapa === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
        </select>
        <select name="status" style="font-size:12px;border:1px solid var(--border);border-radius:4px;padding:6px 10px">
          <option value="">Todos os status</option>
          <?php foreach (desenvolvimento_status() as $key => $label): ?><option value="<?= $key ?>" <?= $f_status === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Filtrar</button>
      </form>
      <a href="desenvolvimento.php" class="btn btn-red btn-sm">+ Novo</a>
    </div>

    <div class="mini-list">
      <?php foreach ($lista as $row): ?>
      <?php
        $retornoTs = !empty($row['proximo_retorno']) ? strtotime($row['proximo_retorno']) : null;
        $atrasado = $retornoTs && $retornoTs < time() && !in_array($row['status'] ?? '', ['ganho', 'perdido'], true);
      ?>
      <div class="dev-card">
        <div class="dev-card-top">
          <div>
            <strong><?= htmlspecialchars($row['empresa']) ?></strong>
            <div class="dev-meta">
              <span><?= htmlspecialchars($row['contato'] ?: 'Sem contato') ?></span>
              <span><?= htmlspecialchars($row['telefone'] ?: '') ?></span>
              <span><?= htmlspecialchars($row['email'] ?: '') ?></span>
            </div>
          </div>
          <div class="dev-meta">
            <span class="dev-pill"><?= htmlspecialchars(desenvolvimento_etapas()[$row['etapa'] ?? 'lead'] ?? ($row['etapa'] ?? '')) ?></span>
            <span class="dev-pill <?= $atrasado ? 'hot' : '' ?>"><?= htmlspecialchars(desenvolvimento_status()[$row['status'] ?? 'novo'] ?? ($row['status'] ?? '')) ?></span>
          </div>
        </div>
        <?php if (!empty($row['interesse'])): ?><div><?= nl2br(htmlspecialchars($row['interesse'])) ?></div><?php endif; ?>
        <div class="dev-meta">
          <span>Próxima ação: <?= htmlspecialchars($row['proxima_acao'] ?: '-') ?></span>
          <span>Retorno: <?= $row['proximo_retorno'] ? date('d/m/Y H:i', strtotime($row['proximo_retorno'])) : '-' ?></span>
          <?php if ($atrasado): ?><span class="dev-pill hot">Retorno atrasado</span><?php endif; ?>
        </div>
        <div class="dev-actions">
          <a class="btn btn-outline btn-sm" href="desenvolvimento.php?edit=<?= (int)$row['id'] ?>">Editar</a>
          <a class="btn btn-ghost btn-sm" href="agenda.php">Abrir agenda</a>
          <?php if (empty($row['cliente_id'])): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Converter esta prospecção em cliente?')">
            <?= csrf_field() ?>
            <input type="hidden" name="converter_cliente" value="1">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-red btn-sm" type="submit">Virar cliente</button>
          </form>
          <?php else: ?>
          <a class="btn btn-red btn-sm" href="clientes.php?action=edit&id=<?= (int)$row['cliente_id'] ?>">Cliente</a>
          <?php endif; ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remover registro?')">
            <?= csrf_field() ?>
            <input type="hidden" name="excluir_dev" value="1">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <button class="btn btn-ghost btn-sm" style="color:var(--red)" type="submit">Excluir</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$lista): ?><div class="empty-state"><h3>Nenhuma prospecção encontrada</h3><p>Cadastre uma ligação, retorno, visita ou oportunidade.</p></div><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title"><?= $editando ? 'Editar prospecção' : 'Nova prospecção' ?></div></div>
    <div class="card-body">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="salvar_dev" value="1">
        <input type="hidden" name="id" value="<?= htmlspecialchars($editando['id'] ?? '') ?>">
        <div class="form-field" style="margin-bottom:10px"><label>Empresa / nome *</label><input type="text" name="empresa" required value="<?= htmlspecialchars($editando['empresa'] ?? '') ?>"></div>
        <div class="form-grid cols-2" style="margin-bottom:10px">
          <div class="form-field"><label>Contato</label><input type="text" name="contato" value="<?= htmlspecialchars($editando['contato'] ?? '') ?>"></div>
          <div class="form-field"><label>Telefone</label><input type="text" name="telefone" value="<?= htmlspecialchars($editando['telefone'] ?? '') ?>"></div>
        </div>
        <div class="form-field" style="margin-bottom:10px"><label>E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($editando['email'] ?? '') ?>"></div>
        <div class="form-grid cols-2" style="margin-bottom:10px">
          <div class="form-field"><label>Origem</label><select name="origem"><option value="">Não informado</option><?php foreach (origens_lead() as $k => $v): ?><option value="<?= $k ?>" <?= ($editando['origem'] ?? '') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></div>
          <div class="form-field"><label>Segmento</label><input type="text" name="segmento" value="<?= htmlspecialchars($editando['segmento'] ?? '') ?>" placeholder="síndico, arquiteto, empresa..."></div>
        </div>
        <div class="form-grid cols-2" style="margin-bottom:10px">
          <div class="form-field"><label>Etapa</label><select name="etapa"><?php foreach (desenvolvimento_etapas() as $k => $v): ?><option value="<?= $k ?>" <?= ($editando['etapa'] ?? 'lead') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></div>
          <div class="form-field"><label>Status</label><select name="status"><?php foreach (desenvolvimento_status() as $k => $v): ?><option value="<?= $k ?>" <?= ($editando['status'] ?? 'novo') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-grid cols-2" style="margin-bottom:10px">
          <div class="form-field"><label>Prioridade</label><select name="prioridade"><?php foreach (['baixa'=>'Baixa','media'=>'Média','alta'=>'Alta'] as $k => $v): ?><option value="<?= $k ?>" <?= ($editando['prioridade'] ?? 'media') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div class="form-field"><label>Último contato</label><input type="datetime-local" name="ultimo_contato" value="<?= htmlspecialchars(dt_mysql_to_local($editando['ultimo_contato'] ?? '')) ?>"></div>
        </div>
        <div class="form-field" style="margin-bottom:10px"><label>Próximo retorno</label><input type="datetime-local" name="proximo_retorno" value="<?= htmlspecialchars(dt_mysql_to_local($editando['proximo_retorno'] ?? '')) ?>"></div>
        <div class="form-field" style="margin-bottom:10px"><label>Interesse / oportunidade</label><textarea name="interesse" rows="3"><?= htmlspecialchars($editando['interesse'] ?? '') ?></textarea></div>
        <div class="form-field" style="margin-bottom:10px"><label>Resultado do último contato</label><textarea name="ultimo_resultado" rows="2"><?= htmlspecialchars($editando['ultimo_resultado'] ?? '') ?></textarea></div>
        <div class="form-field" style="margin-bottom:10px"><label>Próxima ação</label><input type="text" name="proxima_acao" value="<?= htmlspecialchars($editando['proxima_acao'] ?? '') ?>" placeholder="ligar, enviar WhatsApp, marcar visita..."></div>
        <div class="form-field" style="margin-bottom:12px"><label>Observações</label><textarea name="observacoes" rows="3"><?= htmlspecialchars($editando['observacoes'] ?? '') ?></textarea></div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <?php if ($editando): ?><a href="desenvolvimento.php" class="btn btn-outline">Cancelar</a><?php endif; ?>
          <button class="btn btn-red" type="submit">Salvar</button>
        </div>
      </form>
      <div class="alert alert-info" style="margin-top:14px">Quando você mandar o Excel da mala direta, a importação entra aqui usando essas mesmas colunas.</div>
    </div>
  </div>
</div>

<?php include 'includes/foot.php'; ?>
