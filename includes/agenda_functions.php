<?php
// includes/agenda_functions.php — regras de agenda com ownership por usuário logado.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

if (!function_exists('verificar_autenticacao')) {
    function verificar_autenticacao(): string {
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            auth_required_api();
        } else {
            auth_required();
        }
        return auth_usuario() ?: AUTH_USER;
    }
}

function agenda_tipos_validos(): array {
    return ['visita', 'medicao', 'instalacao', 'acompanhamento', 'outro'];
}

function agenda_status_validos(): array {
    return ['agendado', 'confirmado', 'em_andamento', 'concluido', 'cancelado'];
}

function agenda_data_valida(string $data): bool {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $data);
    return $dt instanceof DateTime && $dt->format('Y-m-d H:i:s') === $data;
}

function criar_evento_agenda($titulo, $data, $tipo, $cliente_id, $observacoes, $os_id = null): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();

    $titulo = trim((string)$titulo);
    $tipo = trim((string)$tipo);
    $cliente_id = $cliente_id !== '' ? (int)$cliente_id : null;
    $os_id = $os_id !== '' ? $os_id : null;

    if ($titulo === '') {
        return ['sucesso' => false, 'mensagem' => 'Título é obrigatório.'];
    }
    if (!agenda_data_valida((string)$data)) {
        return ['sucesso' => false, 'mensagem' => 'Data deve estar no formato YYYY-MM-DD HH:MM:SS.'];
    }
    if (!in_array($tipo, agenda_tipos_validos(), true)) {
        return ['sucesso' => false, 'mensagem' => 'Tipo de evento inválido.'];
    }

    $stmt = $pdo->prepare("INSERT INTO agenda
        (cliente_id, os_id, titulo, descricao, data_inicio, data_fim, status, tipo, usuario_id, criado_em)
        VALUES (?, ?, ?, ?, ?, ?, 'agendado', ?, ?, ?)");
    $fim = date('Y-m-d H:i:s', strtotime($data . ' +1 hour'));
    $ok = $stmt->execute([
        $cliente_id,
        $os_id,
        $titulo,
        trim((string)$observacoes),
        $data,
        $fim,
        $tipo,
        $usuario_id,
        date('Y-m-d H:i:s'),
    ]);

    return [
        'sucesso' => $ok,
        'mensagem' => $ok ? 'Evento criado com sucesso.' : 'Erro ao criar evento.',
        'id' => $ok ? (int)$pdo->lastInsertId() : null,
    ];
}

function listar_agenda($mes, $ano): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $mes = max(1, min(12, (int)$mes));
    $ano = max(2000, min(2100, (int)$ano));
    $inicio = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
    $fim = date('Y-m-d H:i:s', strtotime($inicio . ' +1 month'));

    $stmt = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email
        FROM agenda a
        LEFT JOIN clientes c ON c.id = a.cliente_id
        WHERE a.usuario_id = ? AND a.data_inicio >= ? AND a.data_inicio < ?
        ORDER BY a.data_inicio ASC");
    $stmt->execute([$usuario_id, $inicio, $fim]);

    return ['sucesso' => true, 'dados' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function obter_evento($evento_id): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $stmt = $pdo->prepare("SELECT a.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email
        FROM agenda a
        LEFT JOIN clientes c ON c.id = a.cliente_id
        WHERE a.id = ? AND a.usuario_id = ?
        LIMIT 1");
    $stmt->execute([(int)$evento_id, $usuario_id]);
    $evento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evento) {
        return ['sucesso' => false, 'mensagem' => 'Evento não encontrado.'];
    }
    return ['sucesso' => true, 'dados' => $evento];
}

function atualizar_evento($evento_id, $dados): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $atual = obter_evento($evento_id);
    if (!$atual['sucesso']) {
        return $atual;
    }

    $permitidos = ['titulo', 'descricao', 'data_inicio', 'data_fim', 'status', 'tipo', 'cliente_id', 'os_id'];
    $sets = [];
    $valores = [];

    foreach ($permitidos as $campo) {
        if (!array_key_exists($campo, $dados)) {
            continue;
        }
        $valor = $dados[$campo];
        if ($campo === 'titulo' && trim((string)$valor) === '') {
            return ['sucesso' => false, 'mensagem' => 'Título é obrigatório.'];
        }
        if (in_array($campo, ['data_inicio', 'data_fim'], true) && $valor !== '' && !agenda_data_valida((string)$valor)) {
            return ['sucesso' => false, 'mensagem' => 'Data deve estar no formato YYYY-MM-DD HH:MM:SS.'];
        }
        if ($campo === 'tipo' && !in_array((string)$valor, agenda_tipos_validos(), true)) {
            return ['sucesso' => false, 'mensagem' => 'Tipo de evento inválido.'];
        }
        if ($campo === 'status' && !in_array((string)$valor, agenda_status_validos(), true)) {
            return ['sucesso' => false, 'mensagem' => 'Status inválido.'];
        }
        if (in_array($campo, ['cliente_id'], true)) {
            $valor = $valor !== '' ? (int)$valor : null;
        }
        if ($campo === 'os_id') {
            $valor = $valor !== '' ? $valor : null;
        }
        $sets[] = "$campo = ?";
        $valores[] = $valor;
    }

    if (!$sets) {
        return ['sucesso' => false, 'mensagem' => 'Nenhum campo válido para atualizar.'];
    }

    $valores[] = (int)$evento_id;
    $valores[] = $usuario_id;
    $stmt = $pdo->prepare('UPDATE agenda SET ' . implode(', ', $sets) . ' WHERE id = ? AND usuario_id = ?');
    $ok = $stmt->execute($valores);

    return ['sucesso' => $ok, 'mensagem' => $ok ? 'Evento atualizado.' : 'Erro ao atualizar evento.'];
}

function deletar_evento($evento_id): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $atual = obter_evento($evento_id);
    if (!$atual['sucesso']) {
        return $atual;
    }

    $stmt = $pdo->prepare('DELETE FROM agenda WHERE id = ? AND usuario_id = ?');
    $ok = $stmt->execute([(int)$evento_id, $usuario_id]);
    return ['sucesso' => $ok, 'mensagem' => $ok ? 'Evento removido.' : 'Erro ao remover evento.'];
}
