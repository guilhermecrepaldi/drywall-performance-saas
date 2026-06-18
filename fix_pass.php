<?php
// Script de reset de senha — use LOCALMENTE, NUNCA no servidor.
// Gera um hash bcrypt para o usuario admin.
// ATENCAO: apague este arquivo apos o uso.
$pass = 'SENHA_TEMPORARIA';  // troque antes de rodar
$hash = password_hash($pass, PASSWORD_BCRYPT);
$file = __DIR__ . '/includes/config.php';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $content = preg_replace("/define\('AUTH_PASS', '.*'\);/", "define('AUTH_PASS', '$hash');", $content);
    file_put_contents($file, $content);
    echo "<h1>✅ Senha atualizada!</h1>";
    echo "<p>Apague este arquivo imediatamente por seguranca.</p>";
} else {
    echo "Erro: Arquivo config.php nao encontrado.";
}
// ATENCAO: Descomente a linha abaixo para auto-destruir este arquivo:
// unlink(__FILE__);

?>
