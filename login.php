<?php
require_once 'includes/auth.php';
auth_session_start();

// Já logado — vai para home
if (!empty($_SESSION['logado'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_required();
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['senha'] ?? '';

    if (auth_login($user, $pass)) {
        auth_set_logged();
        $volta = $_GET['volta'] ?? 'index.php';
        header('Location: ' . $volta);
        exit;
    }
    $erro = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Premium Detailing</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Outfit', sans-serif;
    background: #0b0f19;
    background-image: radial-gradient(circle at 20% 30%, #1a2235 0%, #0b0f19 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .login-wrap {
    width: 100%;
    max-width: 400px;
  }

  .login-logo {
    text-align: center;
    margin-bottom: 40px;
  }

  .login-logo .l1 {
    font-size: 38px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -1px;
    text-transform: uppercase;
  }

  .login-logo .l2 {
    font-size: 16px;
    font-weight: 600;
    color: #dc2626;
    letter-spacing: 4px;
    text-transform: uppercase;
    margin-top: -5px;
  }

  .login-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  }

  .login-card h2 {
    font-size: 22px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 30px;
    text-align: center;
  }

  .field {
    margin-bottom: 20px;
  }

  .field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: rgba(255,255,255,0.5);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .field input {
    width: 100%;
    padding: 16px 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14px;
    font-family: inherit;
    font-size: 16px;
    color: #fff;
    background: rgba(255, 255, 255, 0.05);
    transition: all 0.2s;
    outline: none;
  }

  .field input:focus {
    border-color: #dc2626;
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.15);
  }

  .btn-login {
    width: 100%;
    padding: 16px;
    background: #dc2626;
    color: #fff;
    border: none;
    border-radius: 14px;
    font-family: inherit;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .btn-login:hover { background: #ef4444; transform: translateY(-2px); }
  .btn-login:active { transform: translateY(0); }

  .login-footer {
    text-align: center;
    margin-top: 30px;
    font-size: 11px;
    color: rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 1px;
  }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-logo">
    <div class="l1">PREMIUM</div>
    <div class="l2">Detailing</div>
  </div>

  <div class="login-card">
    <h2>Acesso Restrito</h2>

    <?php if ($erro): ?>
    <div style="background: rgba(220,38,38,0.1); border: 1px solid #dc2626; color: #fff; padding: 12px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; text-align: center;">
        <?= htmlspecialchars($erro) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= isset($_GET['volta']) ? '?volta='.urlencode($_GET['volta']) : '' ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="usuario">Usuário de Operação</label>
        <input type="text" id="usuario" name="usuario"
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
               autocomplete="username" autofocus required>
      </div>
      <div class="field">
        <label for="senha">Senha de Acesso</label>
        <input type="password" id="senha" name="senha"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-login">Acessar Painel</button>
    </form>
  </div>

  <div class="login-footer">
    Gestão Automotiva de Alta Performance &nbsp;·&nbsp; v2026
  </div>
</div>
</body>
</html>
