<?php
require_once __DIR__ . '/../includes/historico_functions.php';

header('Content-Type: application/json; charset=utf-8');
auth_required_api();

$acao = $_GET['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($acao === 'historico') {
    $resposta = obter_historico_cliente($_GET['cliente_id'] ?? 0);
    http_response_code($resposta['sucesso'] ? 200 : 400);
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['sucesso' => false, 'mensagem' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
