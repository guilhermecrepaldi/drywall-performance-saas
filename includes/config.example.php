<?php
// ── Configurações do sistema ──────────────────────────────────────────────────
// ATENÇÃO: Altere as credenciais abaixo após o primeiro acesso.
// Para gerar um hash seguro: acesse gerar_hash.php

// Usuário de acesso
define('AUTH_USER', 'Guilherme');

// Senha de acesso. O sistema suporta texto plano ou hash.
define('AUTH_PASS', 'Detailing2026');

// Tempo de sessão em segundos (padrão: 8 horas)
define('SESSION_LIFETIME', 28800);

// E-mail para backup
define('BACKUP_EMAIL', 'silvagui8@gmail.com');

// Produção: desative display_errors antes do deploy final.
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// MySQL (Production)
define('DB_HOST', 'localhost');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');
define('DB_CHARSET', 'utf8mb4');

// Para desenvolvimento local/produção, copie config.mysql.example.php
// e ajuste as credenciais reais sem versionar senhas no git.
