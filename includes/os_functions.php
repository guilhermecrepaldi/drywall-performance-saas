<?php
// includes/os_functions.php — pipeline e aprovação pública de OS.

require_once __DIR__ . '/historico_functions.php';

function os_pipeline_status_validos(): array {
    return ['rascunho', 'enviado', 'aprovado', 'em_execucao', 'concluido', 'pago'];
}

function os_status_labels_pipeline(): array {
    return [
        'rascunho' => 'Rascunho',
        'enviado' => 'Enviado',
        'aprovado' => 'Aprovado',
        'em_execucao' => 'Em Execução',
        'concluido' => 'Concluído',
        'pago' => 'Pago',
    ];
}

function os_status_normalizar(string $status): string {
    return $status === 'execucao' ? 'em_execucao' : $status;
}

function atualizar_status_os($os_id, $novo_status): array {
    global $pdo;
    verificar_autenticacao();
    $os_id = (string)$os_id;
    $novo_status = os_status_normalizar((string)$novo_status);

    if (!in_array($novo_status, os_pipeline_status_validos(), true)) {
        return ['sucesso' => false, 'mensagem' => 'Status inválido.'];
    }

    $stmt = $pdo->prepare('UPDATE os SET status = ?, atualizado_em = ? WHERE id = ?');
    $ok = $stmt->execute([$novo_status, date('Y-m-d H:i:s'), $os_id]);
    return ['sucesso' => $ok, 'mensagem' => $ok ? 'Status atualizado.' : 'Erro ao atualizar status.'];
}

function os_uuid_token(): string {
    $bytes = random_bytes(16);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function gerar_token_aprovacao_os($os_id): array {
    global $pdo;
    verificar_autenticacao();
    $os = ler_db('os', ['id' => (string)$os_id]);
    if (!$os) {
        return ['sucesso' => false, 'mensagem' => 'OS não encontrada.'];
    }
    if (!empty($os[0]['token_aprovacao'])) {
        return ['sucesso' => true, 'token' => $os[0]['token_aprovacao'], 'mensagem' => 'Token já existente.'];
    }

    do {
        $token = os_uuid_token();
        $stmtCheck = $pdo->prepare('SELECT COUNT(*) FROM os WHERE token_aprovacao = ?');
        $stmtCheck->execute([$token]);
    } while ((int)$stmtCheck->fetchColumn() > 0);

    $stmt = $pdo->prepare('UPDATE os SET token_aprovacao = ?, atualizado_em = ? WHERE id = ?');
    $ok = $stmt->execute([$token, date('Y-m-d H:i:s'), (string)$os_id]);
    return ['sucesso' => $ok, 'token' => $ok ? $token : null, 'mensagem' => $ok ? 'Token gerado.' : 'Erro ao gerar token.'];
}

function os_decodificar_itens(array $os): array {
    if (isset($os['itens']) && is_string($os['itens'])) {
        $os['itens'] = json_decode($os['itens'], true) ?: [];
    }
    return $os;
}

function obter_os_por_token($token): array {
    global $pdo;
    $token = trim((string)$token);
    if ($token === '') {
        return ['sucesso' => false, 'mensagem' => 'Token ausente.'];
    }

    $stmt = $pdo->prepare('SELECT os.*, c.nome AS cliente_nome_db, c.telefone AS cliente_telefone_db, c.email AS cliente_email_db, c.cpf_cnpj AS cliente_doc_db
        FROM os
        LEFT JOIN clientes c ON c.id = os.cliente_id
        WHERE os.token_aprovacao = ?
        LIMIT 1');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['sucesso' => false, 'mensagem' => 'Link de aprovação inválido.'];
    }

    $row = os_decodificar_itens($row);
    $cliente = [
        'id' => $row['cliente_id'] ?? null,
        'nome' => $row['cliente_nome_db'] ?: ($row['cliente_nome'] ?? ''),
        'telefone' => $row['cliente_telefone_db'] ?: ($row['cliente_tel'] ?? ''),
        'email' => $row['cliente_email_db'] ?? '',
        'documento' => $row['cliente_doc_db'] ?: ($row['cliente_cpf'] ?? ''),
    ];
    return ['sucesso' => true, 'os' => $row, 'cliente' => $cliente];
}

function aprovar_os_por_token($token, string $nome = '', string $telefone = ''): array {
    global $pdo;
    $res = obter_os_por_token($token);
    if (!$res['sucesso']) {
        return $res;
    }
    if (trim($nome) === '' || trim($telefone) === '') {
        return ['sucesso' => false, 'mensagem' => 'Nome e telefone são obrigatórios.'];
    }

    $os = $res['os'];
    if (in_array(os_status_normalizar($os['status'] ?? ''), ['aprovado', 'em_execucao', 'concluido', 'pago'], true)) {
        return ['sucesso' => true, 'mensagem' => 'OS já aprovada.', 'os' => $os, 'cliente' => $res['cliente']];
    }

    $agora = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE os SET status='aprovado', data_aprovacao=?, aprovado_nome=?, aprovado_telefone=?, atualizado_em=? WHERE token_aprovacao=?");
    $ok = $stmt->execute([$agora, trim($nome), trim($telefone), $agora, trim((string)$token)]);
    if (!$ok) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao aprovar OS.'];
    }

    return obter_os_por_token($token) + ['mensagem' => 'OS aprovada com sucesso.'];
}

function listar_os_pipeline(): array {
    verificar_autenticacao();
    $lista = array_map('os_decodificar_itens', ler_db('os'));
    foreach ($lista as &$os) {
        $os['status'] = os_status_normalizar($os['status'] ?? 'rascunho');
    }
    unset($os);
    usort($lista, fn($a, $b) => strtotime($b['criado_em'] ?? '1970-01-01') <=> strtotime($a['criado_em'] ?? '1970-01-01'));
    return ['sucesso' => true, 'dados' => $lista, 'status' => os_status_labels_pipeline()];
}
