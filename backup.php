<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/config_functions.php';

$page_title = 'Backup / Import';
$active_nav = 'backup';

$msg_ok  = '';
$msg_err = '';
$tabelas_mysql = ['usuarios', 'clientes', 'os', 'precos', 'agenda', 'financeiro', 'followups', 'configuracoes', 'anexos', 'produtos', 'fornecedores', 'produto_fornecedor_precos', 'desenvolvimento'];

function importar_dump_mysql(string $arquivo): bool {
    global $pdo;
    $sql = file_get_contents($arquivo);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Arquivo SQL vazio ou inválido.');
    }
    $pdo->exec($sql);
    return true;
}

if (isset($_GET['export']) && $_GET['export'] === 'mysql') {
    $dump = gerar_dump_mysql();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_' . date('Ymd_Hi') . '_drywallcrm.sql"');
    header('Content-Length: ' . strlen($dump));
    echo $dump;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_mysql'])) {
    csrf_required();
    if ($_FILES['arquivo_mysql']['error'] !== UPLOAD_ERR_OK) {
        $msg_err = 'Erro no upload do arquivo.';
    } elseif (!preg_match('/\.sql$/i', $_FILES['arquivo_mysql']['name'] ?? '')) {
        $msg_err = 'Envie um arquivo .sql válido.';
    } else {
        try {
            fazer_backup_mysql();
            importar_dump_mysql($_FILES['arquivo_mysql']['tmp_name']);
            $msg_ok = 'Backup MySQL importado com sucesso.';
        } catch (Throwable $e) {
            error_log('backup import erro: ' . $e->getMessage());
            $msg_err = 'Erro ao importar backup MySQL.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_backup'])) {
    csrf_required();
    $email = trim($_POST['email_backup'] ?? '');
    if ($email === '') {
        $cfg = obter_configuracoes()['config'] ?? [];
        $email = trim((string)($cfg['email'] ?? ''));
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg_err = 'E-mail inválido.';
    } else {
        $dump = gerar_dump_mysql();
        $ok = enviar_backup_email(
            $email,
            'Backup Drywall Performance - ' . date('d/m/Y H:i'),
            "Backup MySQL completo do sistema Drywall Performance.\nGerado em: " . date('d/m/Y H:i'),
            ['drywallcrm_' . date('Ymd_Hi') . '.sql' => $dump]
        );
        $msg_ok  = $ok ? "Backup enviado para {$email}" : '';
        $msg_err = $ok ? '' : 'Erro ao enviar e-mail. Verifique as configurações SMTP.';
    }
}

$infos = [];
foreach ($tabelas_mysql as $tabela) {
    try {
        $infos[$tabela] = (int)$pdo->query('SELECT COUNT(*) FROM `' . validar_tabela_backup($tabela) . '`')->fetchColumn();
    } catch (Throwable $e) {
        $infos[$tabela] = 0;
    }
}

include 'includes/head.php';
?>

<?php if ($msg_ok): ?>
<div class="alert alert-success">✓ <?= htmlspecialchars($msg_ok) ?></div>
<?php endif; ?>
<?php if ($msg_err): ?>
<div class="alert alert-error">✗ <?= htmlspecialchars($msg_err) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div class="card-title">Status do banco MySQL</div>
    <a href="backup.php?export=mysql" class="btn btn-outline btn-sm">⬇ Exportar SQL</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Tabela</th><th>Registros</th></tr>
      </thead>
      <tbody>
        <?php foreach ($infos as $tabela => $total): ?>
        <tr>
          <td class="mono"><?= htmlspecialchars($tabela) ?></td>
          <td><strong><?= (int)$total ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <div class="card-title">Backup por e-mail</div>
  </div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px">
      Envia um dump SQL completo para o e-mail informado.
    </p>
    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="enviar_backup" value="1">
      <?= csrf_field() ?>
      <div class="form-field" style="flex:1;min-width:240px">
        <label>E-mail de destino</label>
        <input type="email" name="email_backup" placeholder="Usa o e-mail cadastrado se ficar vazio">
        <span class="hint">Se deixar em branco, envia para o e-mail salvo em Configurações.</span>
      </div>
      <button type="submit" class="btn btn-red">📧 Enviar backup agora</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Importar MySQL</div>
  </div>
  <div class="card-body">
    <div class="alert alert-info">
      ⚠️ A importação executa o arquivo SQL enviado. Um backup automático será criado antes de importar.
    </div>
    <form method="POST" enctype="multipart/form-data" style="margin-top:14px">
      <?= csrf_field() ?>
      <div class="form-grid cols-2">
        <div class="form-field">
          <label>Arquivo SQL</label>
          <input type="file" name="arquivo_mysql" accept=".sql" required>
          <span class="hint">Selecione um backup .sql exportado anteriormente.</span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:14px">⬆ Importar</button>
    </form>
  </div>
</div>

<?php include 'includes/foot.php'; ?>
