<?php
// gerar_hash.php — Utilitário para trocar a senha do sistema
// APAGUE este arquivo após usar!
require_once 'includes/auth.php';
auth_required();

$hash_gerado = '';
$nova_senha = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_required();
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar  = $_POST['confirmar'] ?? '';
    if (strlen($nova_senha) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif ($nova_senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $hash_gerado = password_hash($nova_senha, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Gerar Hash de Senha</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body style="background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px">
<div style="background:#fff;border-radius:10px;padding:32px;max-width:520px;width:100%;box-shadow:0 4px 20px rgba(0,0,0,.1)">
  <h2 style="margin-bottom:8px">Gerar Hash de Senha</h2>
  <p style="color:#5a7080;font-size:13px;margin-bottom:24px">
    Crie um hash seguro e cole em <code>includes/config.php</code> no campo <code>AUTH_PASS</code>.
    <strong>Apague este arquivo após usar.</strong>
  </p>

  <?php if (!empty($erro)): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;color:#c0392b;margin-bottom:16px;font-size:13px">
      <?= htmlspecialchars($erro) ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div style="margin-bottom:14px">
      <label style="display:block;font-size:13px;font-weight:600;color:#3a5060;margin-bottom:5px">Nova senha</label>
      <input type="password" name="nova_senha" required minlength="8"
             style="width:100%;padding:9px 12px;border:1.5px solid #d0d9e2;border-radius:6px;font-size:14px">
    </div>
    <div style="margin-bottom:20px">
      <label style="display:block;font-size:13px;font-weight:600;color:#3a5060;margin-bottom:5px">Confirmar senha</label>
      <input type="password" name="confirmar" required minlength="8"
             style="width:100%;padding:9px 12px;border:1.5px solid #d0d9e2;border-radius:6px;font-size:14px">
    </div>
    <button type="submit" style="background:#0d6e5a;color:#fff;border:none;border-radius:6px;padding:10px 24px;font-size:14px;font-weight:600;cursor:pointer">
      Gerar Hash
    </button>
  </form>

  <?php if ($hash_gerado): ?>
  <div style="margin-top:24px;background:#f0faf6;border:1.5px solid #1abc9c;border-radius:6px;padding:16px">
    <p style="font-size:13px;font-weight:600;color:#0d6e5a;margin-bottom:8px">✅ Hash gerado! Copie a linha abaixo:</p>
    <code style="display:block;font-size:12px;word-break:break-all;background:#e8f8f3;padding:10px;border-radius:4px;color:#0a3d2b">
      define('AUTH_PASS', '<?= htmlspecialchars($hash_gerado) ?>');
    </code>
    <p style="font-size:12px;color:#5a7080;margin-top:10px">
      Substitua a linha <code>define('AUTH_PASS', ...)</code> em <code>includes/config.php</code> por esta.
    </p>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
