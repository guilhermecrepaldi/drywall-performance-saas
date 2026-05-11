<?php
// includes/config_functions.php — configurações da empresa e backup MySQL.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

if (!function_exists('verificar_autenticacao')) {
    function verificar_autenticacao(): string {
        auth_required();
        return auth_usuario() ?: AUTH_USER;
    }
}

function config_usuario_chave(string $usuario_id): string {
    return 'empresa_config_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $usuario_id);
}

function config_dirs_garantir(): void {
    foreach ([__DIR__ . '/../uploads', __DIR__ . '/../backups'] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function obter_configuracoes(): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $chave = config_usuario_chave($usuario_id);

    $stmt = $pdo->prepare('SELECT * FROM configuracoes WHERE chave = ? OR (chave = ? AND usuario_id = ?) ORDER BY CASE WHEN chave = ? THEN 0 ELSE 1 END LIMIT 1');
    $stmt->execute([$chave, 'empresa_config', $usuario_id, $chave]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        $stmtInsert = $pdo->prepare("INSERT INTO configuracoes
            (chave, valor, usuario_id, nome, telefone, email, cnpj, endereco, margem_padrao, texto_padrao_os, assinatura_pdf, logo_url, atualizado_em)
            VALUES (?, '', ?, 'Drywall Performance', '', '', '', '', 25, '', '', '', ?)");
        $stmtInsert->execute([$chave, $usuario_id, date('Y-m-d H:i:s')]);
        $stmt->execute([$chave, 'empresa_config', $usuario_id, $chave]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return ['sucesso' => true, 'config' => $config ?: []];
}

function config_upload_logo(array $arquivo, string $logo_atual = ''): array {
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['sucesso' => true, 'logo_url' => $logo_atual];
    }
    if (($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['sucesso' => false, 'mensagem' => 'Erro no upload da logo.'];
    }
    if (($arquivo['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['sucesso' => false, 'mensagem' => 'A logo deve ter no máximo 2MB.'];
    }

    $ext = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        return ['sucesso' => false, 'mensagem' => 'Logo deve ser JPG ou PNG.'];
    }

    config_dirs_garantir();
    $nome = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $destino = __DIR__ . '/../uploads/' . $nome;
    if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível salvar a logo.'];
    }

    return ['sucesso' => true, 'logo_url' => 'uploads/' . $nome];
}

function atualizar_configuracoes($dados): array {
    global $pdo;
    $usuario_id = verificar_autenticacao();
    $atual = obter_configuracoes();
    $config_atual = $atual['config'] ?? [];
    $margem = (float)($dados['margem_padrao'] ?? 0);

    if (trim((string)($dados['nome'] ?? '')) === '') {
        return ['sucesso' => false, 'mensagem' => 'Nome da empresa é obrigatório.'];
    }
    if (!empty($dados['email']) && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
        return ['sucesso' => false, 'mensagem' => 'E-mail inválido.'];
    }
    if ($margem < 0 || $margem > 100) {
        return ['sucesso' => false, 'mensagem' => 'Margem padrão deve estar entre 0 e 100.'];
    }

    $logo = config_upload_logo($_FILES['logo'] ?? ['error' => UPLOAD_ERR_NO_FILE], $config_atual['logo_url'] ?? '');
    if (!$logo['sucesso']) {
        return $logo;
    }

    $chave = config_usuario_chave($usuario_id);
    $stmt = $pdo->prepare("INSERT INTO configuracoes
        (chave, valor, usuario_id, nome, telefone, email, cnpj, endereco, margem_padrao, texto_padrao_os, assinatura_pdf, logo_url, atualizado_em)
        VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            usuario_id=VALUES(usuario_id),
            nome=VALUES(nome),
            telefone=VALUES(telefone),
            email=VALUES(email),
            cnpj=VALUES(cnpj),
            endereco=VALUES(endereco),
            margem_padrao=VALUES(margem_padrao),
            texto_padrao_os=VALUES(texto_padrao_os),
            assinatura_pdf=VALUES(assinatura_pdf),
            logo_url=VALUES(logo_url),
            atualizado_em=VALUES(atualizado_em)");
    $ok = $stmt->execute([
        $chave,
        $usuario_id,
        trim((string)$dados['nome']),
        trim((string)($dados['telefone'] ?? '')),
        trim((string)($dados['email'] ?? '')),
        trim((string)($dados['cnpj'] ?? '')),
        trim((string)($dados['endereco'] ?? '')),
        $margem,
        trim((string)($dados['texto_padrao_os'] ?? '')),
        trim((string)($dados['assinatura_pdf'] ?? '')),
        $logo['logo_url'],
        date('Y-m-d H:i:s'),
    ]);

    return ['sucesso' => $ok, 'mensagem' => $ok ? 'Configurações salvas.' : 'Erro ao salvar configurações.', 'logo_url' => $logo['logo_url']];
}

function validar_tabela_backup(string $tabela): string {
    $permitidas = ['usuarios', 'clientes', 'os', 'precos', 'agenda', 'financeiro', 'followups', 'configuracoes', 'anexos', 'produtos', 'fornecedores', 'produto_fornecedor_precos', 'desenvolvimento'];
    if (!in_array($tabela, $permitidas, true)) {
        throw new InvalidArgumentException('Tabela inválida para backup.');
    }
    return $tabela;
}

function mysql_quote_value(PDO $db, mixed $valor): string {
    if ($valor === null) {
        return 'NULL';
    }
    if (is_int($valor) || is_float($valor)) {
        return (string)$valor;
    }
    return $db->quote((string)$valor);
}

function gerar_dump_mysql(): string {
    global $pdo;
    $tabelas = ['usuarios', 'clientes', 'os', 'precos', 'agenda', 'financeiro', 'followups', 'configuracoes', 'anexos', 'produtos', 'fornecedores', 'produto_fornecedor_precos', 'desenvolvimento'];
    $dump = "-- Backup MySQL Drywall CRM\n";
    $dump .= "-- Gerado em " . date('Y-m-d H:i:s') . "\n\n";
    $dump .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tabelas as $tabela) {
        $tabela = validar_tabela_backup($tabela);
        $stmt = $pdo->query("SELECT * FROM `{$tabela}`");
        $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$linhas) {
            continue;
        }

        $dump .= "DELETE FROM `{$tabela}`;\n";
        foreach ($linhas as $linha) {
            $cols = array_map(fn($col) => '`' . str_replace('`', '``', $col) . '`', array_keys($linha));
            $vals = array_map(fn($valor) => mysql_quote_value($pdo, $valor), array_values($linha));
            $dump .= "INSERT INTO `{$tabela}` (" . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ");\n";
        }
        $dump .= "\n";
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

function fazer_backup_mysql(): array {
    verificar_autenticacao();
    config_dirs_garantir();
    $nome = 'backup_' . date('Ymd_His') . '.sql';
    $destino = __DIR__ . '/../backups/' . $nome;
    if (file_put_contents($destino, gerar_dump_mysql()) === false) {
        return ['sucesso' => false, 'mensagem' => 'Erro ao gerar backup.'];
    }
    return ['sucesso' => true, 'arquivo' => $nome, 'url' => 'api/config_api.php?acao=download_backup&arquivo=' . rawurlencode($nome)];
}

function listar_backups_mysql(): array {
    verificar_autenticacao();
    config_dirs_garantir();
    $arquivos = glob(__DIR__ . '/../backups/backup_*.sql') ?: [];
    rsort($arquivos);
    return array_map(fn($path) => [
        'arquivo' => basename($path),
        'tamanho_kb' => round(filesize($path) / 1024, 1),
        'data' => date('d/m/Y H:i', filemtime($path)),
    ], $arquivos);
}
