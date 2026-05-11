<?php
require_once 'includes/auth.php';
auth_required();
require_once 'includes/helpers.php';
$page_title = 'Banco de Fotos (Geral)';
$active_nav = 'fotos';

global $pdo;
$stmt = $pdo->query("SELECT a.*, o.codigo as os_codigo, o.cliente_nome 
                     FROM anexos a 
                     JOIN os o ON a.os_id = o.id 
                     ORDER BY a.criado_em DESC");
$fotos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/head.php';
?>

<style>
.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.photo-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: 12px; overflow: hidden; position: relative; transition: transform 0.2s; }
.photo-card:hover { transform: translateY(-5px); }
.photo-img { height: 200px; background: #000; overflow: hidden; cursor: pointer; }
.photo-img img, .photo-img video { width: 100%; height: 100%; object-fit: cover; }
.photo-meta { padding: 12px; }
.photo-tag { position: absolute; top: 10px; left: 10px; background: rgba(192, 57, 43, 0.9); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.photo-os { position: absolute; top: 10px; right: 10px; background: rgba(13, 27, 42, 0.8); color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-family: monospace; }
</style>

<div class="page-shell">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Banco de Fotos Centralizado</h1>
            <p class="page-date">Portfólio de todos os serviços realizados</p>
        </div>
        <div id="filtros-fotos" style="display:flex; gap:8px;">
            <button class="btn btn-ghost btn-sm active" onclick="filterPhotos('todos', this)">TUDO</button>
            <button class="btn btn-ghost btn-sm" onclick="filterPhotos('entrada', this)">ENTRADA</button>
            <button class="btn btn-ghost btn-sm" onclick="filterPhotos('entrega', this)">ENTREGA</button>
            <button class="btn btn-ghost btn-sm" onclick="filterPhotos('motor', this)">MOTOR</button>
        </div>
    </div>

    <div class="photo-grid" id="main-photo-grid">
        <?php foreach ($fotos as $f): ?>
        <div class="photo-card" data-cat="<?= $f['categoria'] ?>">
            <span class="photo-tag"><?= htmlspecialchars($f['categoria']) ?></span>
            <span class="photo-os"><?= htmlspecialchars($f['os_codigo']) ?></span>
            <div class="photo-img" onclick="window.open('uploads/anexos/<?= $f['arquivo'] ?>', '_blank')">
                <?php if (str_starts_with($f['mime_type'], 'video/')): ?>
                    <video src="uploads/anexos/<?= $f['arquivo'] ?>"></video>
                <?php else: ?>
                    <img src="uploads/anexos/<?= $f['arquivo'] ?>" alt="Foto">
                <?php endif; ?>
            </div>
            <div class="photo-meta">
                <div style="font-size:12px; font-weight:700; color:var(--navy)"><?= htmlspecialchars($f['cliente_nome']) ?></div>
                <div style="font-size:11px; color:var(--muted)"><?= htmlspecialchars($f['legenda'] ?: 'Sem descrição') ?></div>
                <div style="font-size:10px; margin-top:8px; color:var(--muted)"><?= date('d/m/Y', strtotime($f['criado_em'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function filterPhotos(cat, btn) {
    document.querySelectorAll('#filtros-fotos .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.photo-card').forEach(card => {
        if (cat === 'todos' || card.dataset.cat === cat) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php include 'includes/foot.php'; ?>
