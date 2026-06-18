<?php
// includes/database_setup.php — bootstrap leve para MySQL.
// Importe converter_mysql.sql via phpMyAdmin/cPanel antes de subir o sistema.

function database_setup_insert_ignore(PDO $db, string $sql, array $params = []): void {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        // Ignora erros de duplicata (MySQL: 1062, SQLite: 19/2067)
        $code = $e->getCode();
        if ($code == '23000' || stripos($msg, 'Duplicate') !== false || stripos($msg, 'UNIQUE') !== false) {
            return;
        }
        throw $e;
    }
}

function database_setup_column_exists(PDO $db, string $table, string $column): bool {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $db->query("PRAGMA table_info(`$table`)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $c) {
            if ($c['name'] === $column) return true;
        }
        return false;
    }
    // MySQL
    $stmt = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ' . $db->quote($column));
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function database_setup_add_column(PDO $db, string $table, string $column, string $definition): void {
    if (!database_setup_column_exists($db, $table, $column)) {
        $db->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` ADD COLUMN `' . str_replace('`', '``', $column) . '` ' . $definition);
    }
}

function database_setup_exec_safe(PDO $db, string $sql): void {
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        error_log('database_setup aviso: ' . $e->getMessage());
    }
}

function database_setup_run(?PDO $db = null): void {
    $db = $db ?: conectar_db();

    database_setup_add_column($db, 'clientes', 'foto_url', 'VARCHAR(255) NULL');
    database_setup_add_column($db, 'clientes', 'origem_lead', 'VARCHAR(50) NULL');
    database_setup_add_column($db, 'os', 'nota_fiscal_numero', 'VARCHAR(80) NULL');
    database_setup_add_column($db, 'os', 'nota_fiscal_valor', 'DECIMAL(12,2) NULL');
    database_setup_add_column($db, 'os', 'nota_fiscal_data', 'DATE NULL');
    database_setup_add_column($db, 'os', 'nota_fiscal_status', "VARCHAR(40) NULL DEFAULT 'pendente'");
    database_setup_add_column($db, 'os', 'comentario_cliente', 'TEXT NULL');
    database_setup_add_column($db, 'os', 'comentario_interno', 'TEXT NULL');
    database_setup_add_column($db, 'os', 'fornecedor_id', 'INT UNSIGNED NULL');
    
    /* Automotive Extensions */
    database_setup_add_column($db, 'os', 'veiculo_placa', 'VARCHAR(20) NULL');
    database_setup_add_column($db, 'os', 'veiculo_modelo', 'VARCHAR(120) NULL');
    database_setup_add_column($db, 'os', 'veiculo_cor', 'VARCHAR(50) NULL');
    database_setup_add_column($db, 'os', 'veiculo_km', 'VARCHAR(50) NULL');
    database_setup_add_column($db, 'os', 'checklist', 'LONGTEXT NULL');

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    $createUsuarios = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            usuario TEXT NOT NULL UNIQUE,
            senha TEXT NOT NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS usuarios (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            usuario VARCHAR(100) NOT NULL,
            senha VARCHAR(255) NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_usuarios_email (email),
            UNIQUE KEY uq_usuarios_user (usuario)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    database_setup_exec_safe($db, $createUsuarios);

    // --- CLIENTES ---
    $createClientes = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id TEXT NULL,
            nome TEXT NOT NULL,
            cpf_cnpj TEXT NULL,
            telefone TEXT NULL,
            email TEXT NULL,
            tipo TEXT NULL,
            endereco TEXT NULL,
            bairro TEXT NULL,
            cidade TEXT NULL,
            cep TEXT NULL,
            obs TEXT NULL,
            status TEXT NOT NULL DEFAULT 'prospecto',
            origem_lead TEXT NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS clientes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id VARCHAR(100) NULL,
            nome VARCHAR(255) NOT NULL,
            cpf_cnpj VARCHAR(30) NULL,
            telefone VARCHAR(30) NULL,
            email VARCHAR(255) NULL,
            tipo ENUM('PF','PJ') NULL,
            endereco TEXT NULL,
            bairro VARCHAR(120) NULL,
            cidade VARCHAR(120) NULL,
            cep VARCHAR(20) NULL,
            obs TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'prospecto',
            origem_lead VARCHAR(50) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createClientes);

    // --- OS ---
    $createOS = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS os (
            id TEXT PRIMARY KEY,
            codigo TEXT UNIQUE NULL,
            usuario_id TEXT NULL,
            cliente_id INTEGER NULL,
            cliente_nome TEXT NULL,
            cliente_cpf TEXT NULL,
            cliente_tel TEXT NULL,
            cliente_end TEXT NULL,
            cliente_bairro TEXT NULL,
            cliente_cidade TEXT NULL,
            obra_tipo TEXT NULL,
            obra_segmento TEXT NULL,
            segmento_cod TEXT NULL,
            obra_prazo TEXT NULL,
            obra_inicio TEXT NULL,
            obra_pe_dir TEXT NULL,
            obra_acesso TEXT NULL,
            itens TEXT NULL,
            subtotal NUMERIC NOT NULL DEFAULT 0,
            desconto NUMERIC NOT NULL DEFAULT 0,
            total_geral NUMERIC NOT NULL DEFAULT 0,
            incluso TEXT NULL,
            nao_incluso TEXT NULL,
            pagto_forma TEXT NULL,
            pagto_entrada TEXT NULL,
            pagto_saldo TEXT NULL,
            pagto_data TEXT NULL,
            pagto_obs TEXT NULL,
            nota_fiscal INTEGER NOT NULL DEFAULT 0,
            obs_tecnicas TEXT NULL,
            status TEXT NOT NULL DEFAULT 'rascunho',
            emissao TEXT NULL,
            validade TEXT NULL,
            token_aprovacao TEXT UNIQUE NULL,
            data_aprovacao TEXT NULL,
            aprovado_nome TEXT NULL,
            aprovado_telefone TEXT NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS os (
            id VARCHAR(80) NOT NULL,
            codigo VARCHAR(80) NULL,
            usuario_id VARCHAR(100) NULL,
            cliente_id INT UNSIGNED NULL,
            cliente_nome VARCHAR(255) NULL,
            cliente_cpf VARCHAR(30) NULL,
            cliente_tel VARCHAR(30) NULL,
            cliente_end TEXT NULL,
            cliente_bairro VARCHAR(120) NULL,
            cliente_cidade VARCHAR(120) NULL,
            obra_tipo VARCHAR(120) NULL,
            obra_segmento VARCHAR(120) NULL,
            segmento_cod VARCHAR(20) NULL,
            obra_prazo VARCHAR(120) NULL,
            obra_inicio VARCHAR(120) NULL,
            obra_pe_dir VARCHAR(80) NULL,
            obra_acesso TEXT NULL,
            itens LONGTEXT NULL,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            desconto DECIMAL(12,2) NOT NULL DEFAULT 0,
            total_geral DECIMAL(12,2) NOT NULL DEFAULT 0,
            incluso LONGTEXT NULL,
            nao_incluso LONGTEXT NULL,
            pagto_forma VARCHAR(120) NULL,
            pagto_entrada VARCHAR(120) NULL,
            pagto_saldo VARCHAR(120) NULL,
            pagto_data DATE NULL,
            pagto_obs TEXT NULL,
            nota_fiscal TINYINT(1) NOT NULL DEFAULT 0,
            obs_tecnicas TEXT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'rascunho',
            emissao DATE NULL,
            validade DATE NULL,
            token_aprovacao VARCHAR(255) NULL,
            data_aprovacao DATETIME NULL,
            aprovado_nome VARCHAR(255) NULL,
            aprovado_telefone VARCHAR(30) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createOS);

    // --- PRECOS ---
    $createPrecos = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS precos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria TEXT NULL,
            produto TEXT NULL,
            unidade TEXT NULL,
            area NUMERIC NULL,
            preco NUMERIC NULL,
            custo NUMERIC NULL,
            perda NUMERIC NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS precos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            categoria VARCHAR(120) NULL,
            produto VARCHAR(255) NULL,
            unidade VARCHAR(30) NULL,
            area DECIMAL(12,2) NULL,
            preco DECIMAL(12,2) NULL,
            custo DECIMAL(12,2) NULL,
            perda DECIMAL(8,2) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createPrecos);

    // --- AGENDA ---
    $createAgenda = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS agenda (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NULL,
            os_id TEXT NULL,
            titulo TEXT NOT NULL,
            descricao TEXT NULL,
            data_inicio TEXT NOT NULL,
            data_fim TEXT NULL,
            status TEXT NOT NULL DEFAULT 'agendado',
            tipo TEXT NOT NULL DEFAULT 'visita',
            usuario_id TEXT NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS agenda (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cliente_id INT UNSIGNED NULL,
            os_id VARCHAR(80) NULL,
            titulo VARCHAR(255) NOT NULL,
            descricao TEXT NULL,
            data_inicio DATETIME NOT NULL,
            data_fim DATETIME NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'agendado',
            tipo VARCHAR(50) NOT NULL DEFAULT 'visita',
            usuario_id VARCHAR(100) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createAgenda);

    // --- FINANCEIRO ---
    $createFinanceiro = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS financeiro (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            os_id TEXT NOT NULL UNIQUE,
            custo_material NUMERIC NOT NULL DEFAULT 0,
            custo_mao_obra NUMERIC NOT NULL DEFAULT 0,
            horas_mao_obra NUMERIC NOT NULL DEFAULT 0,
            valor_hora NUMERIC NOT NULL DEFAULT 0,
            overhead NUMERIC NOT NULL DEFAULT 0,
            margem NUMERIC NOT NULL DEFAULT 0,
            custo_real NUMERIC NULL,
            valor_sugerido NUMERIC NOT NULL DEFAULT 0,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS financeiro (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            os_id VARCHAR(80) NOT NULL,
            custo_material DECIMAL(12,2) NOT NULL DEFAULT 0,
            custo_mao_obra DECIMAL(12,2) NOT NULL DEFAULT 0,
            horas_mao_obra DECIMAL(10,2) NOT NULL DEFAULT 0,
            valor_hora DECIMAL(12,2) NOT NULL DEFAULT 0,
            overhead DECIMAL(8,2) NOT NULL DEFAULT 0,
            margem DECIMAL(8,2) NOT NULL DEFAULT 0,
            custo_real DECIMAL(12,2) NULL,
            valor_sugerido DECIMAL(12,2) NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_financeiro_os (os_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createFinanceiro);

    // --- FOLLOWUPS ---
    $createFollowups = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS followups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            descricao TEXT NOT NULL,
            data_lembrete TEXT NULL,
            concluido INTEGER NOT NULL DEFAULT 0,
            usuario_id TEXT NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS followups (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            cliente_id INT UNSIGNED NOT NULL,
            descricao TEXT NOT NULL,
            data_lembrete DATETIME NULL,
            concluido TINYINT(1) NOT NULL DEFAULT 0,
            usuario_id VARCHAR(100) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createFollowups);

    // --- CONFIGURACOES ---
    $createConfig = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS configuracoes (
            chave TEXT PRIMARY KEY,
            valor TEXT NULL,
            usuario_id TEXT NULL,
            nome TEXT NULL,
            telefone TEXT NULL,
            email TEXT NULL,
            cnpj TEXT NULL,
            endereco TEXT NULL,
            margem_padrao NUMERIC NULL,
            texto_padrao_os TEXT NULL,
            assinatura_pdf TEXT NULL,
            logo_url TEXT NULL,
            atualizado_em TEXT NULL
        )"
        : "CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(120) NOT NULL,
            valor TEXT NULL,
            usuario_id VARCHAR(100) NULL,
            nome VARCHAR(255) NULL,
            telefone VARCHAR(30) NULL,
            email VARCHAR(255) NULL,
            cnpj VARCHAR(30) NULL,
            endereco TEXT NULL,
            margem_padrao DECIMAL(8,2) NULL,
            texto_padrao_os TEXT NULL,
            assinatura_pdf TEXT NULL,
            logo_url VARCHAR(255) NULL,
            atualizado_em DATETIME NULL,
            PRIMARY KEY (chave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createConfig);

    // --- ANEXOS ---
    $createAnexos = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS anexos (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            os_id       TEXT NOT NULL,
            categoria   TEXT NOT NULL DEFAULT 'outro',
            arquivo     TEXT NOT NULL,
            mime_type   TEXT NULL,
            tamanho     INTEGER NULL,
            largura     INTEGER NULL,
            altura      INTEGER NULL,
            legenda     TEXT NULL,
            criado_em   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS anexos (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            os_id       VARCHAR(80)  NOT NULL,
            categoria   ENUM('pagamento','antes','depois','obra','outro') NOT NULL DEFAULT 'outro',
            arquivo     VARCHAR(255) NOT NULL,
            mime_type   VARCHAR(80)  NULL,
            tamanho     INT UNSIGNED NULL,
            largura     SMALLINT UNSIGNED NULL,
            altura      SMALLINT UNSIGNED NULL,
            legenda     VARCHAR(255) NULL,
            criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    database_setup_exec_safe($db, $createAnexos);

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $nowSql = ($driver === 'sqlite') ? "datetime('now')" : "NOW()";

    $createDesenv = ($driver === 'sqlite') 
        ? "CREATE TABLE IF NOT EXISTS desenvolvimento (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            empresa TEXT NOT NULL,
            contato TEXT NULL,
            telefone TEXT NULL,
            email TEXT NULL,
            origem TEXT NULL,
            segmento TEXT NULL,
            etapa TEXT NOT NULL DEFAULT 'lead',
            status TEXT NOT NULL DEFAULT 'novo',
            prioridade TEXT NOT NULL DEFAULT 'media',
            ultimo_contato TEXT NULL,
            proximo_retorno TEXT NULL,
            interesse TEXT NULL,
            ultimo_resultado TEXT NULL,
            proxima_acao TEXT NULL,
            observacoes TEXT NULL,
            cliente_id INTEGER NULL,
            agenda_id INTEGER NULL,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS desenvolvimento (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            empresa VARCHAR(255) NOT NULL,
            contato VARCHAR(120) NULL,
            telefone VARCHAR(30) NULL,
            email VARCHAR(255) NULL,
            origem VARCHAR(80) NULL,
            segmento VARCHAR(120) NULL,
            etapa VARCHAR(40) NOT NULL DEFAULT 'lead',
            status VARCHAR(40) NOT NULL DEFAULT 'novo',
            prioridade VARCHAR(20) NOT NULL DEFAULT 'media',
            ultimo_contato DATETIME NULL,
            proximo_retorno DATETIME NULL,
            interesse TEXT NULL,
            ultimo_resultado TEXT NULL,
            proxima_acao TEXT NULL,
            observacoes TEXT NULL,
            cliente_id INT UNSIGNED NULL,
            agenda_id INT UNSIGNED NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    database_setup_exec_safe($db, $createDesenv);

    $createProdutos = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS produtos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria TEXT NULL,
            nome TEXT NOT NULL,
            unidade TEXT NULL,
            custo_padrao NUMERIC NOT NULL DEFAULT 0,
            preco_venda NUMERIC NOT NULL DEFAULT 0,
            unidade_servico TEXT NULL,
            consumo_por_unidade NUMERIC NOT NULL DEFAULT 0,
            consumo_perda_percentual NUMERIC NOT NULL DEFAULT 0,
            consumo_referencia TEXT NULL,
            protocolo_operacao TEXT NULL,
            descricao TEXT NULL,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS produtos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            categoria VARCHAR(120) NULL,
            nome VARCHAR(255) NOT NULL,
            unidade VARCHAR(30) NULL,
            custo_padrao DECIMAL(12,2) NOT NULL DEFAULT 0,
            preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0,
            unidade_servico VARCHAR(30) NULL,
            consumo_por_unidade DECIMAL(12,4) NOT NULL DEFAULT 0,
            consumo_perda_percentual DECIMAL(8,2) NOT NULL DEFAULT 0,
            consumo_referencia VARCHAR(255) NULL,
            protocolo_operacao TEXT NULL,
            descricao TEXT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    database_setup_exec_safe($db, $createProdutos);

    database_setup_add_column($db, 'produtos', 'custo_padrao', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
    database_setup_add_column($db, 'produtos', 'preco_venda', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
    database_setup_add_column($db, 'produtos', 'unidade_servico', 'VARCHAR(30) NULL');
    database_setup_add_column($db, 'produtos', 'consumo_por_unidade', 'DECIMAL(12,4) NOT NULL DEFAULT 0');
    database_setup_add_column($db, 'produtos', 'consumo_perda_percentual', 'DECIMAL(8,2) NOT NULL DEFAULT 0');
    database_setup_add_column($db, 'produtos', 'consumo_referencia', 'VARCHAR(255) NULL');
    database_setup_add_column($db, 'produtos', 'protocolo_operacao', 'TEXT NULL');

    $createFornecedores = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS fornecedores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            contato TEXT NULL,
            telefone TEXT NULL,
            email TEXT NULL,
            endereco TEXT NULL,
            obs TEXT NULL,
            checklist TEXT NULL,
            nota_geral NUMERIC NULL,
            ativo INTEGER NOT NULL DEFAULT 1,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )"
        : "CREATE TABLE IF NOT EXISTS fornecedores (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(255) NOT NULL,
            contato VARCHAR(120) NULL,
            telefone VARCHAR(30) NULL,
            email VARCHAR(255) NULL,
            endereco TEXT NULL,
            obs TEXT NULL,
            checklist LONGTEXT NULL,
            nota_geral DECIMAL(4,2) NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    database_setup_exec_safe($db, $createFornecedores);

    $createPFP = ($driver === 'sqlite')
        ? "CREATE TABLE IF NOT EXISTS produto_fornecedor_precos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            produto_id INTEGER NOT NULL,
            fornecedor_id INTEGER NOT NULL,
            preco_pago NUMERIC NOT NULL DEFAULT 0,
            unidade_compra TEXT NULL,
            observacao TEXT NULL,
            atualizado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            criado_em TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(produto_id, fornecedor_id),
            FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
            FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE
        )"
        : "CREATE TABLE IF NOT EXISTS produto_fornecedor_precos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            produto_id INT UNSIGNED NOT NULL,
            fornecedor_id INT UNSIGNED NOT NULL,
            preco_pago DECIMAL(12,2) NOT NULL DEFAULT 0,
            unidade_compra VARCHAR(30) NULL,
            observacao TEXT NULL,
            atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_produto_fornecedor (produto_id, fornecedor_id),
            KEY idx_pfp_produto (produto_id),
            KEY idx_pfp_fornecedor (fornecedor_id),
            CONSTRAINT fk_pfp_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
            CONSTRAINT fk_pfp_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    database_setup_exec_safe($db, $createPFP);

    database_setup_insert_ignore(
        $db,
        'INSERT INTO usuarios (email, usuario, senha) VALUES (?, ?, ?)',
        ['contato@premiumdetailing.com.br', AUTH_USER, AUTH_PASS]
    );

    $defaults = [
        'empresa_nome' => 'Premium Detailing',
        'empresa_cnpj' => '',
        'empresa_endereco' => 'Av. Automotiva, 1000 - São Paulo/SP',
        'empresa_telefone' => '(11) 91359-5985',
        'empresa_email' => 'contato@premiumdetailing.com.br',
        'empresa_logo' => '',
        'pagamento_padrao' => 'Entrada de 50% no check-in e saldo na entrega do veículo.',
        'validade_padrao_dias' => '7',
        'valor_hora_mao_obra' => '120',
        'overhead_padrao_percentual' => '20',
        'margem_padrao_percentual' => '35',
    ];

    foreach ($defaults as $chave => $valor) {
        database_setup_insert_ignore(
            $db,
            'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)',
            [$chave, $valor]
        );
    }

    database_setup_insert_ignore(
        $db,
        "INSERT INTO configuracoes
            (chave, valor, usuario_id, nome, telefone, email, cnpj, endereco, margem_padrao, texto_padrao_os, assinatura_pdf, logo_url, atualizado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, $nowSql)",
        [
            'empresa_config',
            '',
            AUTH_USER,
            $defaults['empresa_nome'],
            $defaults['empresa_telefone'],
            $defaults['empresa_email'],
            $defaults['empresa_cnpj'],
            $defaults['empresa_endereco'],
            (float)$defaults['margem_padrao_percentual'],
            'Orçamento válido conforme prazo informado. Serviços executados conforme escopo aprovado.',
            'Guilherme Crepaldi - Premium Detailing',
            '',
        ]
    );

    $stmtCountProdutos = $db->query('SELECT COUNT(*) FROM produtos');
    if ((int)$stmtCountProdutos->fetchColumn() === 0) {
        $produtos = [
            ['Polimento', 'Composto de Corte Pesado', 'L'],
            ['Polimento', 'Composto de Refino', 'L'],
            ['Polimento', 'Lustrador de Alto Brilho', 'L'],
            ['Proteção', 'Vitrificador de Pintura 9H', 'kit'],
            ['Proteção', 'Selante Sintético Longa Duração', 'un'],
            ['Proteção', 'Cera de Carnaúba Premium', 'un'],
            ['Limpeza', 'Shampoo Neutro Detailing (Concentrado)', 'L'],
            ['Limpeza', 'Desengraxante de Motor / APC', 'L'],
            ['Limpeza', 'Limpador de Couro e Plásticos', 'L'],
            ['Acessórios', 'Boina de Lã de Carneiro', 'un'],
            ['Acessórios', 'Boina de Espuma Média/Macia', 'un'],
            ['Acessórios', 'Pano de Microfibra 400 GSM', 'un'],
            ['Acessórios', 'Toalha de Secagem Twist', 'un'],
            ['Vidros', 'Cristalizador de Vidros / Repelente', 'un'],
            ['Rodas', 'Limpa Rodas e Ferroso', 'L'],
        ];
        $stmtProduto = $db->prepare('INSERT INTO produtos (categoria, nome, unidade) VALUES (?, ?, ?)');
        foreach ($produtos as $produto) {
            $stmtProduto->execute($produto);
        }
    }
}
