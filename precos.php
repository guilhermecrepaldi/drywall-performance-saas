<?php
require_once 'includes/helpers.php';
$page_title = 'Tabela de Preços';
$active_nav = 'precos';
include 'includes/head.php';

$precos = ler_db('precos');
$cats   = [];
foreach ($precos as $p) {
    $cats[$p['categoria']][] = $p;
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <p style="font-size:13px;color:var(--muted)">Atualize os preços abaixo. Os custos por m² são calculados automaticamente.</p>
  <button class="btn btn-red" onclick="salvarPrecos()">💾 Salvar preços</button>
</div>

<div id="msg-save"></div>

<?php foreach ($cats as $cat => $itens): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <div class="card-title"><?= htmlspecialchars($cat) ?></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Produto</th>
          <th>Dimensão / Unid.</th>
          <th style="width:100px">Área (m²)</th>
          <th style="width:120px">Preço (R$) 🔵</th>
          <th style="width:110px">Custo/m²</th>
          <th style="width:80px">Perda (%)</th>
          <th style="width:120px">Custo c/ perda</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($itens as $p): ?>
        <tr data-id="<?= $p['id'] ?>">
          <td><strong><?= htmlspecialchars($p['produto']) ?></strong></td>
          <td class="muted"><?= htmlspecialchars($p['unidade']) ?></td>
          <td style="text-align:right;color:var(--muted)">
            <?= $p['area'] !== null ? number_format($p['area'], 2, ',', '.') . ' m²' : '—' ?>
          </td>
          <td>
            <input type="number"
                   class="preco-input"
                   data-id="<?= $p['id'] ?>"
                   value="<?= $p['preco'] ?>"
                   step="0.01" min="0"
                   style="width:100%;font-size:13px;font-weight:600;color:#0000cc;background:#ebf3ff;border:1px solid #c5d5e4;border-radius:3px;padding:5px 8px;text-align:right"
                   oninput="calcLinha(this)">
          </td>
          <td>
            <?php if ($p['area'] !== null): ?>
            <input type="number"
                   class="custo-input"
                   data-id="<?= $p['id'] ?>"
                   value="<?= number_format($p['custo'] ?? ($p['preco'] / $p['area']), 2, '.', '') ?>"
                   step="0.01" min="0"
                   style="width:100%;font-size:13px;font-weight:600;color:#0000cc;background:#ebf3ff;border:1px solid #c5d5e4;border-radius:3px;padding:5px 8px;text-align:right"
                   oninput="calcLinha(this)">
            <?php else: ?>
            <span style="text-align:right;font-weight:600;color:var(--navy)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <input type="number"
                   class="perda-input"
                   data-id="<?= $p['id'] ?>"
                   value="<?= $p['perda'] ?>"
                   step="1" min="0" max="100"
                   style="width:100%;font-size:12px;color:#0000cc;background:#ebf3ff;border:1px solid #c5d5e4;border-radius:3px;padding:5px 8px;text-align:right"
                   oninput="calcLinha(this)">
          </td>
          <td class="custo-perda" style="text-align:right;font-weight:600;color:var(--red)">
            <?= $p['area'] !== null ? 'R$ ' . number_format(($p['preco'] / $p['area']) * (1 + $p['perda']/100), 2, ',', '.') : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<div style="text-align:right;margin-top:8px">
  <button class="btn btn-red" onclick="salvarPrecos()">💾 Salvar todos os preços</button>
</div>

<script>
const precosData = <?= json_encode($precos, JSON_UNESCAPED_UNICODE) ?>;
const mapaPrecos = {};
precosData.forEach(p => mapaPrecos[p.id] = p);

function calcLinha(input) {
  const row   = input.closest('tr');
  const id    = parseInt(row.dataset.id);
  const p     = mapaPrecos[id];
  if (!p) return;

  let preco, custo, perda;

  if (input.classList.contains('preco-input')) {
    preco = parseFloat(input.value) || 0;
    if (p.area !== null) {
      custo = preco / p.area;
      row.querySelector('.custo-input').value = custo.toFixed(2);
    }
  } else if (input.classList.contains('custo-input')) {
    custo = parseFloat(input.value) || 0;
    if (p.area !== null) {
      preco = custo * p.area;
      row.querySelector('.preco-input').value = preco.toFixed(2);
    } else {
      preco = parseFloat(row.querySelector('.preco-input').value) || 0;
    }
  } else {
    preco = parseFloat(row.querySelector('.preco-input').value) || 0;
    if (p.area !== null) {
      custo = preco / p.area;
      row.querySelector('.custo-input').value = custo.toFixed(2);
    }
  }

  perda = parseFloat(row.querySelector('.perda-input').value) || 0;

  if (p.area !== null) {
    const comPerda = custo * (1 + perda / 100);
    row.querySelector('.custo-perda').textContent = 'R$ ' + comPerda.toFixed(2).replace('.', ',');
  } else {
    row.querySelector('.custo-perda').textContent = '—';
  }
}

function salvarPrecos() {
  const updates = [];
  document.querySelectorAll('tr[data-id]').forEach(row => {
    const id    = parseInt(row.dataset.id);
    const preco = parseFloat(row.querySelector('.preco-input')?.value) || 0;
    const perda = parseFloat(row.querySelector('.perda-input')?.value) || 0;
    const custoInput = row.querySelector('.custo-input');
    const custo = custoInput ? parseFloat(custoInput.value) || null : null;
    updates.push({ id, preco, perda, custo });
  });

  fetch('api/precos.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>'
    },
    body: JSON.stringify(updates)
  })
  .then(r => r.json())
  .then(data => {
    const msg = document.getElementById('msg-save');
    if (data.ok) {
      msg.innerHTML = '<div class="alert alert-success">✓ Preços salvos com sucesso.</div>';
    } else {
      msg.innerHTML = '<div class="alert alert-error">✗ Erro ao salvar: ' + data.mensagem + '</div>';
    }
    setTimeout(() => msg.innerHTML = '', 3000);
  });
}
</script>

<?php include 'includes/foot.php'; ?>
