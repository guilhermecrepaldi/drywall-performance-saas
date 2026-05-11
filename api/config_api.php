<?php
require_once __DIR__ . '/../includes/config_functions.php';

$acao = $_GET['acao'] ?? '';

function config_api_json(array $resposta, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $acao === 'download_backup') {
        auth_required();
        $arquivo = basename($_GET['arquivo'] ?? '');
        if (!preg_match('/^backup_\d{8}_\d{6}\.sql$/', $arquivo)) {
            http_response_code(400);
            die('Arquivo inválido.');
        }
        $path = __DIR__ . '/../backups/' . $arquivo;
        if (!is_file($path)) {
            http_response_code(404);
            die('Backup não encontrado.');
        }
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    auth_required_api();

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $acao === 'obter') {
        $res = obter_configuracoes();
        $res['backups'] = listar_backups_mysql();
        config_api_json($res);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_validate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            config_api_json(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
        }
        if ($acao === 'atualizar') {
            $res = atualizar_configuracoes($_POST);
            config_api_json($res, $res['sucesso'] ? 200 : 400);
        }
        if ($acao === 'backup') {
            $res = fazer_backup_mysql();
            config_api_json($res, $res['sucesso'] ? 200 : 400);
        }
    }

    config_api_json(['sucesso' => false, 'mensagem' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    error_log('config_api erro: ' . $e->getMessage());
    config_api_json(['sucesso' => false, 'mensagem' => 'Erro interno.'], 500);
}
