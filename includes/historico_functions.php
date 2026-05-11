<?php
// includes/historico_functions.php — timeline CRM por cliente.

require_once __DIR__ . '/agenda_functions.php';

function historico_usuario_atual(): string {
    return auth_usuario() ?: AUTH_USER;
}

function historico_evento(string $data, string $tipo, string $descricao, string $usuario, array $detalhes = []): array {
    return [
        'data' => $data,
        'tipo' => $tipo,
        'descricao' => $descricao,
        'usuario' => $usuario,
        'detalhes' => $detalhes,
    ];
}

function obter_historico_cliente($cliente_id): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $cliente_id = (int)$cliente_id;

    if ($cliente_id <= 0) {
        return ['sucesso' => false, 'mensagem' => 'Cliente inválido.', 'historico' => [], 'total' => 0];
    }

    $cliente = ler_db('clientes', ['id' => $cliente_id]);
    if (!$cliente) {
        return ['sucesso' => false, 'mensagem' => 'Cliente não encontrado.', 'historico' => [], 'total' => 0];
    }

    $historico = [];

    $stmtAgenda = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome
        FROM agenda a
        LEFT JOIN clientes c ON c.id = a.cliente_id
        WHERE a.cliente_id = ? AND (a.usuario_id = ? OR a.usuario_id IS NULL OR a.usuario_id = '')
        ORDER BY a.data_inicio DESC");
    $stmtAgenda->execute([$cliente_id, $usuario_id]);
    foreach ($stmtAgenda->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $historico[] = historico_evento(
            $row['data_inicio'] ?? $row['criado_em'] ?? '',
            'agenda',
            trim(($row['titulo'] ?? 'Evento') . ' · ' . ($row['tipo'] ?? 'agenda') . ' · ' . ($row['status'] ?? '')),
            $row['usuario_id'] ?: $usuario_id,
            [
                'id' => $row['id'],
                'descricao_completa' => $row['descricao'] ?? '',
                'status' => $row['status'] ?? '',
                'tipo_evento' => $row['tipo'] ?? '',
            ]
        );
    }

    $stmtOs = $pdo->prepare('SELECT * FROM os WHERE cliente_id = ? ORDER BY COALESCE(emissao, criado_em) DESC');
    $stmtOs->execute([$cliente_id]);
    foreach ($stmtOs->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $historico[] = historico_evento(
            $row['emissao'] ?: ($row['criado_em'] ?? ''),
            'os',
            'OS ' . ($row['codigo'] ?? $row['id']) . ' · ' . ($row['status'] ?? 'rascunho') . ' · ' . moeda((float)($row['total_geral'] ?? 0)),
            $row['usuario_id'] ?? $usuario_id,
            [
                'id' => $row['id'],
                'codigo' => $row['codigo'] ?? '',
                'valor' => (float)($row['total_geral'] ?? 0),
                'status' => $row['status'] ?? '',
                'descricao_completa' => $row['obs_tecnicas'] ?? '',
            ]
        );
    }

    $stmtFollowups = $pdo->prepare("SELECT * FROM followups
        WHERE cliente_id = ? AND (usuario_id = ? OR usuario_id IS NULL OR usuario_id = '')
        ORDER BY COALESCE(data_lembrete, criado_em) DESC");
    $stmtFollowups->execute([$cliente_id, $usuario_id]);
    foreach ($stmtFollowups->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $historico[] = historico_evento(
            $row['data_lembrete'] ?: ($row['criado_em'] ?? ''),
            'followup',
            ($row['concluido'] ? 'Follow-up concluído: ' : 'Follow-up: ') . ($row['descricao'] ?? ''),
            $row['usuario_id'] ?: $usuario_id,
            [
                'id' => $row['id'],
                'concluido' => (int)($row['concluido'] ?? 0),
                'descricao_completa' => $row['descricao'] ?? '',
            ]
        );
    }

    usort($historico, fn($a, $b) => strtotime($b['data'] ?: '1970-01-01') <=> strtotime($a['data'] ?: '1970-01-01'));

    return [
        'sucesso' => true,
        'historico' => array_values($historico),
        'eventos' => array_values($historico),
        'total' => count($historico),
    ];
}
