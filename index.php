<?php
require_once 'includes/helpers.php';
$page_title = 'Painel de Controle';
$active_nav = 'dashboard';
include 'includes/head.php';

$clientes = ler_db('clientes');
$os_lista  = ler_db('os');
$agenda = ler_db('agenda');

// Estatísticas Gerais
$total_clientes = count($clientes);
$total_os       = count($os_lista);
$mes_atual      = date('m');
$ano_atual      = date('Y');

$os_mes = array_filter($os_lista, fn($o) =>
    isset($o['criado_em']) &&
    date('m', strtotime($o['criado_em'])) === $mes_atual &&
    date('Y', strtotime($o['criado_em'])) === $ano_atual
);

$total_orcado_mes = array_sum(array_column(array_values($os_mes), 'total_geral'));
$aprovadas = array_filter($os_lista, fn($o) => in_array($o['status'] ?? '', ['aprovado', 'em_execucao', 'execucao', 'concluido', 'pago']));
$total_aprovado = array_sum(array_column(array_values($aprovadas), 'total_geral'));

// Últimas 8 Ordens de Serviço
$os_recentes = array_slice(array_reverse($os_lista), 0, 8);

// Próximas Visitas (Agenda)
$hoje = date('Y-m-d');
$proximas_visitas = array_filter($agenda, fn($a) => ($a['data'] ?? '') >= $hoje);
usort($proximas_visitas, fn($a, $b) => strcmp($a['data'] ?? '', $b['data'] ?? ''));
$proximas_visitas = array_slice($proximas_visitas, 0, 5);

// Últimos Clientes/Leads
$clientes_recentes = array_slice(array_reverse($clientes), 0, 5);
?>

<style>
.dashboard-layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; margin-top: 24px; align-items: start; }
.quick-actions { display: flex; gap: 12px; margin-bottom: 24px; }
.plug-list { display: flex; flex-direction: column; gap: 12px; }
.plug-item { display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
.plug-item:last-child { border-bottom: none; padding-bottom: 0; }
@media (max-width: 900px) {
  .dashboard-layout { grid-template-columns: 1fr; }
}
</style>

<!-- AÇÕES RÁPIDAS -->
<div class="quick-actions">
    <a href="os.php?action=new" class="btn btn-primary">+ Nova OS</a>
    <a href="desenvolvimento.php" class="btn btn-outline">+ Novo Lead</a>
    <a href="agenda.php" class="btn btn-outline">+ Agendar Visita</a>
    <a href="financeiro.php" class="btn btn-ghost" style="margin-left:auto">Resumo Financeiro &rarr;</a>
</div>

<!-- GRID DE ESTATÍSTICAS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">OS no Mês</div>
    <div class="stat-value"><?= count($os_mes) ?></div>
    <div class="stat-sub">EMITIDAS EM <?= strtoupper(date('F Y')) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Volume Orçado</div>
    <div class="stat-value val"><?= moeda($total_orcado_mes) ?></div>
    <div class="stat-sub">TOTAL DE ORÇAMENTOS NO MÊS</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Receita Confirmada</div>
    <div class="stat-value" style="color:var(--success)"><?= moeda($total_aprovado) ?></div>
    <div class="stat-sub"><?= count($aprovadas) ?> OS APROVADAS/EM EXECUÇÃO</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Clientes na Base</div>
    <div class="stat-value"><?= $total_clientes ?></div>
    <div class="stat-sub"><?= $total_os ?> OS REGISTRADAS NO TOTAL</div>
  </div>
</div>

<div class="dashboard-layout">
  <!-- COLUNA ESQUERDA -->
  <div style="display:flex; flex-direction:column; gap:24px;">
    
    <!-- LISTA DE OS RECENTES -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Ordens de Serviço Recentes</div>
        <div style="display:flex;gap:8px">
            <a href="os.php" class="btn btn-ghost btn-sm">Ver Todas</a>
        </div>
      </div>
      <?php if (empty($os_recentes)): ?>
      <div class="empty-state" style="padding:40px;text-align:center">
        <div style="font-size:32px;margin-bottom:12px;opacity:0.3">◈</div>
        <h3 style="color:var(--text-muted)">Nenhuma OS encontrada</h3>
        <p style="color:var(--text-dim);font-size:12px">Inicie um novo orçamento para começar.</p>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Código</th>
              <th>Cliente</th>
              <th>Tipo</th>
              <th>Data</th>
              <th>Total</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($os_recentes as $os): ?>
            <tr>
              <td class="val" style="color:var(--accent)"><?= htmlspecialchars($os['codigo'] ?? '—') ?></td>
              <td>
                <div style="font-weight:600"><?= htmlspecialchars($os['cliente_nome'] ?? '—') ?></div>
              </td>
              <td class="muted" style="font-size:11px"><?= htmlspecialchars($os['obra_tipo'] ?? 'GERAL') ?></td>
              <td class="muted"><?= isset($os['criado_em']) ? date('d/m/Y', strtotime($os['criado_em'])) : '—' ?></td>
              <td class="val"><?= moeda($os['total_geral'] ?? 0) ?></td>
              <td><?= badge_status($os['status'] ?? 'rascunho') ?></td>
              <td style="text-align:right">
                <a href="os.php?action=edit&id=<?= urlencode($os['id'] ?? '') ?>" class="btn btn-outline btn-sm">Abrir</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- RESUMO POR STATUS -->
    <?php if (!empty($os_lista)):
      $por_status = [];
      foreach (status_os() as $key => $label) {
        $count = count(array_filter($os_lista, fn($o) => ($o['status'] ?? 'rascunho') === $key));
        if ($count > 0) $por_status[$key] = ['label' => $label, 'count' => $count];
      }
      if (!empty($por_status)):
    ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Distribuição de Status</div>
      </div>
      <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
        <?php foreach ($por_status as $key => $info): ?>
        <a href="os.php?status=<?= $key ?>" style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:16px 24px;background:var(--surface-bright);border-radius:var(--radius);text-decoration:none;min-width:120px;border:1px solid var(--border);transition:all 0.2s">
          <span style="font-family:var(--font-mono);font-size:24px;font-weight:700;color:var(--text-main)"><?= $info['count'] ?></span>
          <span style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:1px"><?= $info['label'] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; endif; ?>

  </div>

  <!-- COLUNA DIREITA (PLUGS) -->
  <div style="display:flex; flex-direction:column; gap:24px;">
    
    <!-- CARD: PRÓXIMAS VISITAS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Próximas Visitas</div>
        <a href="agenda.php" class="btn btn-ghost btn-sm">Calendário</a>
      </div>
      <div class="card-body" style="padding:16px;">
        <?php if (empty($proximas_visitas)): ?>
            <div style="text-align:center; color:var(--text-dim); font-size:12px;">Nenhuma visita futura agendada.</div>
        <?php else: ?>
            <div class="plug-list">
                <?php foreach ($proximas_visitas as $visita): ?>
                <div class="plug-item">
                    <div>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($visita['cliente_nome'] ?? 'Cliente') ?></div>
                        <div style="color:var(--text-muted); font-size:11px;"><?= htmlspecialchars($visita['titulo'] ?? 'Visita') ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-weight:700; color:var(--accent); font-size:12px;"><?= date('d/m', strtotime($visita['data'])) ?></div>
                        <div style="color:var(--text-dim); font-size:11px;"><?= htmlspecialchars($visita['hora'] ?? '') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CARD: NOVOS CLIENTES / LEADS -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Novos Leads</div>
        <a href="clientes.php" class="btn btn-ghost btn-sm">Base CRM</a>
      </div>
      <div class="card-body" style="padding:16px;">
        <?php if (empty($clientes_recentes)): ?>
            <div style="text-align:center; color:var(--text-dim); font-size:12px;">Nenhum cliente cadastrado.</div>
        <?php else: ?>
            <div class="plug-list">
                <?php foreach ($clientes_recentes as $cli): ?>
                <div class="plug-item">
                    <div>
                        <div style="font-weight:600; font-size:13px;"><?= htmlspecialchars($cli['nome'] ?? 'Sem Nome') ?></div>
                        <div style="color:var(--text-muted); font-size:11px;"><?= htmlspecialchars($cli['telefone'] ?? '--') ?></div>
                    </div>
                    <div style="text-align:right;">
                        <a href="clientes.php?action=edit&id=<?= $cli['id'] ?>" class="btn btn-outline" style="padding:4px 8px; font-size:10px;">Perfil</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
    </div>

  </div> <!-- /COLUNA DIREITA -->

</div> <!-- /DASHBOARD LAYOUT -->

<?php include 'includes/foot.php'; ?>
