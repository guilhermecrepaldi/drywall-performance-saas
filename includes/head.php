<?php
// includes/head.php — Antigravity v2026 Drywall Interface
require_once __DIR__ . '/auth.php';
// auth_required(); 

$nav_groups = [
    'Principal' => [
        ['key' => 'dashboard', 'label' => 'Painel Geral', 'url' => 'index.php', 'icon' => '⬢'],
    ],
    'Atendimento' => [
        ['key' => 'agenda', 'label' => 'Agenda / Check-in', 'url' => 'agenda.php', 'icon' => '📅'],
        ['key' => 'clientes', 'label' => 'Clientes / Veículos', 'url' => 'clientes.php', 'icon' => '👤'],
    ],
    'Serviços' => [
        ['key' => 'os', 'label' => 'Ordens de Serviço', 'url' => 'os.php', 'icon' => '🚗'],
        ['key' => 'pipeline', 'label' => 'Status da Garagem', 'url' => 'os_pipeline.php', 'icon' => '⚯'],
        ['key' => 'fotos', 'label' => 'Banco de Fotos', 'url' => 'fotos_geral.php', 'icon' => '📸'],
    ],
    'Gestão' => [
        ['key' => 'financeiro', 'label' => 'Financeiro', 'url' => 'financeiro.php', 'icon' => '⌥'],
        ['key' => 'produtos', 'label' => 'Insumos / Preços', 'url' => 'produtos.php', 'icon' => '📚'],
        ['key' => 'configuracoes', 'label' => 'Configurações', 'url' => 'configuracoes.php', 'icon' => '⚙'],
    ]
];

if (empty($breadcrumbs)) {
    $breadcrumbs = [['label' => 'INÍCIO', 'url' => 'index.php']];
    if (($active_nav ?? 'dashboard') !== 'dashboard') {
        $breadcrumbs[] = ['label' => $page_title ?? 'Página', 'url' => ''];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($page_title ?? 'Sistema') ?> | Auto Detail Manager</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
    <script src="assets/js/scripts.js?v=<?= time() ?>" defer></script>
</head>
<body>

<div class="app-wrapper">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="app-logo-mark">A</div>
            <div class="app-logo-text">AUTO <span style="color:var(--text-dim);font-weight:400;font-size:10px">DETAIL MANAGER</span></div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($nav_groups as $group => $items): ?>
                <div class="nav-group-title"><?= $group ?></div>
                <div class="nav-group">
                    <?php foreach ($items as $item): ?>
                        <a href="<?= htmlspecialchars($item['url']) ?>" 
                           class="nav-item <?= ($active_nav ?? '') === $item['key'] ? 'active' : '' ?>">
                            <span class="nav-icon"><?= $item['icon'] ?></span>
                            <span><?= htmlspecialchars($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="logout.php" class="nav-item" style="margin-top:auto; color:var(--text-dim)">
                <span class="nav-icon">⏻</span>
                <span>Sair do Sistema</span>
            </a>
        </div>
    </aside>

    <div class="app-layout">
        <header class="topbar">
            <div class="topbar-left">
                <nav class="breadcrumbs">
                    <?php foreach ($breadcrumbs as $idx => $bc): ?>
                        <?php if (!empty($bc['url'])): ?>
                            <a href="<?= htmlspecialchars($bc['url']) ?>"><?= htmlspecialchars($bc['label']) ?></a>
                        <?php else: ?>
                            <span><?= htmlspecialchars($bc['label']) ?></span>
                        <?php endif; ?>
                        <?php if ($idx < count($breadcrumbs) - 1): ?><span class="bc-sep">/</span><?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="topbar-right">
                <div class="status-indicator" style="display:flex;align-items:center;gap:8px;font-size:10px;color:var(--success);font-weight:700;text-transform:uppercase;letter-spacing:1px">
                    <span style="width:8px;height:8px;background:var(--success);border-radius:50%;box-shadow:0 0 8px var(--success)"></span>
                    Sistema Online
                </div>
            </div>
        </header>

        <main class="main">
            <div class="page-header" style="margin-bottom:32px">
                <h1 style="font-size:24px;margin-bottom:4px"><?= htmlspecialchars($page_title ?? '') ?></h1>
                <div style="font-size:11px;color:var(--text-dim)"><?= date('d/m/Y H:i') ?></div>
            </div>
