<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/helpers.php';

$page_title = 'Produtos e Fornecedores';
$active_nav = 'produtos';
$msg_ok = '';
$msg_err = '';
$sugerir_tabela = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_required();
    try {
        if (isset($_POST['salvar_produto'])) {
            $dados = [
                'id' => $_POST['id'] ?? '',
                'categoria' => trim($_POST['categoria'] ?? ''),
                'nome' => trim($_POST['nome'] ?? ''),
                'unidade' => trim($_POST['unidade'] ?? ''),
                'custo_padrao' => (float)str_replace(',', '.', $_POST['custo_padrao'] ?? 0),
                'preco_venda' => (float)str_replace(',', '.', $_POST['preco_venda'] ?? 0),
                'unidade_servico' => trim($_POST['unidade_servico'] ?? ''),
                'consumo_por_unidade' => (float)str_replace(',', '.', $_POST['consumo_por_unidade'] ?? 0),
                'consumo_perda_percentual' => (float)str_replace(',', '.', $_POST['consumo_perda_percentual'] ?? 0),
                'consumo_referencia' => trim($_POST['consumo_referencia'] ?? ''),
                'protocolo_operacao' => trim($_POST['protocolo_operacao'] ?? ''),
                'descricao' => trim($_POST['descricao'] ?? ''),
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
            ];
            if ($dados['id'] === '') unset($dados['id']);
            salvar_db('produtos', $dados, 'id');
            $msg_ok = 'Produto salvo.';
        }

        if (isset($_POST['salvar_fornecedor'])) {
            $checklist = [];
            foreach (criterios_fornecedor() as $key => $label) {
                $checklist[$key] = !empty($_POST['checklist'][$key]);
            }
            $dados = [
                'id' => $_POST['id'] ?? '',
                'nome' => trim($_POST['nome'] ?? ''),
                'contato' => trim($_POST['contato'] ?? ''),
                'telefone' => trim($_POST['telefone'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'endereco' => trim($_POST['endereco'] ?? ''),
                'obs' => trim($_POST['obs'] ?? ''),
                'checklist' => json_encode($checklist, JSON_UNESCAPED_UNICODE),
                'nota_geral' => (float)($_POST['nota_geral'] ?? 0),
                'ativo' => isset($_POST['ativo']) ? 1 : 0,
            ];
            if ($dados['id'] === '') unset($dados['id']);
            
            // Salva e captura o ID para sugerir tabela
            global $pdo;
            salvar_db('fornecedores', $dados, 'id');
            $fornecedor_id = $dados['id'] ?? $pdo->lastInsertId();
            
            header("Location: produtos.php?ok=f_saved&sugerir_tabela=" . (int)$fornecedor_id);
            exit;
        }

        if (isset($_POST['salvar_preco_fornecedor'])) {
            $dados = [
                'id' => $_POST['id'] ?? '',
                'produto_id' => (int)($_POST['produto_id'] ?? 0),
                'fornecedor_id' => (int)($_POST['fornecedor_id'] ?? 0),
                'preco_pago' => (float)str_replace(',', '.', $_POST['preco_pago'] ?? 0),
                'unidade_compra' => trim($_POST['unidade_compra'] ?? ''),
                'observacao' => trim($_POST['observacao'] ?? ''),
            ];
            if ($dados['id'] === '') unset($dados['id']);
            salvar_db('produto_fornecedor_precos', $dados, 'id');
            $msg_ok = 'Preço por fornecedor salvo.';
        }
    } catch (Throwable $e) {
        error_log('produtos erro: ' . $e->getMessage());
        $msg_err = 'Erro ao salvar. Confira os campos obrigatórios.';
    }
}

if (isset($_GET['ok'])) {
    if ($_GET['ok'] === 'f_saved') $msg_ok = 'Fornecedor salvo.';
}

if (isset($_GET['sugerir_tabela'])) {
    $sugerir_tabela = (int)$_GET['sugerir_tabela'];
}

$produtos = ler_db('produtos');
$fornecedores = ler_db('fornecedores');
$precos = ler_db('produto_fornecedor_precos');
$produto_por_id = array_column($produtos, null, 'id');
$fornecedor_por_id = array_column($fornecedores, null, 'id');

include 'includes/head.php';
?>

<style>
.prod-shell { display:grid; grid-template-columns: 1.2fr 1fr; gap:24px; align-items:start; }
.prod-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.prod-tab-btn { border:1px solid var(--border); border-radius:8px; background:#fff; padding:8px 12px; font-size:12px; font-weight:800; color:var(--muted); cursor:pointer; }
.prod-tab-btn.active { border-color:var(--red); color:var(--navy); background:#fff7f7; }
.prod-tab { display:none; }
.prod-tab.active { display:block; }
.mini-list { display:flex; flex-direction:column; gap:8px; }
.mini-item { border:1px solid var(--border); border-radius:12px; padding:12px; background:var(--glass-bg); backdrop-filter:blur(10px); transition:all 0.3s ease; cursor:pointer; }
.mini-item:hover { transform:translateX(5px); box-shadow:var(--glass-shadow); border-color:var(--red); }
.metric-inline { display:flex; gap:6px; flex-wrap:wrap; font-size:11px; color:var(--muted); margin-top:4px; }
.metric-inline strong { color:var(--navy); }
.check-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
.check-grid label { display:flex; align-items:center; gap:6px; font-size:11px; border:1px solid var(--border); border-radius:6px; padding:6px 8px; background:rgba(255,255,255,0.5); cursor:pointer; }

/* Modal Suggestion Style */
.modal-suggestion {
    position: fixed; top: 20%; left: 50%; transform: translate(-50%, -20%);
    z-index: 1000; width: 400px; background: #fff; border-radius: 18px;
    box-shadow: 0 30px 90px rgba(0,0,0,0.3); padding: 24px; border: 2px solid var(--red);
    text-align: center; display: none;
}
.modal-suggestion.open { display: block; animation: floatIn 0.5s ease; }
@keyframes floatIn { from { opacity:0; transform:translate(-50%, -10%); } to { opacity:1; transform:translate(-50%, -20%); } }

@media(max-width: 1100px){.prod-shell{grid-template-columns:1fr}}
</style>

<?php if ($msg_ok): ?><div class="alert alert-success">✓ <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
<?php if ($msg_err): ?><div class="alert alert-error">✗ <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

<!-- MODAL DE SUGESTÃO -->
<?php if ($sugerir_tabela): 
    $f_nome = $fornecedor_por_id[$sugerir_tabela]['nome'] ?? 'Fornecedor';
?>
<div class="modal-suggestion open" id="modal-sugestao">
    <div style="font-size: 40px; margin-bottom: 15px;">📊</div>
    <h3 style="margin-bottom: 10px; color: var(--navy);">Fornecedor Cadastrado!</h3>
    <p style="font-size: 14px; color: var(--muted); margin-bottom: 20px;">
        Deseja cadastrar a <strong>tabela de preços exclusiva</strong> deste fornecedor (<?= htmlspecialchars($f_nome) ?>) agora?
    </p>
    <div style="display: flex; gap: 10px; justify-content: center;">
        <button class="btn btn-outline" onclick="fecharSugestao()">Depois</button>
        <button class="btn btn-red" onclick="focarTabelaPreco(<?= $sugerir_tabela ?>)">Sim, cadastrar agora</button>
    </div>
</div>
<div class="modal-bg open" id="modal-sugestao-bg" onclick="fecharSugestao()"></div>
<?php endif; ?>

<div class="prod-shell">
  <!-- COLUNA ESQUERDA: PRODUTOS -->
  <div>
    <div class="card">
      <div class="card-header"><div class="card-title">📦 Catálogo de Produtos</div></div>
      <div class="card-body">
        <div class="prod-tabs">
          <button type="button" class="prod-tab-btn active" data-tab="item" onclick="abrirProdutoTab('item')">Cadastro Básico</button>
          <button type="button" class="prod-tab-btn" data-tab="custo" onclick="abrirProdutoTab('custo')">Financeiro</button>
          <button type="button" class="prod-tab-btn" data-tab="consumo" onclick="abrirProdutoTab('consumo')">Engenharia/Consumo</button>
        </div>
        <form method="POST" class="form-grid cols-4" style="margin-bottom:24px">
          <?= csrf_field() ?>
          <input type="hidden" name="salvar_produto" value="1">
          <input type="hidden" name="ativo" value="1">
          <div class="prod-tab active span-4" id="prod-tab-item">
            <div class="form-grid cols-4">
              <div class="form-field">
                <label>Categoria</label>
                <input type="text" name="categoria" placeholder="Ex: Polimento / Proteção">
              </div>
              <div class="form-field span-2">
                <label>Produto *</label>
                <input type="text" name="nome" required placeholder="Nome do material">
              </div>
              <div class="form-field">
                <label>Unidade</label>
                <input type="text" name="unidade" placeholder="un, L, kit">
              </div>
              <div class="form-field span-4">
                <label>Descrição</label>
                <textarea name="descricao" rows="2"></textarea>
              </div>
            </div>
          </div>
          <div class="prod-tab span-4" id="prod-tab-custo">
            <div class="form-grid cols-2">
              <div class="form-field">
                <label>Custo Médio Padrão</label>
                <input type="number" step="0.01" min="0" name="custo_padrao" placeholder="R$ 0,00">
              </div>
              <div class="form-field">
                <label>Preço Sugerido de Venda</label>
                <input type="number" step="0.01" min="0" name="preco_venda" placeholder="R$ 0,00">
              </div>
            </div>
          </div>
          <div class="prod-tab span-4" id="prod-tab-consumo">
            <div class="form-grid cols-3">
              <div class="form-field">
                <label>Unidade de serviço</label>
                <input type="text" name="unidade_servico" placeholder="ex: veículo">
              </div>
              <div class="form-field">
                <label>Consumo/unid.</label>
                <input type="number" step="0.0001" min="0" name="consumo_por_unidade" placeholder="0.0000">
              </div>
              <div class="form-field">
                <label>Perda (%)</label>
                <input type="number" step="0.01" min="0" name="consumo_perda_percentual" placeholder="10">
              </div>
            </div>
          </div>
          <div class="form-field span-4" style="align-items:flex-end">
            <button class="btn btn-red" type="submit">+ Cadastrar no Catálogo</button>
          </div>
        </form>
        
        <div class="table-wrap">
          <table>
            <thead><tr><th>Produto</th><th>Unid.</th><th>Custo</th><th>Venda</th></tr></thead>
            <tbody>
              <?php foreach ($produtos as $p): ?>
              <tr>
                <td>
                  <div style="font-size:10px; color:var(--muted); text-transform:uppercase;"><?= htmlspecialchars($p['categoria'] ?? '') ?></div>
                  <strong><?= htmlspecialchars($p['nome'] ?? '') ?></strong>
                </td>
                <td class="muted"><?= htmlspecialchars($p['unidade'] ?? '') ?></td>
                <td class="val"><?= moeda((float)($p['custo_padrao'] ?? 0)) ?></td>
                <td class="val"><strong><?= moeda((float)($p['preco_venda'] ?? 0)) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- COLUNA DIREITA: FORNECEDORES -->
  <div>
    <div class="card" id="card-fornecedores">
      <div class="card-header"><div class="card-title">🚚 Fornecedores</div></div>
      <div class="card-body">
        <form method="POST" style="margin-bottom:20px; padding:15px; background:rgba(13,27,42,0.03); border-radius:12px;">
          <?= csrf_field() ?>
          <input type="hidden" name="salvar_fornecedor" value="1">
          <input type="hidden" name="ativo" value="1">
          <div class="form-field" style="margin-bottom:10px"><label>Nome do Fornecedor *</label><input type="text" name="nome" required></div>
          <div class="form-grid cols-2" style="margin-bottom:10px">
            <div class="form-field"><label>WhatsApp/Tel</label><input type="text" name="telefone"></div>
            <div class="form-field"><label>Rating (0-10)</label><input type="number" step="0.5" min="0" max="10" name="nota_geral" placeholder="10"></div>
          </div>
          <div class="check-grid" style="margin-bottom:12px">
            <?php foreach (criterios_fornecedor() as $key => $label): ?>
            <label><input type="checkbox" name="checklist[<?= htmlspecialchars($key) ?>]" value="1"> <?= htmlspecialchars($label) ?></label>
            <?php endforeach; ?>
          </div>
          <button class="btn btn-red" type="submit" style="width:100%; justify-content:center;">+ Salvar Fornecedor</button>
        </form>

        <div class="mini-list">
          <?php foreach ($fornecedores as $f): ?>
          <div class="mini-item" onclick="focarTabelaPreco(<?= $f['id'] ?>)">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <strong><?= htmlspecialchars($f['nome']) ?></strong>
                <span style="font-size:10px; background:var(--navy); color:#fff; padding:2px 6px; border-radius:4px;">Nota <?= htmlspecialchars((string)$f['nota_geral']) ?></span>
            </div>
            <div class="muted" style="font-size:11px; margin-top:4px;">📞 <?= htmlspecialchars($f['telefone'] ?? 'Sem tel') ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- TABELA DE PREÇOS DO FORNECEDOR -->
    <div class="card" id="card-tabela-precos">
      <div class="card-header"><div class="card-title">💰 Tabela de Preços do Fornecedor</div></div>
      <div class="card-body">
        <form method="POST" class="form-grid cols-2" style="margin-bottom:16px">
          <?= csrf_field() ?>
          <input type="hidden" name="salvar_preco_fornecedor" value="1">
          <div class="form-field span-2">
            <label>Fornecedor Selecionado</label>
            <select name="fornecedor_id" id="sel-fornecedor-preco" required>
              <option value="">Selecione um fornecedor...</option>
              <?php foreach ($fornecedores as $f): ?><option value="<?= (int)$f['id'] ?>" <?= ($sugerir_tabela == $f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($f['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label>Produto</label>
            <select name="produto_id" required>
              <option value="">Selecione...</option>
              <?php foreach ($produtos as $p): ?><option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label>Preço Pago (R$)</label>
            <input type="number" step="0.01" min="0" name="preco_pago" required placeholder="0,00">
          </div>
          <div class="form-field span-2">
            <button class="btn btn-navy" type="submit" style="width:100%; justify-content:center; background:var(--navy); color:#fff;">Vincular Preço</button>
          </div>
        </form>

        <div class="table-wrap">
          <table style="font-size:11px;">
            <thead><tr><th>Fornecedor</th><th>Produto</th><th>Preço</th></tr></thead>
            <tbody>
              <?php foreach ($precos as $row): ?>
              <tr class="preco-row fornecedor-<?= $row['fornecedor_id'] ?>">
                <td><?= htmlspecialchars($fornecedor_por_id[$row['fornecedor_id']]['nome'] ?? '-') ?></td>
                <td><strong><?= htmlspecialchars($produto_por_id[$row['produto_id']]['nome'] ?? '-') ?></strong></td>
                <td class="val"><?= moeda((float)$row['preco_pago']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function abrirProdutoTab(tab) {
  document.querySelectorAll('.prod-tab-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tab));
  document.querySelectorAll('.prod-tab').forEach(el => el.classList.remove('active'));
  document.getElementById('prod-tab-' + tab)?.classList.add('active');
}

function fecharSugestao() {
    document.getElementById('modal-sugestao')?.classList.remove('open');
    document.getElementById('modal-sugestao-bg')?.classList.remove('open');
}

function focarTabelaPreco(id) {
    const select = document.getElementById('sel-fornecedor-preco');
    if (select) {
        select.value = id;
        // Scroll suave até o card de tabela de preços
        document.getElementById('card-tabela-precos').scrollIntoView({ behavior: 'smooth' });
        // Filtra visualmente a tabela (opcional)
        filtrarPrecos(id);
    }
    fecharSugestao();
}

function filtrarPrecos(id) {
    document.querySelectorAll('.preco-row').forEach(row => {
        if (id === '' || row.classList.contains('fornecedor-' + id)) {
            row.style.display = 'table-row';
        } else {
            row.style.display = 'none';
        }
    });
}

// Se já houver um fornecedor selecionado no carregamento (ex: via sugestão)
window.onload = () => {
    const sel = document.getElementById('sel-fornecedor-preco').value;
    if (sel) filtrarPrecos(sel);
};

document.getElementById('sel-fornecedor-preco').addEventListener('change', function() {
    filtrarPrecos(this.value);
});
</script>

<?php include 'includes/foot.php'; ?>
