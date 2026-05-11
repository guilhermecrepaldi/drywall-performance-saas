<?php
require_once __DIR__ . '/../includes/financeiro_functions.php';

header('Content-Type: application/json; charset=utf-8');
auth_required_api();

function financeiro_api_responder(array $resposta, int $status = 200): void {
    http_response_code($status);
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

$acao = $_GET['acao'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if ($acao === 'calcular') {
            $resposta = calcular_custo_os($_GET['os_id'] ?? '');
            financeiro_api_responder($resposta, $resposta['sucesso'] ? 200 : 400);
        }
        if ($acao === 'relatorio') {
            financeiro_api_responder(relatorio_faturamento($_GET['mes'] ?? date('m'), $_GET['ano'] ?? date('Y')));
        }
        financeiro_api_responder(['sucesso' => false, 'mensagem' => 'Ação GET inválida.'], 400);
    }

    if ($method === 'POST') {
        if (!csrf_validate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            financeiro_api_responder(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
        }
        if ($acao === 'salvar') {
            $resposta = salvar_financeiro_os($_POST['os_id'] ?? '', $_POST);
            financeiro_api_responder($resposta, $resposta['sucesso'] ? 200 : 400);
        }
        financeiro_api_responder(['sucesso' => false, 'mensagem' => 'Ação POST inválida.'], 400);
    }

    financeiro_api_responder(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 400);
} catch (Throwable $e) {
    error_log('financeiro_api erro: ' . $e->getMessage());
    financeiro_api_responder(['sucesso' => false, 'mensagem' => 'Erro interno.'], 500);
}
