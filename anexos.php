<?php
// sistema/anexos.php — Interface de gerenciamento de fotos da OS
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

$os_id = trim($_GET['os_id'] ?? '');
if (!$os_id) {
    header('Location: os.php');
    exit;
}

// Busca dados da OS para o cabeçalho
$os_rows = ler_db('os', ['id' => $os_id]);
$os_data = $os_rows[0] ?? null;

if (!$os_data) {
    die('OS não encontrada.');
}

include 'includes/head.php';
?>

<div class="page-shell">
    <div class="page-heading">
        <div>
            <div class="breadcrumbs">
                <a href="os.php">Ordens de Serviço</a>
                <span class="bc-sep">/</span>
                <span>Fotos e Anexos</span>
            </div>
            <h1 class="page-title">Galeria da OS: <?= htmlspecialchars($os_data['codigo']) ?></h1>
            <p class="page-date">Cliente: <?= htmlspecialchars($os_data['cliente_nome']) ?></p>
        </div>
        <div>
            <a href="os.php" class="btn btn-outline">← Voltar para lista</a>
        </div>
    </div>

    <div class="page-content">
        <!-- ÁREA DE UPLOAD -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <div class="card-title">Enviar novas fotos/vídeos</div>
            </div>
            <div class="card-body">
                <form id="upload-form" class="form-grid cols-2-1">
                    <div class="form-field">
                        <label>Selecionar Arquivo (Fotos max 10MB | Vídeos max 50MB)</label>
                        <input type="file" id="arquivo-input" name="arquivo" accept="image/*,video/*" required>
                    </div>
                    <div class="form-field">
                        <label>Categoria</label>
                        <select name="categoria" id="categoria-input">
                            <option value="antes">Antes da Obra</option>
                            <option value="obra" selected>Durante a Obra</option>
                            <option value="depois">Depois da Obra (Finalizado)</option>
                            <option value="pagamento">Comprovante de Pagamento</option>
                            <option value="outro">Outro / Técnico</option>
                        </select>
                    </div>
                    <div class="form-field span-2">
                        <label>Legenda / Descrição Curta</label>
                        <input type="text" name="legenda" id="legenda-input" placeholder="Ex: Detalhe da sanca na sala">
                    </div>
                    <input type="hidden" name="os_id" value="<?= htmlspecialchars($os_id) ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <div style="display: flex; align-items: flex-end;">
                        <button type="submit" class="btn btn-red" id="btn-enviar" style="width: 100%; height: 40px; justify-content: center;">
                            🚀 Fazer Upload
                        </button>
                    </div>
                </form>
                <div id="upload-progress" style="display:none; margin-top: 15px;">
                    <div style="height: 4px; background: #eee; border-radius: 2px; overflow: hidden;">
                        <div id="progress-bar" style="height: 100%; background: var(--red); width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p style="font-size: 11px; color: var(--muted); margin-top: 5px;">Enviando arquivo, aguarde...</p>
                </div>
            </div>
        </div>

        <!-- GRADE DE FOTOS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Arquivos Anexados</div>
            </div>
            <div class="card-body">
                <div id="galeria-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
                    <!-- Carregado via JS -->
                    <div class="empty-state" id="galeria-vazia">
                        <div class="icon">📷</div>
                        <p>Nenhuma foto anexada ainda.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .foto-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s;
        position: relative;
    }
    .foto-card:hover { transform: translateY(-5px); }
    .foto-img-wrap {
        height: 160px;
        background: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        cursor: pointer;
    }
    .foto-img-wrap img, .foto-img-wrap video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .foto-info {
        padding: 12px;
    }
    .foto-legenda {
        font-size: 13px;
        font-weight: 600;
        color: var(--navy);
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .foto-meta {
        font-size: 11px;
        color: var(--muted);
        display: flex;
        justify-content: space-between;
    }
    .btn-delete-foto {
        position: absolute;
        top: 8px;
        right: 8px;
        background: rgba(220, 38, 38, 0.8);
        color: #fff;
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        backdrop-filter: blur(4px);
    }
    .badge-foto {
        position: absolute;
        top: 8px;
        left: 8px;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        background: rgba(13, 27, 42, 0.7);
        color: #fff;
        backdrop-filter: blur(4px);
    }
</style>

<script>
const osId = "<?= $os_id ?>";

async function carregarGaleria() {
    const res = await fetch(`api/anexos_api.php?acao=listar&os_id=${osId}`);
    const json = await res.json();
    
    const grid = document.getElementById('galeria-grid');
    if (json.dados && json.dados.length > 0) {
        document.getElementById('galeria-vazia').style.display = 'none';
        grid.innerHTML = json.dados.map(f => `
            <div class="foto-card" id="foto-${f.id}">
                <span class="badge-foto">${f.categoria}</span>
                <button class="btn-delete-foto" onclick="deletarAnexo(${f.id})">✕</button>
                <div class="foto-img-wrap" onclick="window.open('${f.url}', '_blank')">
                    ${f.eh_video 
                        ? `<video src="${f.url}"></video>` 
                        : `<img src="${f.url}" alt="${f.legenda}">`}
                </div>
                <div class="foto-info">
                    <div class="foto-legenda">${f.legenda || 'Sem legenda'}</div>
                    <div class="foto-meta">
                        <span>${f.criado_em.split(' ')[0]}</span>
                        <span>${f.tamanho_fmt}</span>
                    </div>
                </div>
            </div>
        `).join('');
    } else {
        grid.innerHTML = '<div class="empty-state" id="galeria-vazia"><div class="icon">📷</div><p>Nenhuma foto anexada ainda.</p></div>';
    }
}

document.getElementById('upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-enviar');
    const progress = document.getElementById('upload-progress');
    const bar = document.getElementById('progress-bar');
    
    const formData = new FormData(e.target);
    
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    progress.style.display = 'block';
    bar.style.width = '0%';

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/anexos_api.php?acao=upload', true);
        
        xhr.upload.onprogress = (ev) => {
            if (ev.lengthComputable) {
                const percent = (ev.loaded / ev.total) * 100;
                bar.style.width = percent + '%';
            }
        };

        xhr.onload = function() {
            const res = JSON.parse(this.responseText);
            if (res.sucesso) {
                alert('Upload concluído!');
                e.target.reset();
                carregarGaleria();
            } else {
                alert('Erro: ' + res.mensagem);
            }
            finalizar();
        };

        xhr.onerror = () => {
            alert('Erro de conexão.');
            finalizar();
        };

        xhr.send(formData);
    } catch (err) {
        console.error(err);
        finalizar();
    }

    function finalizar() {
        btn.disabled = false;
        btn.textContent = '🚀 Fazer Upload';
        progress.style.display = 'none';
    }
});

async function deletarAnexo(id) {
    if (!confirm('Deseja realmente excluir esta foto?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', "<?= csrf_token() ?>");

    const res = await fetch('api/anexos_api.php?acao=deletar', {
        method: 'POST',
        body: formData
    });
    const json = await res.json();
    
    if (json.sucesso) {
        document.getElementById(`foto-${id}`).remove();
        if (document.querySelectorAll('.foto-card').length === 0) {
            carregarGaleria();
        }
    } else {
        alert(json.mensagem);
    }
}

// Inicializa
carregarGaleria();
</script>

<?php include 'includes/foot.php'; ?>
