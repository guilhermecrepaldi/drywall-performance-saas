<?php
$pass = '@Drywall2026';
$hash = password_hash($pass, PASSWORD_BCRYPT);
$file = __DIR__ . '/includes/config.php';
if (file_exists($file)) {
    $content = file_get_contents($file);
    $content = preg_replace("/define\('AUTH_PASS', '.*'\);/", "define('AUTH_PASS', '$hash');", $content);
    file_put_contents($file, $content);
    echo "<h1>✅ Sucesso!</h1>";
    echo "<p>A senha do usuário <strong>Guilherme</strong> foi atualizada para <strong>@Drywall2026</strong>.</p>";
    echo "<p>Este arquivo será excluído por segurança.</p>";
} else {
    echo "Erro: Arquivo config.php não encontrado.";
}
// unlink(__FILE__); // Comentei para você ver o sucesso antes de deletar
?>
