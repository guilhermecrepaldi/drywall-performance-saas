<?php
// includes/auth.php — gerenciamento de sessão e autenticação

require_once __DIR__ . '/config.php';

function auth_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// Verifica se está logado — redireciona para login se não estiver
function auth_required(): void {
    auth_session_start();
    if (empty($_SESSION['logado'])) {
        $volta = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?volta=' . $volta);
        exit;
    }
    // Renova a sessão a cada request (sliding expiration)
    $_SESSION['ultimo_acesso'] = time();
}

// Para endpoints de API — retorna 401 JSON em vez de redirecionar
function auth_required_api(): void {
    auth_session_start();
    if (empty($_SESSION['logado'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensagem' => 'Não autenticado']);
        exit;
    }
}

function csrf_token(): string {
    auth_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(?string $token): bool {
    auth_session_start();
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_required(): void {
    if (csrf_validate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        return;
    }

    http_response_code(403);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'mensagem' => 'Token CSRF inválido']);
        exit;
    }
    die('Token CSRF inválido.');
}

function os_print_token(string $os_id): string {
    return hash_hmac('sha256', 'os_print|' . $os_id, AUTH_PASS);
}

function os_print_token_valid(string $os_id, ?string $token): bool {
    return is_string($token) && hash_equals(os_print_token($os_id), $token);
}

function auth_login(string $user, string $pass): bool {
    if (strcasecmp(trim($user), AUTH_USER) !== 0) return false;
    return $pass === AUTH_PASS;
}

// Inicia sessão autenticada
function auth_set_logged(): void {
    session_regenerate_id(true);
    $_SESSION['logado']       = true;
    $_SESSION['usuario']      = AUTH_USER;
    $_SESSION['ultimo_acesso'] = time();
}

// Destroi a sessão
function auth_logout(): void {
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Retorna o nome do usuário logado
function auth_usuario(): string {
    return $_SESSION['usuario'] ?? '';
}
