<?php
// includes/helpers.php — funções utilitárias do sistema
// Atualizado para usar MySQL via PDO.

require_once __DIR__ . '/config.php';

// Conexão DB
if (!function_exists('conectar_db')) {
    function conectar_db(): PDO {
        static $db = null;
        if ($db instanceof PDO) {
            return $db;
        }

        try {
            // Tenta MySQL primeiro (Produção)
            $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . $charset,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 2, // Timeout curto para fallback rápido
                ]
            );
            return $db;
        } catch (PDOException $e) {
            // Fallback para SQLite (Desenvolvimento Local)
            try {
                $sqlitePath = __DIR__ . '/../dados.db';
                $db = new PDO('sqlite:' . $sqlitePath);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $db;
            } catch (PDOException $e2) {
                error_log('Erro conexão BD (MySQL e SQLite): ' . $e2->getMessage());
                http_response_code(500);
                die('Erro interno ao conectar ao banco de dados: ' . $e2->getMessage());
            }
        }
    }
}

$pdo = conectar_db();
require_once __DIR__ . '/database_setup.php';
database_setup_run();

// Tabelas permitidas — previne SQL injection via nome de tabela
const TABELAS_PERMITIDAS = ['clientes', 'os', 'precos', 'agenda', 'followups', 'configuracoes', 'anexos', 'financeiro', 'produtos', 'fornecedores', 'produto_fornecedor_precos', 'desenvolvimento'];
const FUNCOES_MODULOS = [
    'agenda' => ['criar_evento_agenda', 'listar_agenda', 'obter_evento', 'atualizar_evento', 'deletar_evento'],
    'financeiro' => ['calcular_custo_os', 'obter_lucro_real', 'relatorio_faturamento'],
    'historico' => ['obter_historico_cliente'],
    'os' => ['atualizar_status_os', 'gerar_token_aprovacao_os', 'aprovar_os_por_token', 'obter_os_por_token'],
    'configuracoes' => ['obter_configuracoes', 'atualizar_configuracoes', 'fazer_backup_mysql'],
];

function validar_tabela(string $tabela): string {
    if (!in_array($tabela, TABELAS_PERMITIDAS, true)) {
        throw new InvalidArgumentException("Tabela não permitida: $tabela");
    }
    return $tabela;
}

// ── Lê dados do DB ──────────────────────────────────────────────
function ler_db(string $tabela, array $where = []): array {
    global $pdo;
    $tabela = validar_tabela($tabela);
    $query  = "SELECT * FROM $tabela";
    if ($where) {
        $conditions = [];
        foreach ($where as $col => $val) {
            $conditions[] = "$col = ?";
        }
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_values($where));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ── Salva no DB ────────────────────────────────────────────
function salvar_db(string $tabela, array $dados, string $id_col = 'id'): bool {
    global $pdo;
    $tabela = validar_tabela($tabela);
    $dados['atualizado_em'] = date('Y-m-d H:i:s');
    $id_existe = false;
    if (isset($dados[$id_col]) && $dados[$id_col] !== '') {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM $tabela WHERE $id_col = ?");
        $stmtCheck->execute([$dados[$id_col]]);
        $id_existe = (int)$stmtCheck->fetchColumn() > 0;
    }

    if (!$id_existe) {
        $dados['criado_em'] = date('Y-m-d H:i:s');
        $cols = array_keys($dados);
        $placeholders = str_repeat('?,', count($cols) - 1) . '?';
        $stmt = $pdo->prepare("INSERT INTO $tabela (" . implode(',', $cols) . ") VALUES ($placeholders)");
        return $stmt->execute(array_values($dados));
    } else {
        $set = [];
        $values = [];
        foreach ($dados as $col => $val) {
            if ($col !== $id_col) {
                $set[] = "$col = ?";
                $values[] = $val;
            }
        }
        $values[] = $dados[$id_col];
        $stmt = $pdo->prepare("UPDATE $tabela SET " . implode(',', $set) . " WHERE $id_col = ?");
        return $stmt->execute($values);
    }
}

// ── Funções específicas (mantidas para compatibilidade) ────────
function ler_json(string $arquivo): array {
    // Fallback ou para arquivos não migrados
    $path = DADOS_DIR . $arquivo;
    if (!file_exists($path)) return [];
    $conteudo = file_get_contents($path);
    return json_decode($conteudo, true) ?? [];
}

function salvar_json(string $arquivo, array $dados): bool {
    // Fallback
    $path = DADOS_DIR . $arquivo;
    return file_put_contents($path, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

define('DADOS_DIR', __DIR__ . '/../dados/');

// ── Gera próximo ID de cliente ───────────────────────────────
function proximo_id_cliente(): int {
    $clientes = ler_db('clientes');
    if (empty($clientes)) return 1;
    $ids = array_column($clientes, 'id');
    return max($ids) + 1;
}

// ── Gera código da OS ────────────────────────────────────────
function gerar_codigo_os(int $cliente_id, string $segmento, int $ano): string {
    $os_lista = ler_db('os');
    $ano_str  = substr((string)$ano, -2);
    $prefixo  = sprintf('%03d', $cliente_id) . strtoupper($segmento[0]) . $ano_str;

    $seq = 1;
    foreach ($os_lista as $os) {
        if (isset($os['codigo']) && strpos($os['codigo'], $prefixo) === 0) {
            $seq++;
        }
    }
    return $prefixo . sprintf('%02d', $seq);
}

// ── Segmentos disponíveis ────────────────────────────────────
function segmentos(): array {
    return [
        'H' => 'Hatch / Compacto (P)',
        'S' => 'Sedan / Médio (M)',
        'V' => 'SUV / Crossover (G)',
        'P' => 'Pick-up / Caminhonete (GG)',
        'O' => 'Outros / Especial',
    ];
}

function multiplicadores_tamanho(): array {
    return [
        'H' => 1.0,
        'S' => 1.2,
        'V' => 1.5,
        'P' => 1.8,
        'O' => 1.0,
    ];
}

// ── Status disponíveis ───────────────────────────────────────
function status_os(): array {
    return [
        'rascunho'  => 'Rascunho',
        'enviado'   => 'Enviado',
        'aprovado'  => 'Aprovado',
        'em_execucao' => 'Em execução',
        'execucao'  => 'Em execução',
        'concluido' => 'Concluído',
        'pago'      => 'Pago',
        'cancelado' => 'Cancelado',
    ];
}

function status_exige_nf(string $status): bool {
    return in_array($status, ['em_execucao', 'execucao', 'concluido', 'pago'], true);
}

function origens_lead(): array {
    return [
        'indicacao' => 'Indicação',
        'getninjas' => 'GetNinjas',
        'google' => 'Google / Busca',
        'google_ads' => 'Google Ads',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'site' => 'Site',
        'olx' => 'OLX',
        'habitissimo' => 'Habitissimo',
        'condominio' => 'Condomínio / Síndico',
        'arquiteto' => 'Arquiteto / Designer',
        'obra_vizinha' => 'Obra vizinha',
        'cliente_antigo' => 'Cliente antigo',
        'outro' => 'Outro',
    ];
}

function criterios_fornecedor(): array {
    return [
        'entrega' => 'Entrega no prazo',
        'retorno_rapido' => 'Retorno rápido',
        'preco_competitivo' => 'Preço competitivo',
        'qualidade' => 'Qualidade consistente',
        'disponibilidade' => 'Produto disponível',
        'troca_devolucao' => 'Troca/devolução fácil',
        'emite_nf' => 'Emite NF',
        'condicao_pagamento' => 'Boa condição de pagamento',
    ];
}

function desenvolvimento_etapas(): array {
    return [
        'lead' => 'Lead',
        'ligacao' => 'Ligação',
        'retorno' => 'Retorno',
        'visita' => 'Visita',
        'orcamento' => 'Orçamento',
        'trabalho' => 'Trabalho',
        'perdido' => 'Perdido',
        'pausado' => 'Pausado',
    ];
}

function desenvolvimento_status(): array {
    return [
        'novo' => 'Novo',
        'em_andamento' => 'Em andamento',
        'aguardando' => 'Aguardando',
        'ganho' => 'Ganho',
        'perdido' => 'Perdido',
    ];
}

function tabela_servicos_cnae(): array {
    return [
        'Estética Externa' => [
            'Polimento Comercial' => ['4520-0/05'],
            'Polimento Técnico (Correção)' => ['4520-0/05'],
            'Vitrificação de Pintura (Ceramic)' => ['4520-0/05'],
            'Lavagem Detalhada Premium' => ['4520-0/05'],
            'Descontaminação de Pintura' => ['4520-0/05'],
            'Lavagem Técnica de Motor' => ['4520-0/05'],
        ],
        'Estética Interna' => [
            'Higienização Interna Completa' => ['4520-0/05'],
            'Limpeza e Hidratação de Couro' => ['4520-0/05'],
            'Impermeabilização de Tecidos' => ['4520-0/05'],
            'Oxi-sanitização (Ozônio)' => ['4520-0/05'],
            'Limpeza Detalhada de Painel/Plásticos' => ['4520-0/05'],
        ],
        'Proteção & Estilo' => [
            'Aplicação de PPF (Full)' => ['4520-0/05'],
            'Aplicação de Película Solar (Insulfilm)' => ['4520-0/05'],
            'Envelopamento de Retrovisores/Teto' => ['4520-0/05'],
            'Pintura de Pinças de Freio' => ['4520-0/01'],
        ],
        'Reparos Rápidos' => [
            'Martelinho de Ouro (Reparo)' => ['4520-0/01'],
            'Revitalização de Faróis' => ['4520-0/05'],
            'Pintura de Rodas' => ['4520-0/01'],
            'Retoque de Pintura (Spot Repair)' => ['4520-0/01'],
        ],
        'Produtos / Revenda' => [
            'Cera Sintética Profissional' => ['4744-0/99'],
            'Kit de Manutenção Home Care' => ['4744-0/99'],
            'Aromatizante Premium' => ['4744-0/99'],
        ],
    ];
}

function servicos_disponiveis(): array {
    $list = [];
    foreach (tabela_servicos_cnae() as $categoria => $servicos) {
        foreach ($servicos as $servico => $cn ) {
            $list[] = [
                'categoria' => $categoria,
                'servico'   => $servico,
                'cnae'      => implode(' / ', $cn),
            ];
        }
    }
    return $list;
}

function cnae_por_servico(string $servico): string {
    foreach (tabela_servicos_cnae() as $servicos) {
        foreach ($servicos as $nome => $cn) {
            if (mb_strtolower(trim($nome)) === mb_strtolower(trim($servico))) {
                return implode(' / ', $cn);
            }
        }
    }
    return '';
}

function cnae_descricoes(): array {
    return [
        '4520-0/05' => 'Serviços de lavagem, polimento e estética automotiva.',
        '4520-0/01' => 'Serviços de lanternagem, funilaria e pintura de veículos.',
        '4744-0/99' => 'Comércio varejista de produtos automotivos e conservação.',
    ];
}

// ── Badge HTML de status ─────────────────────────────────────
function badge_status(string $status): string {
    $labels = status_os();
    $label  = $labels[$status] ?? $status;
    return '<span class="badge badge-' . htmlspecialchars($status) . '">' . htmlspecialchars($label) . '</span>';
}

// ── Formata moeda BR ─────────────────────────────────────────
function moeda(float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// ── Envia e-mail de backup ───────────────────────────────────
function enviar_backup_email(string $email_destino, string $assunto, string $corpo, array $anexos = []): bool {
    $boundary = md5(uniqid());
    $headers  = "From: sistema@drywallperformance.com.br\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

    $msg  = "--{$boundary}\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $msg .= $corpo . "\r\n";

    foreach ($anexos as $nome => $conteudo) {
        $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $contentType = match ($ext) {
            'sql' => 'application/sql',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: {$contentType}; name=\"{$nome}\"\r\n";
        $msg .= "Content-Disposition: attachment; filename=\"{$nome}\"\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($conteudo)) . "\r\n";
    }
    $msg .= "--{$boundary}--";

    return mail($email_destino, $assunto, $msg, $headers);
}

// ── Resposta JSON para API ───────────────────────────────────
function deletar_db(string $tabela, array $where): bool {
    global $pdo;
    $tabela = validar_tabela($tabela);
    $conditions = [];
    $values = [];
    foreach ($where as $col => $val) {
        $conditions[] = "$col = ?";
        $values[] = $val;
    }
    $stmt = $pdo->prepare("DELETE FROM $tabela WHERE " . implode(" AND ", $conditions));
    return $stmt->execute($values);
}

function resposta_json(bool $ok, string $mensagem, array $dados = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'       => $ok,
        'mensagem' => $mensagem,
        'dados'    => $dados,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
