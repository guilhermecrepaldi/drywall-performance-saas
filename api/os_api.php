<?php
require_once __DIR__ . '/../includes/os_functions.php';

header('Content-Type: application/json; charset=utf-8');

function os_api_responder(array $resposta, int $status = 200): void {
    http_response_code($status);
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = $_GET['acao'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if ($acao === 'obter_por_token') {
            $res = obter_os_por_token($_GET['token'] ?? '');
            os_api_responder($res, $res['sucesso'] ? 200 : 400);
        }

        auth_required_api();
        if ($acao === 'listar') {
            os_api_responder(listar_os_pipeline());
        }
        os_api_responder(['sucesso' => false, 'mensagem' => 'Ação GET inválida.'], 400);
    }

    if ($method === 'POST') {
        if ($acao === 'aprovar_por_token') {
            $res = aprovar_os_por_token($_GET['token'] ?? $_POST['token'] ?? '', $_POST['nome'] ?? '', $_POST['telefone'] ?? '');
            os_api_responder($res, $res['sucesso'] ? 200 : 400);
        }

        auth_required_api();
        if (!csrf_validate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            os_api_responder(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
        }

        if ($acao === 'atualizar_status') {
            $res = atualizar_status_os($_GET['os_id'] ?? $_POST['os_id'] ?? '', $_GET['novo_status'] ?? $_POST['novo_status'] ?? '');
            os_api_responder($res, $res['sucesso'] ? 200 : 400);
        }
        if ($acao === 'gerar_token') {
            $res = gerar_token_aprovacao_os($_GET['os_id'] ?? $_POST['os_id'] ?? '');
            os_api_responder($res, $res['sucesso'] ? 200 : 400);
        }
        os_api_responder(['sucesso' => false, 'mensagem' => 'Ação POST inválida.'], 400);
    }

    os_api_responder(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 400);
} catch (Throwable $e) {
    error_log('os_api erro: ' . $e->getMessage());
    os_api_responder(['sucesso' => false, 'mensagem' => 'Erro interno.'], 500);
}
