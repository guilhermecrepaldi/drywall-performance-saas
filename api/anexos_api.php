<?php
// api/anexos_api.php — Upload, listagem e exclusão de anexos da OS
// Fotos: max 10MB | Vídeos: max 50MB
// Compressão server-side com GD (JPEG → 80%, redimensiona se >2000px)

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function anexos_responder(array $r, int $status = 200): void {
    http_response_code($status);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

define('ANEXOS_DIR',      __DIR__ . '/../uploads/anexos/');
define('ANEXOS_URL',      'uploads/anexos/');
define('ANEXOS_MAX_IMG',  10 * 1024 * 1024); // 10 MB
define('ANEXOS_MAX_VID',  50 * 1024 * 1024); // 50 MB
define('ANEXOS_QUALITY',  80);               // JPEG quality após compressão

const ANEXOS_MIMES_IMG = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const ANEXOS_MIMES_VID = ['video/mp4', 'video/quicktime', 'video/webm'];
const ANEXOS_MIMES_OK  = [...ANEXOS_MIMES_IMG, ...ANEXOS_MIMES_VID];
const ANEXOS_CATS      = ['pagamento', 'antes', 'depois', 'obra', 'outro'];

// ── Cria tabela e pasta ─────────────────────────────────────────
function anexos_setup(): void {
    global $pdo;
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

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
            os_id       VARCHAR(40)  NOT NULL,
            categoria   ENUM('pagamento','antes','depois','obra','outro') NOT NULL DEFAULT 'outro',
            arquivo     VARCHAR(255) NOT NULL,
            mime_type   VARCHAR(80)  NULL,
            tamanho     INT UNSIGNED NULL,
            largura     SMALLINT UNSIGNED NULL,
            altura      SMALLINT UNSIGNED NULL,
            legenda     VARCHAR(255) NULL,
            criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_anexos_os  (os_id),
            KEY idx_anexos_cat (categoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($createAnexos);

    if (!is_dir(ANEXOS_DIR)) {
        mkdir(ANEXOS_DIR, 0755, true);
        file_put_contents(ANEXOS_DIR . '.htaccess',
            "<FilesMatch \"\\.php$\">\n  Order Allow,Deny\n  Deny from all\n</FilesMatch>\n"
        );
    }
}

// ── Compressão com GD ───────────────────────────────────────────
function anexos_comprimir_imagem(string $tmp, string $mime): ?string {
    if (!extension_loaded('gd')) return null;

    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
        'image/gif'  => @imagecreatefromgif($tmp),
        default      => false,
    };
    if (!$img) return null;

    $w = imagesx($img);
    $h = imagesy($img);
    $max_px = 2000;
    if ($w > $max_px || $h > $max_px) {
        $ratio   = $w > $h ? ($max_px / $w) : ($max_px / $h);
        $nw      = (int)round($w * $ratio);
        $nh      = (int)round($h * $ratio);
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $destino = tempnam(sys_get_temp_dir(), 'dwl_');
    imagejpeg($img, $destino, ANEXOS_QUALITY);
    imagedestroy($img);
    return $destino;
}

// ── Listar ──────────────────────────────────────────────────────
function anexos_listar(string $os_id): array {
    global $pdo;
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    $orderSql = ($driver === 'sqlite')
        ? "CASE categoria WHEN 'antes' THEN 1 WHEN 'depois' THEN 2 WHEN 'obra' THEN 3 WHEN 'pagamento' THEN 4 ELSE 5 END, criado_em ASC"
        : "FIELD(categoria,'antes','depois','obra','pagamento','outro'), criado_em ASC";

    $stmt = $pdo->prepare(
        "SELECT id, os_id, categoria, arquivo, mime_type, tamanho, largura, altura, legenda, criado_em
         FROM anexos WHERE os_id = ? ORDER BY $orderSql"
    );
    $stmt->execute([$os_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['url']         = ANEXOS_URL . basename($r['arquivo']);
        $r['eh_video']    = str_starts_with($r['mime_type'] ?? '', 'video/');
        $r['tamanho_fmt'] = $r['tamanho'] ? number_format($r['tamanho'] / 1024, 0, ',', '.') . ' KB' : '';
    }
    return ['sucesso' => true, 'dados' => $rows];
}

// ── Upload ──────────────────────────────────────────────────────
function anexos_upload(string $os_id, string $categoria, string $legenda): array {
    if (!in_array($categoria, ANEXOS_CATS, true)) $categoria = 'outro';

    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE  => 'Arquivo excede o limite do servidor.',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o limite do formulário.',
            UPLOAD_ERR_PARTIAL   => 'Upload incompleto. Tente novamente.',
            UPLOAD_ERR_NO_FILE   => 'Nenhum arquivo enviado.',
        ];
        $code = $_FILES['arquivo']['error'] ?? -1;
        return ['sucesso' => false, 'mensagem' => $erros[$code] ?? 'Erro de upload.'];
    }

    $tmp  = $_FILES['arquivo']['tmp_name'];
    $size = $_FILES['arquivo']['size'];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);

    if (!in_array($mime, ANEXOS_MIMES_OK, true)) {
        return ['sucesso' => false, 'mensagem' => "Tipo não permitido: $mime. Use JPG, PNG, WEBP, MP4, MOV ou WEBM."];
    }

    $eh_video = str_starts_with($mime, 'video/');
    $max      = $eh_video ? ANEXOS_MAX_VID : ANEXOS_MAX_IMG;
    if ($size > $max) {
        return ['sucesso' => false, 'mensagem' => 'Arquivo muito grande. Limite: ' . ($max / 1024 / 1024) . 'MB.'];
    }

    $ext_map = [
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm',
    ];
    $ext = $ext_map[$mime] ?? 'bin';

    $arquivo_origem  = $tmp;
    $arquivo_temp_gd = null;
    if (!$eh_video) {
        $arquivo_temp_gd = anexos_comprimir_imagem($tmp, $mime);
        if ($arquivo_temp_gd) {
            $arquivo_origem = $arquivo_temp_gd;
            $size           = filesize($arquivo_temp_gd);
            $ext            = 'jpg';
        }
    }

    $largura = $altura = null;
    if (!$eh_video) {
        $info = @getimagesize($arquivo_origem);
        if ($info) { $largura = $info[0]; $altura = $info[1]; }
    }

    $nome_arquivo = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $destino      = ANEXOS_DIR . $nome_arquivo;

    $ok_move = $arquivo_temp_gd
        ? rename($arquivo_temp_gd, $destino)
        : move_uploaded_file($tmp, $destino);

    if (!$ok_move) {
        if ($arquivo_temp_gd && file_exists($arquivo_temp_gd)) @unlink($arquivo_temp_gd);
        return ['sucesso' => false, 'mensagem' => 'Falha ao gravar arquivo no servidor.'];
    }

    global $pdo;
    $mime_salvo = $eh_video ? $mime : 'image/jpeg';
    $stmt = $pdo->prepare(
        'INSERT INTO anexos (os_id,categoria,arquivo,mime_type,tamanho,largura,altura,legenda,criado_em)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $ok = $stmt->execute([
        $os_id, $categoria, $nome_arquivo, $mime_salvo,
        $size, $largura, $altura,
        substr(trim($legenda), 0, 255),
        date('Y-m-d H:i:s'),
    ]);

    if (!$ok) {
        @unlink($destino);
        return ['sucesso' => false, 'mensagem' => 'Erro ao registrar no banco.'];
    }

    return [
        'sucesso'  => true,
        'mensagem' => 'Arquivo salvo' . ($arquivo_temp_gd ? ' e comprimido.' : '.'),
        'dados'    => [
            'id'          => (int)$pdo->lastInsertId(),
            'arquivo'     => $nome_arquivo,
            'url'         => ANEXOS_URL . $nome_arquivo,
            'mime_type'   => $mime_salvo,
            'tamanho'     => $size,
            'largura'     => $largura,
            'altura'      => $altura,
            'categoria'   => $categoria,
            'eh_video'    => $eh_video,
            'tamanho_fmt' => number_format($size / 1024, 0, ',', '.') . ' KB',
        ],
    ];
}

// ── Deletar ─────────────────────────────────────────────────────
function anexos_deletar(int $id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT arquivo FROM anexos WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return ['sucesso' => false, 'mensagem' => 'Anexo não encontrado.'];
    $pdo->prepare('DELETE FROM anexos WHERE id = ?')->execute([$id]);
    $path = ANEXOS_DIR . basename($row['arquivo']);
    if (file_exists($path)) @unlink($path);
    return ['sucesso' => true, 'mensagem' => 'Removido.'];
}

// ── Roteamento ───────────────────────────────────────────────────
auth_required_api();
anexos_setup();

$method = $_SERVER['REQUEST_METHOD'];
$acao   = $_GET['acao'] ?? '';

try {
    if ($method === 'GET' && $acao === 'listar') {
        $os_id = trim($_GET['os_id'] ?? '');
        if ($os_id === '') anexos_responder(['sucesso' => false, 'mensagem' => 'os_id obrigatório.'], 400);
        anexos_responder(anexos_listar($os_id));
    }

    if ($method === 'POST') {
        if (!csrf_validate($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            anexos_responder(['sucesso' => false, 'mensagem' => 'Token CSRF inválido.'], 403);
        }
        if ($acao === 'upload') {
            $os_id = trim($_POST['os_id'] ?? '');
            if ($os_id === '') anexos_responder(['sucesso' => false, 'mensagem' => 'os_id obrigatório.'], 400);
            anexos_responder(anexos_upload($os_id, trim($_POST['categoria'] ?? 'outro'), $_POST['legenda'] ?? ''));
        }
        if ($acao === 'deletar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) anexos_responder(['sucesso' => false, 'mensagem' => 'ID inválido.'], 400);
            anexos_responder(anexos_deletar($id));
        }
    }

    anexos_responder(['sucesso' => false, 'mensagem' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    error_log('anexos_api: ' . $e->getMessage());
    anexos_responder(['sucesso' => false, 'mensagem' => 'Erro interno.'], 500);
}
