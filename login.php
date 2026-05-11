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
<title>Login | Drywall Performance</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Barlow', sans-serif;
    background: #0d1b2a;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .login-wrap {
    width: 100%;
    max-width: 380px;
  }

  .login-logo {
    text-align: center;
    margin-bottom: 32px;
  }

  .login-logo .l1 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 32px;
    font-weight: 800;
    color: #fff;
    letter-spacing: 1px;
    text-transform: uppercase;
  }

  .login-logo .l2 {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: #1abc9c;
    letter-spacing: 2px;
    text-transform: uppercase;
  }

  .login-logo .l3 {
    font-size: 12px;
    color: #5a7080;
    margin-top: 4px;
    letter-spacing: 1px;
  }

  .login-card {
    background: #fff;
    border-radius: 10px;
    padding: 36px 32px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
  }

  .login-card h2 {
    font-size: 20px;
    font-weight: 600;
    color: #0d1b2a;
    margin-bottom: 24px;
  }

  .field {
    margin-bottom: 16px;
  }

  .field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #3a5060;
    margin-bottom: 6px;
    letter-spacing: 0.4px;
  }

  .field input {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #d0d9e2;
    border-radius: 6px;
    font-family: 'Barlow', sans-serif;
    font-size: 15px;
    color: #0d1b2a;
    background: #f8fafc;
    transition: border-color 0.15s, box-shadow 0.15s;
    outline: none;
  }

  .field input:focus {
    border-color: #1abc9c;
    box-shadow: 0 0 0 3px rgba(26,188,156,0.15);
    background: #fff;
  }

  .erro-msg {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: 6px;
    color: #c0392b;
    font-size: 13px;
    padding: 10px 14px;
    margin-bottom: 16px;
  }

  .btn-login {
    width: 100%;
    padding: 12px;
    background: #0d6e5a;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-family: 'Barlow', sans-serif;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    margin-top: 8px;
  }

  .btn-login:hover { background: #0a5a4a; }
  .btn-login:active { background: #084840; }

  .login-footer {
    text-align: center;
    margin-top: 20px;
    font-size: 12px;
    color: #5a7080;
  }
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-logo">
    <div class="l1">Drywall</div>
    <div class="l2">Performance</div>
    <div class="l3">Sistema v2.0</div>
  </div>

  <div class="login-card">
    <h2>Entrar no sistema</h2>

    <?php if ($erro): ?>
    <div class="erro-msg"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= isset($_GET['volta']) ? '?volta='.urlencode($_GET['volta']) : '' ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="usuario">Usuário</label>
        <input type="text" id="usuario" name="usuario"
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
               autocomplete="username" autofocus required>
      </div>
      <div class="field">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-login">Entrar</button>
    </form>
  </div>

  <div class="login-footer">
    CNPJ 66.472.550/0001-11 &nbsp;·&nbsp; Guilherme Crepaldi
  </div>
</div>
</body>
</html>
