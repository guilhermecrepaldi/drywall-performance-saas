/* Antigravity v2026 Core Scripts */

function toast(msg, type = 'info') {
    const t = document.createElement('div');
    t.style.position = 'fixed';
    t.style.bottom = '24px';
    t.style.right = '24px';
    t.style.padding = '12px 20px';
    t.style.background = type === 'error' ? 'var(--danger)' : 'var(--accent)';
    t.style.color = '#fff';
    t.style.borderRadius = '6px';
    t.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
    t.style.fontSize = '12px';
    t.style.fontWeight = '700';
    t.style.zIndex = '1000';
    t.style.textTransform = 'uppercase';
    t.style.letterSpacing = '1px';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', () => {
    // URL Notification Handler
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('ok')) {
        const val = urlParams.get('ok');
        if (val === '1') toast('Operation Successful', 'success');
        if (val === 'del') toast('Record Deleted', 'warning');
    }
});
