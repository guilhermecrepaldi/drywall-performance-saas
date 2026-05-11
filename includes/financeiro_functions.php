<?php
// includes/financeiro_functions.php — cálculos financeiros para OS.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

function financeiro_config_valor(string $chave, float $padrao = 0): float {
    global $pdo;
    $stmt = $pdo->prepare('SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1');
    $stmt->execute([$chave]);
    $valor = $stmt->fetchColumn();
    return $valor !== false && $valor !== '' ? (float)$valor : $padrao;
}

function financeiro_os_por_id(string $os_id): ?array {
    $rows = ler_db('os', ['id' => $os_id]);
    if (!$rows) {
        return null;
    }
    $os = $rows[0];
    if (isset($os['itens']) && is_string($os['itens'])) {
        $os['itens'] = json_decode($os['itens'], true) ?: [];
    }
    return $os;
}

function financeiro_preco_por_descricao(array $precos, string $descricao): ?array {
    $desc = mb_strtolower(trim($descricao));
    if ($desc === '') {
        return null;
    }
    foreach ($precos as $preco) {
        $produto = mb_strtolower(trim($preco['produto'] ?? ''));
        if ($produto !== '' && ($desc === $produto || str_contains($desc, $produto) || str_contains($produto, $desc))) {
            return $preco;
        }
    }
    return null;
}

function calcular_custo_material_os(array $os): float {
    $precos = ler_db('precos');
    $total = 0.0;
    foreach (($os['itens'] ?? []) as $item) {
        $medida = (float)($item['medida'] ?? 0);
        $unitario_orcado = (float)($item['vunit'] ?? 0);
        $preco = financeiro_preco_por_descricao($precos, $item['desc'] ?? '');
        if ($preco) {
            $custo_unitario = isset($preco['custo']) && $preco['custo'] !== null
                ? (float)$preco['custo']
                : (float)($preco['preco'] ?? 0);
            $perda = 1 + ((float)($preco['perda'] ?? 0) / 100);
            $total += $medida * $custo_unitario * $perda;
        } else {
            $total += $medida * $unitario_orcado * 0.45;
        }
    }
    return round($total, 2);
}

function financeiro_registro(string $os_id): ?array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM financeiro WHERE os_id = ? LIMIT 1');
    $stmt->execute([$os_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function salvar_financeiro_os(string $os_id, array $dados): array {
    global $pdo;
    auth_required();
    $os = financeiro_os_por_id($os_id);
    if (!$os) {
        return ['sucesso' => false, 'mensagem' => 'OS não encontrada.'];
    }

    $custo_material = (float)($dados['custo_material'] ?? calcular_custo_material_os($os));
    $horas = (float)($dados['horas_mao_obra'] ?? 0);
    $valor_hora = (float)($dados['valor_hora'] ?? financeiro_config_valor('valor_hora_mao_obra', 85));
    $custo_mao_obra = (float)($dados['custo_mao_obra'] ?? ($horas * $valor_hora));
    $overhead = (float)($dados['overhead'] ?? financeiro_config_valor('overhead_padrao_percentual', 15));
    $margem = (float)($dados['margem'] ?? financeiro_config_valor('margem_padrao_percentual', 25));
    $base = $custo_material + $custo_mao_obra;
    $custo_com_overhead = $base * (1 + $overhead / 100);
    $valor_sugerido = $margem >= 100 ? $custo_com_overhead : $custo_com_overhead / max(0.01, (1 - $margem / 100));
    $custo_real = isset($dados['custo_real']) && $dados['custo_real'] !== '' ? (float)$dados['custo_real'] : $custo_com_overhead;

    $existente = financeiro_registro($os_id);
    if ($existente) {
        $stmt = $pdo->prepare('UPDATE financeiro SET custo_material=?, custo_mao_obra=?, horas_mao_obra=?, valor_hora=?, overhead=?, margem=?, custo_real=?, valor_sugerido=?, atualizado_em=? WHERE os_id=?');
        $ok = $stmt->execute([$custo_material, $custo_mao_obra, $horas, $valor_hora, $overhead, $margem, $custo_real, round($valor_sugerido, 2), date('Y-m-d H:i:s'), $os_id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO financeiro (os_id,custo_material,custo_mao_obra,horas_mao_obra,valor_hora,overhead,margem,custo_real,valor_sugerido,criado_em,atualizado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $ok = $stmt->execute([$os_id, $custo_material, $custo_mao_obra, $horas, $valor_hora, $overhead, $margem, $custo_real, round($valor_sugerido, 2), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    }

    return ['sucesso' => $ok, 'mensagem' => $ok ? 'Custos salvos.' : 'Erro ao salvar custos.'];
}

function calcular_custo_os($os_id): array {
    auth_required();
    $os = financeiro_os_por_id((string)$os_id);
    if (!$os) {
        return ['sucesso' => false, 'mensagem' => 'OS não encontrada.'];
    }

    $registro = financeiro_registro((string)$os_id) ?: [];
    $custo_material = isset($registro['custo_material']) ? (float)$registro['custo_material'] : calcular_custo_material_os($os);
    $horas = (float)($registro['horas_mao_obra'] ?? 0);
    $valor_hora = (float)($registro['valor_hora'] ?? financeiro_config_valor('valor_hora_mao_obra', 85));
    $custo_mao_obra = (float)($registro['custo_mao_obra'] ?? ($horas * $valor_hora));
    $overhead = (float)($registro['overhead'] ?? financeiro_config_valor('overhead_padrao_percentual', 15));
    $margem = (float)($registro['margem'] ?? financeiro_config_valor('margem_padrao_percentual', 25));
    $base = $custo_material + $custo_mao_obra;
    $custo_total = $base * (1 + $overhead / 100);
    $valor_sugerido = $margem >= 100 ? $custo_total : $custo_total / max(0.01, (1 - $margem / 100));
    $valor_orcado = (float)($os['total_geral'] ?? 0);
    $custo_real = isset($registro['custo_real']) && $registro['custo_real'] !== null ? (float)$registro['custo_real'] : $custo_total;
    $lucro = $valor_orcado - $custo_real;

    return [
        'sucesso' => true,
        'dados' => [
            'os' => $os,
            'custo_material' => round($custo_material, 2),
            'horas_mao_obra' => round($horas, 2),
            'valor_hora' => round($valor_hora, 2),
            'custo_mao_obra' => round($custo_mao_obra, 2),
            'overhead' => round($overhead, 2),
            'margem' => round($margem, 2),
            'custo_total' => round($custo_total, 2),
            'custo_real' => round($custo_real, 2),
            'valor_sugerido' => round($valor_sugerido, 2),
            'valor_orcado' => round($valor_orcado, 2),
            'lucro' => round($lucro, 2),
            'lucro_percentual' => $valor_orcado > 0 ? round(($lucro / $valor_orcado) * 100, 2) : 0,
        ],
    ];
}

function obter_lucro_real($os_id): array {
    $calc = calcular_custo_os($os_id);
    if (!$calc['sucesso']) {
        return $calc;
    }
    $d = $calc['dados'];
    return [
        'sucesso' => true,
        'dados' => [
            'os_id' => $os_id,
            'valor_orcado' => $d['valor_orcado'],
            'custo_real' => $d['custo_real'],
            'lucro' => $d['lucro'],
            'lucro_percentual' => $d['lucro_percentual'],
        ],
    ];
}

function relatorio_faturamento($mes, $ano): array {
    auth_required();
    $mes = max(1, min(12, (int)$mes));
    $ano = max(2000, min(2100, (int)$ano));
    $inicio = sprintf('%04d-%02d-01', $ano, $mes);
    $fim = date('Y-m-d', strtotime($inicio . ' +1 month'));
    $os_lista = ler_db('os');
    $por_cliente = [];
    $totais = ['receita' => 0.0, 'custos' => 0.0, 'lucro' => 0.0, 'pendentes' => 0.0, 'nf_valor' => 0.0, 'nf_sem_numero' => 0.0];
    $pendentes = [];

    foreach ($os_lista as $os) {
        $data = $os['emissao'] ?: substr((string)($os['criado_em'] ?? ''), 0, 10);
        if (!$data || $data < $inicio || $data >= $fim) {
            continue;
        }
        $calc = calcular_custo_os($os['id']);
        if (!$calc['sucesso']) {
            continue;
        }
        $d = $calc['dados'];
        $cliente = $os['cliente_nome'] ?: 'Sem cliente';
        if (!isset($por_cliente[$cliente])) {
            $por_cliente[$cliente] = ['cliente' => $cliente, 'receita' => 0.0, 'custos' => 0.0, 'lucro' => 0.0, 'quantidade' => 0];
        }
        $por_cliente[$cliente]['receita'] += $d['valor_orcado'];
        $por_cliente[$cliente]['custos'] += $d['custo_real'];
        $por_cliente[$cliente]['lucro'] += $d['lucro'];
        $por_cliente[$cliente]['quantidade']++;
        $totais['receita'] += $d['valor_orcado'];
        $totais['custos'] += $d['custo_real'];
        $totais['lucro'] += $d['lucro'];
        if (!empty($os['nota_fiscal_numero']) || !empty($os['nota_fiscal'])) {
            $totais['nf_valor'] += (float)($os['nota_fiscal_valor'] ?? $d['valor_orcado']);
        }
        if (in_array($os['status'] ?? '', ['em_execucao', 'execucao', 'concluido', 'pago'], true) && empty($os['nota_fiscal_numero']) && empty($os['nota_fiscal'])) {
            $totais['nf_sem_numero'] += $d['valor_orcado'];
        }

        if (in_array($os['status'] ?? '', ['aprovado', 'execucao', 'concluido'], true) && empty($os['pagto_data'])) {
            $totais['pendentes'] += $d['valor_orcado'];
            $pendentes[] = [
                'id' => $os['id'],
                'codigo' => $os['codigo'],
                'cliente_nome' => $cliente,
                'status' => $os['status'],
                'valor' => $d['valor_orcado'],
                'nota_fiscal_numero' => $os['nota_fiscal_numero'] ?? '',
            ];
        }
    }

    foreach ($por_cliente as &$linha) {
        $linha['receita'] = round($linha['receita'], 2);
        $linha['custos'] = round($linha['custos'], 2);
        $linha['lucro'] = round($linha['lucro'], 2);
        $linha['margem'] = $linha['receita'] > 0 ? round(($linha['lucro'] / $linha['receita']) * 100, 2) : 0;
    }
    unset($linha);

    return [
        'sucesso' => true,
        'dados' => [
            'totais' => array_map(fn($v) => round($v, 2), $totais),
            'por_cliente' => array_values($por_cliente),
            'pendentes' => $pendentes,
        ],
    ];
}
