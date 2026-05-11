<?php
require_once '../includes/auth.php';
auth_required_api();
require_once '../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(ler_db('precos'), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    csrf_required();
    $updates = json_decode(file_get_contents('php://input'), true);
    if (!is_array($updates)) { resposta_json(false, 'Dados inválidos'); }

    global $pdo;
    $stmt = $pdo->prepare('UPDATE precos SET preco = ?, perda = ?, custo = ? WHERE id = ?');
    foreach ($updates as $u) {
        $stmt->execute([(float)($u['preco'] ?? 0), (int)($u['perda'] ?? 0), $u['custo'] ? (float)$u['custo'] : null, $u['id']]);
    }

    // Enviar backup por e-mail
    $anexos = [
        'precos.json' => json_encode(ler_db('precos'), JSON_UNESCAPED_UNICODE),
        'clientes.json' => json_encode(ler_db('clientes'), JSON_UNESCAPED_UNICODE),
        'os.json' => json_encode(ler_db('os'), JSON_UNESCAPED_UNICODE),
    ];
    enviar_backup_email(BACKUP_EMAIL, 'Backup Premium Detailing Manager - Preços', 'Backup automático após alteração de preços.', $anexos);

    resposta_json(true, 'Preços salvos');
}
