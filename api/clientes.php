<?php
// api/clientes.php — API REST para clientes (MySQL)
require_once '../includes/auth.php';
auth_required_api();
require_once '../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$acao   = $_GET['acao'] ?? '';

switch ($method) {
    case 'GET':
        if ($acao === 'buscar' && isset($_GET['q'])) {
            $q = strtolower(trim($_GET['q']));
            $clientes = ler_db('clientes');
            $result = array_values(array_filter($clientes, fn($c) =>
                str_contains(strtolower($c['nome'] ?? ''), $q) ||
                str_contains($c['telefone'] ?? '', $q)
            ));
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } elseif (isset($_GET['id'])) {
            $rows = ler_db('clientes', ['id' => $_GET['id']]);
            if ($rows) { echo json_encode($rows[0], JSON_UNESCAPED_UNICODE); exit; }
            http_response_code(404);
            echo json_encode(['erro' => 'Cliente não encontrado']);
        } else {
            echo json_encode(ler_db('clientes'), JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'POST':
        csrf_required();
        $dados = json_decode(file_get_contents('php://input'), true);
        if (!isset($dados['nome'])) { resposta_json(false, 'Nome obrigatório'); }
        $dados['id'] = isset($dados['id']) ? (int)$dados['id'] : proximo_id_cliente();
        $dados['criado_em'] = $dados['criado_em'] ?? date('Y-m-d H:i:s');
        $dados['atualizado_em'] = date('Y-m-d H:i:s');
        if (salvar_db('clientes', $dados, 'id')) {
            // Enviar backup por e-mail
            $anexos = [
                'clientes.json' => json_encode(ler_db('clientes'), JSON_UNESCAPED_UNICODE),
                'precos.json' => json_encode(ler_db('precos'), JSON_UNESCAPED_UNICODE),
                'os.json' => json_encode(ler_db('os'), JSON_UNESCAPED_UNICODE),
            ];
            enviar_backup_email(BACKUP_EMAIL, 'Backup Premium Detailing Manager - Clientes', 'Backup automático após alteração de clientes.', $anexos);

            resposta_json(true, 'Cliente salvo', ['id' => $dados['id']]);
        } else {
            resposta_json(false, 'Erro ao salvar cliente');
        }
        break;

    case 'DELETE':
        csrf_required();
        parse_str(file_get_contents("php://input"), $input);
        $id = $_GET['id'] ?? $input['id'] ?? null;
        if (!$id) { resposta_json(false, 'ID ausente'); }
        if (deletar_db('clientes', ['id' => $id])) { resposta_json(true, 'Cliente removido'); }
        resposta_json(false, 'Erro ao remover');
        break;

    default:
        http_response_code(405);
        echo json_encode(['erro' => 'Método não permitido']);
}
