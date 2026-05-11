<?php
require_once __DIR__ . '/../includes/agenda_functions.php';

header('Content-Type: application/json; charset=utf-8');

function agenda_api_responder(array $resposta, int $status = 200): void {
    http_response_code($status);
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

function agenda_api_data(?string $valor): string {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return '';
    }
    if (str_contains($valor, 'T')) {
        $valor = str_replace('T', ' ', $valor);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $valor)) {
        $valor .= ':00';
    }
    return $valor;
}

$acao = $_GET['acao'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if ($acao === 'listar') {
            agenda_api_responder(listar_agenda($_GET['mes'] ?? date('m'), $_GET['ano'] ?? date('Y')));
        }
        if ($acao === 'obter') {
            $resposta = obter_evento($_GET['id'] ?? 0);
            agenda_api_responder($resposta, $resposta['sucesso'] ? 200 : 400);
        }
        agenda_api_responder(['sucesso' => false, 'mensagem' => 'Ação GET inválida.'], 400);
    }

    if ($method === 'POST') {
        if (!csrf_validate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            agenda_api_responder(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
        }

        if ($acao === 'criar') {
            $resposta = criar_evento_agenda(
                $_POST['titulo'] ?? '',
                agenda_api_data($_POST['data_inicio'] ?? $_POST['data'] ?? ''),
                $_POST['tipo'] ?? '',
                $_POST['cliente_id'] ?? null,
                $_POST['descricao'] ?? $_POST['observacoes'] ?? '',
                $_POST['os_id'] ?? null
            );
            agenda_api_responder($resposta, $resposta['sucesso'] ? 200 : 400);
        }

        if ($acao === 'atualizar') {
            $dados = [
                'titulo' => $_POST['titulo'] ?? null,
                'descricao' => $_POST['descricao'] ?? '',
                'data_inicio' => agenda_api_data($_POST['data_inicio'] ?? ''),
                'data_fim' => agenda_api_data($_POST['data_fim'] ?? ''),
                'status' => $_POST['status'] ?? '',
                'tipo' => $_POST['tipo'] ?? '',
                'cliente_id' => $_POST['cliente_id'] ?? null,
                'os_id' => $_POST['os_id'] ?? null,
            ];
            $dados = array_filter($dados, fn($valor) => $valor !== null);
            $resposta = atualizar_evento($_POST['id'] ?? 0, $dados);
            agenda_api_responder($resposta, $resposta['sucesso'] ? 200 : 400);
        }

        if ($acao === 'deletar') {
            $resposta = deletar_evento($_POST['id'] ?? 0);
            agenda_api_responder($resposta, $resposta['sucesso'] ? 200 : 400);
        }

        agenda_api_responder(['sucesso' => false, 'mensagem' => 'Ação POST inválida.'], 400);
    }

    agenda_api_responder(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 400);
} catch (Throwable $e) {
    error_log('agenda_api erro: ' . $e->getMessage());
    agenda_api_responder(['sucesso' => false, 'mensagem' => 'Erro interno.'], 500);
}
