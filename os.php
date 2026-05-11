<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/helpers.php';
$page_title = 'Orçamentos';
$active_nav = 'os';

$acao    = $_GET['action'] ?? 'list';
$msg_ok  = '';
$msg_err = '';

// ── Salvar OS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_os'])) {
    csrf_required();
    $os_lista    = ler_db('os');
    $id_edicao   = $_POST['id_edicao'] ?? null;
    $cliente_id  = (int)($_POST['cliente_id'] ?? 0);
    $segmento_cod= $_POST['segmento_cod'] ?? 'R';
    $email_backup= trim($_POST['email_backup'] ?? '');
    $status_post = trim($_POST['status'] ?? 'rascunho');

    if ($email_backup && !filter_var($email_backup, FILTER_VALIDATE_EMAIL)) {
        $msg_err = 'E-mail de backup inválido.';
    }

    // Gera código se novo
    $codigo = $id_edicao ? $_POST['codigo_os'] : gerar_codigo_os($cliente_id, $segmento_cod, (int)date('Y'));

    // Itens da tabela
    $itens = [];
    $subtotal = 0;
    $categorias_disponiveis = array_keys(tabela_servicos_cnae());
    $rowCount = max(
        count($_POST['item_categoria'] ?? []),
        count($_POST['item_tipo'] ?? []),
        count($_POST['item_desc'] ?? []),
        count($_POST['item_unid'] ?? []),
        count($_POST['item_medida'] ?? []),
        count($_POST['item_vunit'] ?? []),
        count($_POST['item_cnae'] ?? [])
    );
    for ($i = 0; $i < $rowCount; $i++) {
        $tipo      = trim($_POST['item_tipo'][$i] ?? '');
        $categoria = trim($_POST['item_categoria'][$i] ?? '');
        $desc      = trim($_POST['item_desc'][$i] ?? '');
        $medida    = (float)str_replace(',', '.', $_POST['item_medida'][$i] ?? 0);
        $vunit     = (float)str_replace(',', '.', $_POST['item_vunit'][$i]  ?? 0);
        $total     = round($medida * $vunit, 2);
        $cnae      = trim($_POST['item_cnae'][$i] ?? '');
        if (!$cnae && $desc) {
            $cnae = cnae_por_servico($desc);
        }

        $isEmpty = $categoria === '' && $tipo === '' && $desc === '' && $medida === 0 && $vunit === 0;
        if ($isEmpty) {
            continue;
        }

        $subtotal += $total;
        $itens[] = [
            'tipo'     => $tipo,
            'categoria'=> in_array($categoria, $categorias_disponiveis, true) ? $categoria : '',
            'desc'     => $desc,
            'unid'     => $_POST['item_unid'][$i]  ?? 'm²',
            'medida'   => $medida,
            'vunit'    => $vunit,
            'total'    => $total,
            'cnae'     => $cnae,
        ];
    }

    $desconto    = (float)str_replace([',','R$',' '], ['.','',' '], $_POST['desconto'] ?? 0);
    $total_geral = round(max(0, $subtotal - $desconto), 2);

    $os = [
        'id'             => $id_edicao ?: uniqid('os_'),
        'codigo'         => $codigo,
        'cliente_id'     => $cliente_id,
        'cliente_nome'   => trim($_POST['cliente_nome']  ?? ''),
        'cliente_cpf'    => trim($_POST['cliente_cpf']   ?? ''),
        'cliente_tel'    => trim($_POST['cliente_tel']   ?? ''),
        'cliente_end'    => trim($_POST['cliente_end']   ?? ''),
        'cliente_bairro' => trim($_POST['cliente_bairro']?? ''),
        'cliente_cidade' => trim($_POST['cliente_cidade']?? ''),
        'obra_tipo'      => trim($_POST['obra_tipo']     ?? ''),
        'obra_segmento'  => trim($_POST['obra_segmento'] ?? ''),
        'segmento_cod'   => $segmento_cod,
        'obra_prazo'     => trim($_POST['obra_prazo']    ?? ''),
        'obra_inicio'    => trim($_POST['obra_inicio']   ?? ''),
        'obra_pe_dir'    => trim($_POST['obra_pe_dir']   ?? ''),
        'obra_acesso'    => trim($_POST['obra_acesso']   ?? ''),
        'itens'          => $itens,
        'subtotal'       => $subtotal,
        'desconto'       => $desconto,
        'total_geral'    => $total_geral,
        'incluso'        => $_POST['incluso'] ?? [],
        'nao_incluso'    => $_POST['nao_incluso'] ?? [],
        'pagto_forma'    => trim($_POST['pagto_forma']   ?? ''),
        'pagto_entrada'  => trim($_POST['pagto_entrada'] ?? ''),
        'pagto_saldo'    => trim($_POST['pagto_saldo']   ?? ''),
        'pagto_data'     => trim($_POST['pagto_data']    ?? ''),
        'pagto_obs'      => trim($_POST['pagto_obs']     ?? ''),
        'nota_fiscal'    => status_exige_nf($status_post) ? isset($_POST['nota_fiscal']) : 0,
        'nota_fiscal_numero' => status_exige_nf($status_post) ? trim($_POST['nota_fiscal_numero'] ?? '') : '',
        'nota_fiscal_valor' => status_exige_nf($status_post) ? (float)str_replace([',','R$',' '], ['.','',''], $_POST['nota_fiscal_valor'] ?? 0) : null,
        'nota_fiscal_data' => status_exige_nf($status_post) ? (trim($_POST['nota_fiscal_data'] ?? '') ?: null) : null,
        'nota_fiscal_status' => status_exige_nf($status_post) ? trim($_POST['nota_fiscal_status'] ?? 'pendente') : 'pendente',
        'fornecedor_id'  => (int)($_POST['fornecedor_id'] ?? 0) ?: null,
        'obs_tecnicas'   => trim($_POST['obs_tecnicas']  ?? ''),
        'comentario_cliente' => trim($_POST['comentario_cliente'] ?? ''),
        'comentario_interno' => trim($_POST['comentario_interno'] ?? ''),
        'status'         => $status_post,
        'emissao'        => trim($_POST['emissao']        ?? date('Y-m-d')),
        'validade'       => trim($_POST['validade']       ?? ''),
        'criado_em'      => $id_edicao ? ($_POST['criado_em'] ?? date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'),
        'atualizado_em'  => date('Y-m-d H:i:s'),
    ];

    if (empty($os['cliente_nome'])) {
        $msg_err = 'Nome do cliente é obrigatório.';
    } else {
        // Store itens as JSON
        $to_save = $os;
        $to_save['itens'] = json_encode($os['itens'], JSON_UNESCAPED_UNICODE);
        $to_save['incluso'] = json_encode($os['incluso'], JSON_UNESCAPED_UNICODE);
        $to_save['nao_incluso'] = json_encode($os['nao_incluso'], JSON_UNESCAPED_UNICODE);

        if (!salvar_db('os', $to_save, 'id')) {
            $msg_err = 'Erro ao salvar OS.';
        } else {
            // Backup por e-mail (gera JSON a partir do DB)
            $backup_status = '';
            $anexos = [
                'os.json'       => json_encode(array_map(function($r){ if (isset($r['itens']) && is_string($r['itens'])) $r['itens']=json_decode($r['itens'],true); return $r; }, ler_db('os')), JSON_UNESCAPED_UNICODE),
                'clientes.json' => json_encode(ler_db('clientes'), JSON_UNESCAPED_UNICODE),
                'precos.json'   => json_encode(ler_db('precos'), JSON_UNESCAPED_UNICODE),
            ];
            if ($email_backup) {
                $assunto = "Backup OS {$codigo} — Drywall Performance";
                $corpo   = "OS {$codigo} salva em " . date('d/m/Y H:i') . ".\nCliente: {$os['cliente_nome']}\nTotal: " . moeda($total_geral);
                $backup_ok = enviar_backup_email($email_backup, $assunto, $corpo, $anexos);
                $backup_status = $backup_ok ? 'backup=sent' : 'backup=fail';
            }
            // Sempre enviar backup para o e-mail padrão
            enviar_backup_email(BACKUP_EMAIL, "Backup Sistema Drywall - OS {$codigo}", "Backup automático após salvar OS {$codigo}.", $anexos);
            $redirect = "os.php?ok=1&id=" . urlencode($os['id']);
            if ($backup_status) {
                $redirect .= '&' . $backup_status;
            }
            header("Location: $redirect");
            exit;
        }
    }
}

// ── Excluir OS ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_os'])) {
    csrf_required();
    if (deletar_db('os', ['id' => $_POST['id'] ?? ''])) {
        header('Location: os.php?ok=del');
        exit;
    } else {
        header('Location: os.php?ok=0');
        exit;
    }
}

// ── Alterar status ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_status'])) {
    csrf_required();
    $novo_status = $_POST['status'] ?? '';
    if (!array_key_exists($novo_status, status_os())) {
        http_response_code(400);
        die('Status inválido.');
    }
    global $pdo;
    $stmt = $pdo->prepare('UPDATE os SET status = ?, atualizado_em = ? WHERE id = ?');
    $stmt->execute([$novo_status, date('Y-m-d H:i:s'), $_POST['id'] ?? '']);
    header('Location: os.php?ok=status');
    exit;
}

if (isset($_GET['ok'])) {
    $msgs = ['1'=>'OS salva com sucesso.','del'=>'OS removida.','status'=>'Status atualizado.'];
    $msg_ok = $msgs[$_GET['ok']] ?? 'Operação realizada.';
    if (isset($_GET['backup'])) {
        if ($_GET['backup'] === 'sent') {
            $msg_ok .= ' Backup enviado por e-mail.';
        } elseif ($_GET['backup'] === 'fail') {
            $msg_ok .= ' OS salva, mas ocorreu erro ao enviar backup por e-mail.';
        }
    }
}

// ── Dados para form ──────────────────────────────────────
$editando      = null;
$cliente_pre   = null;
$clientes_list = ler_db('clientes');
$fornecedores_list = ler_db('fornecedores');

if ($acao === 'edit' && isset($_GET['id'])) {
    $rows = ler_db('os', ['id' => $_GET['id']]);
    if (!empty($rows)) {
        $editando = $rows[0];
        if (isset($editando['itens']) && is_string($editando['itens'])) {
            $editando['itens'] = json_decode($editando['itens'], true);
        }
        foreach (['incluso', 'nao_incluso'] as $jsonField) {
            if (isset($editando[$jsonField]) && is_string($editando[$jsonField])) {
                $editando[$jsonField] = json_decode($editando[$jsonField], true) ?: [];
            }
        }
        $acao = 'form';
    }
}

if (($acao === 'new' || $acao === 'form') && isset($_GET['cliente_id'])) {
    foreach ($clientes_list as $c) {
        if ($c['id'] == $_GET['cliente_id']) { $cliente_pre = $c; break; }
    }
}

// ── Lista filtrada ───────────────────────────────────────
$os_lista   = array_map(function($r){ if (isset($r['itens']) && is_string($r['itens'])) $r['itens'] = json_decode($r['itens'], true); return $r; }, ler_db('os'));
$busca      = trim($_GET['q'] ?? '');
$filtro_st  = $_GET['status'] ?? '';
$filtro_cli = $_GET['cliente_id'] ?? '';

$os_filtrada = $os_lista;
if ($busca) {
    $os_filtrada = array_filter($os_filtrada, fn($o) =>
        str_contains(strtolower($o['cliente_nome'] ?? ''), strtolower($busca)) ||
        str_contains(strtolower($o['codigo'] ?? ''), strtolower($busca))
    );
}
if ($filtro_st) {
    $os_filtrada = array_filter($os_filtrada, fn($o) => ($o['status'] ?? 'rascunho') === $filtro_st);
}
if ($filtro_cli) {
    $os_filtrada = array_filter($os_filtrada, fn($o) => $o['cliente_id'] == $filtro_cli);
}
$os_filtrada = array_reverse(array_values($os_filtrada));

include 'includes/head.php';
?>

<style>
.cnae-wrap { display:grid; grid-template-columns:minmax(0,1fr) 32px; gap:4px; align-items:start; }
.cnae-help-btn { height:30px; border:1px solid var(--border); border-radius:4px; background:var(--bg); color:var(--navy); font-weight:800; cursor:pointer; }
.cnae-info { display:none; grid-column:1 / -1; margin-top:4px; padding:7px 9px; border:1px solid var(--border); border-radius:6px; background:var(--navy2); color:var(--text); font-size:11px; line-height:1.45; }
.cnae-info.open { display:block; position:absolute; z-index:300; width:320px; box-shadow:var(--glass-shadow); padding:10px; border:1px solid var(--glass-border); background:rgba(255,255,255,0.95); backdrop-filter:blur(8px); }
</style>

<?php if ($msg_ok): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg_ok) ?>
  <?php if (isset($_GET['id'])): ?>
  · <a href="os_print.php?id=<?= urlencode($_GET['id']) ?>&token=<?= urlencode(os_print_token($_GET['id'])) ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-left:8px">🖨️ Imprimir</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($msg_err): ?>
<div class="alert alert-error">✗ <?= htmlspecialchars($msg_err) ?></div>
<?php endif; ?>

<?php if ($acao === 'form' || $acao === 'new'): ?>
<?php
// Cliente a pré-preencher
$c = $editando ?? ($cliente_pre ? [
    'cliente_nome'   => $cliente_pre['nome'],
    'cliente_cpf'    => $cliente_pre['cpf_cnpj'],
    'cliente_tel'    => $cliente_pre['telefone'],
    'cliente_end'    => $cliente_pre['endereco'],
    'cliente_bairro' => $cliente_pre['bairro'],
    'cliente_cidade' => $cliente_pre['cidade'] . ($cliente_pre['cep'] ? ' / ' . $cliente_pre['cep'] : ''),
    'cliente_id'     => $cliente_pre['id'],
] : []);
$segmentos = segmentos();
$status_list = status_os();
$val_inicio = date('Y-m-d', strtotime('+10 days'));
$cliente_travado = !empty($c['cliente_id']);
?>
<!-- ═══ FORMULÁRIO DA OS ════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <div>
    <?php if ($editando): ?>
    <span style="font-family:'Barlow Condensed',sans-serif;font-size:20px;font-weight:800;color:var(--navy)"><?= htmlspecialchars($editando['codigo']) ?></span>
    <span style="margin-left:10px"><?= badge_status($editando['status'] ?? 'rascunho') ?></span>
    <?php else: ?>
    <span style="font-size:13px;color:var(--muted)">Nova OS</span>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($editando): ?>
    <a href="os_print.php?id=<?= urlencode($editando['id']) ?>&token=<?= urlencode(os_print_token($editando['id'])) ?>" target="_blank" class="btn btn-outline btn-sm">🖨️ Imprimir</a>
    <?php endif; ?>
    <a href="os.php" class="btn btn-outline btn-sm">← Voltar</a>
  </div>
</div>

<form method="POST" action="os.php">
  <input type="hidden" name="salvar_os" value="1">
  <?= csrf_field() ?>
  <?php if ($editando): ?>
  <input type="hidden" name="id_edicao" value="<?= htmlspecialchars($editando['id']) ?>">
  <input type="hidden" name="codigo_os" value="<?= htmlspecialchars($editando['codigo']) ?>">
  <input type="hidden" name="criado_em" value="<?= htmlspecialchars($editando['criado_em'] ?? '') ?>">
  <?php endif; ?>

  <!-- CLIENTE -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div class="card-title">01. Cliente</div>
      <?php if (!$editando): ?>
      <div style="display:flex;align-items:center;gap:8px">
        <div class="search-bar" style="max-width:240px">
          <input type="text" id="busca-cli" placeholder="Buscar cliente..." oninput="buscarCliente(this.value)" autocomplete="off">
        </div>
        <div id="cli-results" style="display:none;position:absolute;z-index:200;background:#fff;border:1px solid var(--border);border-radius:4px;max-height:200px;overflow-y:auto;min-width:280px;box-shadow:0 4px 16px rgba(0,0,0,.1)"></div>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <input type="hidden" name="cliente_id" id="cliente_id" value="<?= htmlspecialchars((string)($c['cliente_id'] ?? '')) ?>">
      <div class="form-grid cols-2">
        <div class="form-field span-2">
          <label>Nome / Razão Social *</label>
          <input type="text" name="cliente_nome" id="f_nome" required value="<?= htmlspecialchars($c['cliente_nome'] ?? '') ?>" placeholder="Nome completo" <?= $cliente_travado ? 'readonly' : '' ?>>
        </div>
        <div class="form-field">
          <label>CPF / CNPJ</label>
          <input type="text" name="cliente_cpf" id="f_cpf" value="<?= htmlspecialchars($c['cliente_cpf'] ?? '') ?>" <?= $cliente_travado ? 'readonly' : '' ?>>
        </div>
        <div class="form-field">
          <label>WhatsApp</label>
          <input type="text" name="cliente_tel" id="f_tel" value="<?= htmlspecialchars($c['cliente_tel'] ?? '') ?>" <?= $cliente_travado ? 'readonly' : '' ?>>
        </div>
        <div class="form-field span-2">
          <label>Endereço</label>
          <input type="text" name="cliente_end" id="f_end" value="<?= htmlspecialchars($c['cliente_end'] ?? '') ?>" <?= $cliente_travado ? 'readonly' : '' ?>>
        </div>
        <div class="form-field">
          <label>Bairro</label>
          <input type="text" name="cliente_bairro" id="f_bairro" value="<?= htmlspecialchars($c['cliente_bairro'] ?? '') ?>" <?= $cliente_travado ? 'readonly' : '' ?>>
        </div>
        <div class="form-field">
          <label>Cidade / UF / CEP</label>
          <input type="text" name="cliente_cidade" id="f_cidade" value="<?= htmlspecialchars($c['cliente_cidade'] ?? '') ?>" <?= $cliente_travado ? 'readonly' : '' ?>>
        </div>
      </div>
      <?php if ($cliente_travado): ?>
      <div class="alert alert-info" style="margin-top:12px">Dados do cliente travados nesta OS. Para alterar cadastro, use a tela Clientes.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- OBRA -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div class="card-title">02. Dados da Obra</div>
      <?php if ($editando): ?>
      <span style="font-size:11px;color:var(--muted)">Código gerado: <strong><?= htmlspecialchars($editando['codigo']) ?></strong></span>
      <?php else: ?>
      <span style="font-size:11px;color:var(--muted)" id="preview-codigo">Código: aguardando...</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="form-grid cols-3">
        <div class="form-field">
          <label>Segmento</label>
          <select name="segmento_cod" id="seg_cod" onchange="atualizarCodigo()">
            <?php foreach ($segmentos as $cod => $nome): ?>
            <option value="<?= $cod ?>" <?= ($c['segmento_cod'] ?? 'R') === $cod ? 'selected' : '' ?>><?= $cod ?> — <?= $nome ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Tipo de obra</label>
          <select name="obra_tipo">
            <?php foreach (['Forro de drywall','Sanca / Rebaixo','Divisória','Parede','Reforma / Reparo','Finalização','Facelift','Outro'] as $t): ?>
            <option <?= ($c['obra_tipo'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Status</label>
          <select name="status" id="status-os" onchange="toggleNfFields()">
            <?php foreach ($status_list as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($c['status'] ?? 'rascunho') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field">
          <label>Emissão</label>
          <input type="date" name="emissao" value="<?= htmlspecialchars($c['emissao'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-field">
          <label>Validade</label>
          <input type="date" name="validade" value="<?= htmlspecialchars($c['validade'] ?? $val_inicio) ?>">
        </div>
        <div class="form-field">
          <label>Prazo estimado</label>
          <input type="text" name="obra_prazo" value="<?= htmlspecialchars($c['obra_prazo'] ?? '') ?>" placeholder="ex: 5 dias úteis">
        </div>
        <div class="form-field">
          <label>Início previsto</label>
          <input type="date" name="obra_inicio" value="<?= htmlspecialchars($c['obra_inicio'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Pé-direito</label>
          <input type="text" name="obra_pe_dir" value="<?= htmlspecialchars($c['obra_pe_dir'] ?? '') ?>" placeholder="ex: 2,80 m">
        </div>
        <div class="form-field">
          <label>Acesso</label>
          <select name="obra_acesso">
            <?php foreach (['Livre','Escada','Elevador','Escada + Elevador'] as $a): ?>
            <option <?= ($c['obra_acesso'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-field span-3">
          <label>Fornecedor interno da execução</label>
          <select name="fornecedor_id">
            <option value="">Sem fornecedor vinculado</option>
            <?php foreach ($fornecedores_list as $fornecedor): ?>
            <option value="<?= (int)$fornecedor['id'] ?>" <?= (int)($c['fornecedor_id'] ?? 0) === (int)$fornecedor['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($fornecedor['nome']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Dado interno para comparar preço, serviço e desempenho depois.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- SERVIÇOS -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <div class="card-title">03. Serviços</div>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table id="tb-servicos">
          <thead>
            <tr>
              <th style="width:30px">#</th>
              <th style="width:140px">Categoria</th>
              <th>Serviço</th>
              <th style="width:140px">CNAE</th>
              <th style="width:70px">Unid.</th>
              <th style="width:80px">Medida</th>
              <th style="width:90px">Valor Unit.</th>
              <th style="width:90px">Total</th>
              <th style="width:28px"></th>
            </tr>
          </thead>
          <tbody id="tbody-servicos">
            <?php
            $itens_edit = $c['itens'] ?? [];
            if (empty($itens_edit)) {
                $itens_edit = [[
                    'tipo' => '',
                    'categoria' => '',
                    'desc' => '',
                    'unid' => 'm²',
                    'medida' => '',
                    'vunit' => '',
                    'cnae' => '',
                ]];
            }
            foreach ($itens_edit as $idx => $it):
            ?>
            <tr id="row-<?= $idx ?>">
              <input type="hidden" name="item_tipo[]" value="<?= htmlspecialchars($it['tipo'] ?? '') ?>">
              <td style="text-align:center;color:var(--muted);font-size:11px"><?= $idx+1 ?></td>
              <td>
                <select name="item_categoria[]" style="font-size:12px;border:1px solid var(--border);border-radius:3px;padding:4px;width:100%">
                  <option value=""></option>
                  <?php foreach (array_keys(tabela_servicos_cnae()) as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>" <?= ($it['categoria'] ?? '') === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="text" name="item_desc[]" value="<?= htmlspecialchars($it['desc'] ?? '') ?>" placeholder="Serviço" style="width:100%;font-size:12px;border:1px solid var(--border);border-radius:3px;padding:5px 8px" oninput="calcRow(<?= $idx ?>); atualizarCNAE(<?= $idx ?>)"></td>
              <td>
                <div class="cnae-wrap">
                  <input type="text" name="item_cnae[]" id="item-cnae-<?= $idx ?>" value="<?= htmlspecialchars($it['cnae'] ?? '') ?>" readonly onclick="mostrarCnaeInfo(<?= $idx ?>)" style="width:100%;font-size:11px;border:1px solid var(--border);border-radius:3px;padding:5px 8px;background:var(--navy2);color:var(--text);cursor:pointer">
                  <button type="button" class="cnae-help-btn" onclick="mostrarCnaeInfo(<?= $idx ?>)" title="Explicar CNAE">?</button>
                  <div class="cnae-info" id="cnae-info-<?= $idx ?>"></div>
                </div>
              </td>
              <td>
                <select name="item_unid[]" style="font-size:12px;border:1px solid var(--border);border-radius:3px;padding:4px;width:100%">
                  <?php foreach (['m²','mt linear','unid.','verba','furo','andar'] as $u): ?>
                  <option <?= ($it['unid'] ?? 'm²') === $u ? 'selected' : '' ?>><?= $u ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="item_medida[]" value="<?= $it['medida'] ?? '' ?>" step="0.01" min="0" style="width:100%;font-size:12px;border:1px solid var(--border);border-radius:3px;padding:5px 8px;text-align:right" oninput="calcRow(<?= $idx ?>)"></td>
              <td><input type="number" name="item_vunit[]" value="<?= $it['vunit'] ?? '' ?>" step="0.01" min="0" style="width:100%;font-size:12px;border:1px solid var(--border);border-radius:3px;padding:5px 8px;text-align:right" oninput="calcRow(<?= $idx ?>)"></td>
              <td id="total-row-<?= $idx ?>" style="text-align:right;font-weight:600;font-size:12px;padding:0 8px;color:var(--navy)"><?= isset($it['total']) ? moeda($it['total']) : 'R$ 0,00' ?></td>
              <td><button type="button" onclick="removerLinha(<?= $idx ?>)" style="background:none;border:none;color:var(--muted);font-size:16px;cursor:pointer;padding:0 4px" title="Remover">×</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:10px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);flex-wrap:wrap;gap:10px">
        <button type="button" onclick="addLinha()" class="btn btn-outline btn-sm">+ Adicionar item</button>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
          <div style="display:flex;align-items:center;gap:10px;font-size:13px">
            <span style="color:var(--muted)">Subtotal</span>
            <span id="subtotal" style="font-weight:600;color:var(--navy)">R$ 0,00</span>
          </div>
          <div style="display:flex;align-items:center;gap:10px;font-size:13px">
            <span style="color:var(--muted)">Desconto</span>
            <input type="text" name="desconto" id="desconto" value="<?= htmlspecialchars($c['desconto'] ?? '0') ?>" style="width:90px;text-align:right;font-size:12px;border:1px solid var(--border);border-radius:3px;padding:4px 8px" oninput="calcTotais()">
          </div>
          <div style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:700;background:var(--navy);color:#fff;padding:6px 12px;border-radius:4px">
            <span>TOTAL GERAL</span>
            <span id="total-geral">R$ 0,00</span>
          </div>
        </div>
      </div>
      <!-- Incluso / Não incluso -->
      <div style="padding:10px 16px 14px;border-top:1px solid var(--border)">
        <div style="display:flex;gap:20px;flex-wrap:wrap">
          <div>
            <div style="font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;margin-bottom:6px">Incluso</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php foreach (['Material','Mão de obra','Retirada / Entulho','Sanca'] as $inc): ?>
              <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;padding:4px 10px;border:1px solid var(--border);border-radius:3px;background:var(--bg)">
                <input type="checkbox" name="incluso[]" value="<?= $inc ?>" <?= in_array($inc, $c['incluso'] ?? []) ? 'checked' : '' ?>>
                <?= $inc ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;margin-bottom:6px">Não incluso</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <?php foreach (['Lixa / Amassamento','Pintura','Espuma','Instalação elétrica','Instalação de LED'] as $ni): ?>
              <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;padding:4px 10px;border:1px solid var(--border);border-radius:3px;background:var(--bg)">
                <input type="checkbox" name="nao_incluso[]" value="<?= $ni ?>" <?= in_array($ni, $c['nao_incluso'] ?? []) ? 'checked' : '' ?>>
                <?= $ni ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PAGAMENTO -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div class="card-title">04. Pagamento</div></div>
    <div class="card-body">
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <?php foreach (['PIX / TED','Cartão','Dinheiro','Parcelado','Empreitada','Não acordado'] as $f): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;padding:6px 14px;border:1.5px solid var(--border);border-radius:20px">
          <input type="radio" name="pagto_forma" value="<?= $f ?>" <?= ($c['pagto_forma'] ?? '') === $f ? 'checked' : '' ?>>
          <?= $f ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="form-grid cols-3">
        <div class="form-field">
          <label>Entrada (R$)</label>
          <input type="text" name="pagto_entrada" value="<?= htmlspecialchars($c['pagto_entrada'] ?? '') ?>" placeholder="R$ 0,00">
        </div>
        <div class="form-field">
          <label>Saldo / Restante</label>
          <input type="text" name="pagto_saldo" value="<?= htmlspecialchars($c['pagto_saldo'] ?? '') ?>" placeholder="R$ 0,00">
        </div>
        <div class="form-field">
          <label>Data do saldo</label>
          <input type="date" name="pagto_data" value="<?= htmlspecialchars($c['pagto_data'] ?? '') ?>">
        </div>
        <div class="form-field span-3">
          <label>Observações de pagamento</label>
          <textarea name="pagto_obs"><?= htmlspecialchars($c['pagto_obs'] ?? '') ?></textarea>
        </div>
      </div>
      <div style="margin-top:10px">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="nota_fiscal" value="1" <?= !empty($c['nota_fiscal']) ? 'checked' : '' ?>>
          🧾 Nota Fiscal inclusa · CNAE 4330-4/04 · 4330-4/99 · 4744-0/99
        </label>
      </div>
      <div id="nf-fields" class="form-grid cols-4" style="margin-top:14px">
        <div class="form-field">
          <label>Número da NF</label>
          <input type="text" name="nota_fiscal_numero" value="<?= htmlspecialchars($c['nota_fiscal_numero'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Valor da NF</label>
          <input type="number" step="0.01" min="0" name="nota_fiscal_valor" value="<?= htmlspecialchars((string)($c['nota_fiscal_valor'] ?? '')) ?>">
        </div>
        <div class="form-field">
          <label>Data da NF</label>
          <input type="date" name="nota_fiscal_data" value="<?= htmlspecialchars($c['nota_fiscal_data'] ?? '') ?>">
        </div>
        <div class="form-field">
          <label>Status da NF</label>
          <select name="nota_fiscal_status">
            <?php foreach (['pendente'=>'Pendente','emitida'=>'Emitida','cancelada'=>'Cancelada','nao_aplica'=>'Não se aplica'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($c['nota_fiscal_status'] ?? 'pendente') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- OBSERVAÇÕES -->
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><div class="card-title">05. Observações</div></div>
    <div class="card-body">
      <div class="form-field">
        <label>Observações técnicas / escopo</label>
        <textarea name="obs_tecnicas" rows="3"><?= htmlspecialchars($c['obs_tecnicas'] ?? '') ?></textarea>
      </div>
      <div class="form-grid cols-2" style="margin-top:14px">
        <div class="form-field">
          <label>Comentário do cliente</label>
          <textarea name="comentario_cliente" rows="3" placeholder="O que o cliente pediu, reclamou ou aprovou"><?= htmlspecialchars($c['comentario_cliente'] ?? '') ?></textarea>
        </div>
        <div class="form-field">
          <label>Comentário interno</label>
          <textarea name="comentario_interno" rows="3" placeholder="Anotações suas, decisão comercial, fornecedor, risco"><?= htmlspecialchars($c['comentario_interno'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- SALVAR -->
  <div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div class="form-field" style="flex:1;max-width:300px">
      <label>E-mail para backup (opcional)</label>
      <input type="email" name="email_backup" placeholder="seu@email.com">
      <span class="hint">Ao salvar, envia os JSONs por e-mail como backup.</span>
    </div>
    <div style="display:flex;gap:10px">
      <a href="os.php" class="btn btn-outline">Cancelar</a>
      <button type="submit" class="btn btn-red">💾 Salvar OS</button>
    </div>
  </div>

</form>

<script>
let rowCount = <?= count($itens_edit) ?>;

function addLinha() {
  const idx = rowCount++;
  const tr = document.createElement('tr');
  tr.id = 'row-' + idx;
  tr.innerHTML = `
    <input type="hidden" name="item_tipo[]" value="">
    <td style="text-align:center;color:#5a7080;font-size:11px">${idx+1}</td>
    <td><select name="item_categoria[]" style="font-size:12px;border:1px solid #d0d9e2;border-radius:3px;padding:4px;width:100%">
      <option value=""></option>
      <?php foreach (array_keys(tabela_servicos_cnae()) as $cat): ?>
      <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select></td>
    <td><input type="text" name="item_desc[]" placeholder="Serviço" style="width:100%;font-size:12px;border:1px solid #d0d9e2;border-radius:3px;padding:5px 8px" oninput="calcRow(${idx}); atualizarCNAE(${idx})"></td>
    <td>
      <div class="cnae-wrap">
        <input type="text" name="item_cnae[]" id="item-cnae-${idx}" readonly onclick="mostrarCnaeInfo(${idx})" style="width:100%;font-size:11px;border:1px solid var(--border);border-radius:3px;padding:5px 8px;background:var(--navy2);color:var(--text);cursor:pointer">
        <button type="button" class="cnae-help-btn" onclick="mostrarCnaeInfo(${idx})" title="Explicar CNAE">?</button>
        <div class="cnae-info" id="cnae-info-${idx}"></div>
      </div>
    </td>
    <td><select name="item_unid[]" style="font-size:12px;border:1px solid #d0d9e2;border-radius:3px;padding:4px;width:100%">
      <option>m²</option><option>mt linear</option><option>unid.</option><option>verba</option><option>furo</option><option>andar</option>
    </select></td>
    <td><input type="number" name="item_medida[]" step="0.01" min="0" style="width:100%;font-size:12px;border:1px solid #d0d9e2;border-radius:3px;padding:5px 8px;text-align:right" oninput="calcRow(${idx})"></td>
    <td><input type="number" name="item_vunit[]" step="0.01" min="0" style="width:100%;font-size:12px;border:1px solid #d0d9e2;border-radius:3px;padding:5px 8px;text-align:right" oninput="calcRow(${idx})"></td>
    <td id="total-row-${idx}" style="text-align:right;font-weight:600;font-size:12px;padding:0 8px;color:#0d1b2a">R$ 0,00</td>
    <td><button type="button" onclick="removerLinha(${idx})" style="background:none;border:none;color:#ccc;font-size:16px;cursor:pointer;padding:0 4px">×</button></td>
  `;
  document.getElementById('tbody-servicos').appendChild(tr);
  calcTotais();
}

const serviceCnaeMap = <?= json_encode(array_merge(...array_values(tabela_servicos_cnae())), JSON_UNESCAPED_UNICODE) ?>;
const cnaeDescricaoMap = <?= json_encode(cnae_descricoes(), JSON_UNESCAPED_UNICODE) ?>;

function normalizarCnaeValor(valor) {
  return Array.isArray(valor) ? valor.join(' / ') : String(valor || '').replace(/,/g, ' / ');
}

function cnaeCodigos(valor) {
  return String(valor || '')
    .split(/\s*\/\s*|\s*,\s*/)
    .map(v => v.trim())
    .filter(Boolean)
    .reduce((acc, parte, idx, arr) => {
      if (/^\d{4}-\d$/.test(parte) && arr[idx + 1]?.startsWith?.('0')) {
        acc.push(parte + '/' + arr[idx + 1]);
      } else if (/^\d{4}-\d\/\d{2}$/.test(parte)) {
        acc.push(parte);
      }
      return acc;
    }, []);
}

function textoExplicacaoCnae(idx, valor) {
  const codigosExistentes = cnaeCodigos(valor);
  
  let html = '<div style="padding:5px; border-bottom:1px solid #ddd; margin-bottom:5px; font-weight:bold; color:var(--navy);">Sugestões de CNAE (Gesso/Drywall):</div>';
  
  // Lista de sugestões rápidas
  const sugestoes = [
    {cod: '4330-4/03', desc: 'Gesso e Estuque (Sancas, forros, molduras, acabamento)'},
    {cod: '4330-4/02', desc: 'Divisórias e Tetos (Instalação de Drywall, divisórias)'},
    {cod: '4330-4/99', desc: 'Outras obras de acabamento técnico'},
    {cod: '4744-0/99', desc: 'Comércio de materiais (Se houver revenda)'}
  ];

  html += sugestoes.map(s => `
    <div onclick="selecionarCnae(${idx}, '${s.cod}')" style="padding:6px; cursor:pointer; border-radius:4px; margin-bottom:2px; transition:background 0.2s;" onmouseover="this.style.background='#eef2f6'" onmouseout="this.style.background='transparent'">
      <strong style="color:var(--red);">${s.cod}</strong> - <small>${s.desc}</small>
    </div>
  `).join('');

  if (codigosExistentes.length) {
    html += '<div style="margin-top:8px; padding-top:5px; border-top:1px dashed #ccc; font-size:10px; color:#666;">CNAE atual detectado: ' + codigosExistentes.join(', ') + '</div>';
  }

  return html;
}

function selecionarCnae(idx, codigo) {
  const campo = document.getElementById('item-cnae-' + idx);
  if (campo) {
    campo.value = codigo;
    document.getElementById('cnae-info-' + idx).classList.remove('open');
  }
}

function mostrarCnaeInfo(idx) {
  const campo = document.getElementById('item-cnae-' + idx);
  const box = document.getElementById('cnae-info-' + idx);
  if (!campo || !box) return;
  box.innerHTML = textoExplicacaoCnae(idx, campo.value);
  box.classList.toggle('open');
}

function atualizarCNAE(idx) {
  const row = document.getElementById('row-' + idx);
  if (!row) return;
  const desc = (row.querySelector('[name="item_desc[]"]')?.value || '').toLowerCase();
  let cnae = '';
  if (desc) {
    for (const service in serviceCnaeMap) {
      if (desc.includes(service.toLowerCase())) {
        cnae = normalizarCnaeValor(serviceCnaeMap[service]);
        break;
      }
    }
  }
  const cnaeField = row.querySelector('[name="item_cnae[]"]');
  if (cnaeField) cnaeField.value = cnae;
}

function removerLinha(idx) {
  const row = document.getElementById('row-' + idx);
  if (row) { row.remove(); calcTotais(); }
}

function calcRow(idx) {
  const row = document.getElementById('row-' + idx);
  if (!row) return;
  const m = parseFloat(row.querySelector('[name="item_medida[]"]')?.value) || 0;
  const v = parseFloat(row.querySelector('[name="item_vunit[]"]')?.value)  || 0;
  const t = m * v;
  const cell = document.getElementById('total-row-' + idx);
  if (cell) cell.textContent = 'R$ ' + t.toFixed(2).replace('.', ',');
  calcTotais();
}

function calcTotais() {
  let sub = 0;
  document.querySelectorAll('[name="item_medida[]"]').forEach((el, i) => {
    const m = parseFloat(el.value) || 0;
    const vEl = document.querySelectorAll('[name="item_vunit[]"]')[i];
    const v = parseFloat(vEl?.value) || 0;
    sub += m * v;
  });
  const desc = parseFloat(document.getElementById('desconto')?.value?.replace(',','.')) || 0;
  const tot  = Math.max(0, sub - desc);
  document.getElementById('subtotal').textContent   = 'R$ ' + sub.toFixed(2).replace('.', ',');
  document.getElementById('total-geral').textContent = 'R$ ' + tot.toFixed(2).replace('.', ',');
}

// Busca cliente
let buscaTimer;
function buscarCliente(q) {
  clearTimeout(buscaTimer);
  if (q.length < 2) { document.getElementById('cli-results').style.display='none'; return; }
  buscaTimer = setTimeout(() => {
    fetch('api/clientes.php?acao=buscar&q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        const box = document.getElementById('cli-results');
        if (!data.length) { box.style.display='none'; return; }
        box.innerHTML = data.map(c => `
          <div onclick="selecionarCliente(${JSON.stringify(c).replace(/"/g,'&quot;')})"
               style="padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid #eee;hover:background:#f0f4f8">
            <strong>${c.nome}</strong> <span style="color:#5a7080">${c.telefone||''}</span>
          </div>`).join('');
        box.style.display = 'block';
        const rect = document.getElementById('busca-cli').getBoundingClientRect();
        box.style.top = (rect.bottom + window.scrollY + 2) + 'px';
        box.style.left = rect.left + 'px';
        document.getElementById('cli-results').style.position = 'fixed';
      });
  }, 300);
}

function selecionarCliente(c) {
  document.getElementById('cliente_id').value   = c.id;
  document.getElementById('f_nome').value        = c.nome || '';
  document.getElementById('f_cpf').value         = c.cpf_cnpj || '';
  document.getElementById('f_tel').value         = c.telefone || '';
  document.getElementById('f_end').value         = c.endereco || '';
  document.getElementById('f_bairro').value      = c.bairro || '';
  document.getElementById('f_cidade').value      = (c.cidade||'') + (c.cep ? ' / '+c.cep : '');
  document.getElementById('busca-cli').value     = c.nome;
  document.getElementById('cli-results').style.display = 'none';
  ['f_nome','f_cpf','f_tel','f_end','f_bairro','f_cidade'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.readOnly = true;
  });
  atualizarCodigo();
}

function atualizarCodigo() {
  const cliId = document.getElementById('cliente_id').value;
  const seg   = document.getElementById('seg_cod').value;
  const ano   = new Date().getFullYear().toString().slice(-2);
  if (cliId) {
    document.getElementById('preview-codigo').textContent =
      'Código: ' + String(cliId).padStart(3,'0') + seg + ano + '??';
  }
}

function toggleNfFields() {
  const status = document.getElementById('status-os')?.value || 'rascunho';
  const box = document.getElementById('nf-fields');
  if (!box) return;
  box.style.display = ['em_execucao', 'execucao', 'concluido', 'pago'].includes(status) ? 'grid' : 'none';
}

document.addEventListener('click', e => {
  if (!e.target.closest('#busca-cli') && !e.target.closest('#cli-results'))
    document.getElementById('cli-results').style.display = 'none';
});

// Calcula totais iniciais
document.querySelectorAll('[name="item_medida[]"]').forEach((el,i) => calcRow(i));
calcTotais();
toggleNfFields();
</script>

<?php else: ?>
<!-- ═══ LISTA DE OS ═════════════════════════════════════════ -->
<div style="display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:24px;background:var(--surface);padding:16px 20px;border-radius:var(--radius);border:1px solid var(--border)">
  <form method="GET" style="display:flex;gap:12px;flex-wrap:nowrap;align-items:center">
    <div class="search-wrap" style="position:relative">
      <input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Pesquisar código ou cliente..." style="min-width:300px;background:var(--bg)">
    </div>
    <select name="status" style="background:var(--bg)">
      <option value="">Status: Todos</option>
      <?php foreach (status_os() as $k => $v): ?>
      <option value="<?= $k ?>" <?= $filtro_st === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
    <?php if ($busca || $filtro_st): ?><a href="os.php" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
  </form>
  <a href="os.php?action=new" class="btn btn-primary">+ Nova OS</a>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Orçamentos</div>
    <span style="font-size:12px;color:var(--muted)"><?= count($os_filtrada) ?> registros</span>
  </div>
  <?php if (empty($os_filtrada)): ?>
  <div class="empty-state">
    <h3>Nenhuma OS encontrada</h3>
    <p>Crie sua primeira ordem de serviço.</p>
    <a href="os.php?action=new" class="btn btn-red" style="margin-top:14px">+ Nova OS</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Código</th>
          <th>Cliente</th>
          <th>Tipo</th>
          <th>Emissão</th>
          <th>Total</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($os_filtrada as $os): ?>
        <tr>
          <td class="mono"><?= htmlspecialchars($os['codigo'] ?? '—') ?></td>
          <td>
            <strong><?= htmlspecialchars($os['cliente_nome'] ?? '—') ?></strong>
            <br><span class="muted" style="font-size:11px"><?= htmlspecialchars($os['cliente_tel'] ?? '') ?></span>
          </td>
          <td class="muted"><?= htmlspecialchars($os['obra_tipo'] ?? '—') ?></td>
          <td class="muted"><?= isset($os['emissao']) ? date('d/m/Y', strtotime($os['emissao'])) : '—' ?></td>
          <td class="val"><?= moeda($os['total_geral'] ?? 0) ?></td>
          <td><?= badge_status($os['status'] ?? 'rascunho') ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:nowrap">
              <a href="os.php?action=edit&id=<?= urlencode($os['id']) ?>" class="btn btn-outline btn-sm">Editar</a>
              <a href="anexos.php?os_id=<?= urlencode($os['id']) ?>" class="btn btn-ghost btn-sm" title="Fotos/Anexos">FOTOS</a>
              <a href="os_print.php?id=<?= urlencode($os['id']) ?>&token=<?= urlencode(os_print_token($os['id'])) ?>" target="_blank" class="btn btn-ghost btn-sm" title="Imprimir">PRINT</a>
              <form method="POST" action="os.php" style="display:inline"
                    onsubmit="return confirm('Remover esta OS?')">
                <?= csrf_field() ?>
                <input type="hidden" name="excluir_os" value="1">
                <input type="hidden" name="id" value="<?= htmlspecialchars($os['id']) ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)" title="Remover">✕</button>
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
