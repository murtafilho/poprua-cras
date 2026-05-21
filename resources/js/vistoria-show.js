// Slideshow
const slideshowUrls = window.SLIDESHOW_URLS;
let slideIndex = 0;

function openSlideshow(index) {
    slideIndex = index;
    const overlay = document.getElementById('slideshow-overlay');
    overlay.style.display = 'flex';
    updateSlide();
    document.body.style.overflow = 'hidden';
}

function closeSlideshow() {
    document.getElementById('slideshow-overlay').style.display = 'none';
    document.body.style.overflow = '';
}

function slideMove(dir) {
    slideIndex = (slideIndex + dir + slideshowUrls.length) % slideshowUrls.length;
    updateSlide();
}

function updateSlide() {
    document.getElementById('slide-image').src = slideshowUrls[slideIndex];
    document.getElementById('slide-counter').textContent = (slideIndex + 1) + ' / ' + slideshowUrls.length;
    document.getElementById('slide-prev').style.visibility = slideshowUrls.length > 1 ? 'visible' : 'hidden';
    document.getElementById('slide-next').style.visibility = slideshowUrls.length > 1 ? 'visible' : 'hidden';
}

document.addEventListener('keydown', function(e) {
    if (document.getElementById('slideshow-overlay').style.display === 'none') return;
    if (e.key === 'ArrowLeft') slideMove(-1);
    else if (e.key === 'ArrowRight') slideMove(1);
    else if (e.key === 'Escape') closeSlideshow();
});

// Swipe para mobile
(function() {
    const overlay = document.getElementById('slideshow-overlay');
    let touchStartX = 0;
    overlay.addEventListener('touchstart', function(e) { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
    overlay.addEventListener('touchend', function(e) {
        const diff = e.changedTouches[0].screenX - touchStartX;
        if (Math.abs(diff) > 50) {
            slideMove(diff > 0 ? -1 : 1);
        }
    }, { passive: true });
})();
(function() {
    const VISTORIA_ID = window.VISTORIA_ID;
    const APP_BASE = document.querySelector('meta[name="app-base"]').content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function openFotosDB() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open('poprua_fotos', 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('pendentes')) {
                    db.createObjectStore('pendentes', { keyPath: 'id', autoIncrement: true });
                }
            };
            req.onsuccess = (e) => resolve(e.target.result);
            req.onerror = (e) => reject(e.target.error);
        });
    }

    async function getFotosPendentes() {
        const db = await openFotosDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction('pendentes', 'readonly');
            const store = tx.objectStore('pendentes');
            const req = store.getAll();
            req.onsuccess = () => {
                // Filtra fotos desta vistoria ou do tempId da sessão
                const tempId = sessionStorage.getItem('poprua_fotos_temp_id');
                const fotos = req.result.filter(f =>
                    f.vistoria_id === VISTORIA_ID || f.vistoria_id === tempId
                );
                resolve(fotos);
            };
            req.onerror = () => reject(req.error);
        });
    }

    async function vincularTempId() {
        const tempId = sessionStorage.getItem('poprua_fotos_temp_id');
        if (!tempId) return;
        const db = await openFotosDB();
        const tx = db.transaction('pendentes', 'readwrite');
        const store = tx.objectStore('pendentes');
        const req = store.getAll();
        req.onsuccess = () => {
            req.result.forEach(f => {
                if (f.vistoria_id === tempId) {
                    f.vistoria_id = VISTORIA_ID;
                    store.put(f);
                }
            });
        };
        await new Promise((resolve) => { tx.oncomplete = resolve; });
        sessionStorage.removeItem('poprua_fotos_temp_id');
    }

    async function removerFotoLocal(id) {
        const db = await openFotosDB();
        const tx = db.transaction('pendentes', 'readwrite');
        tx.objectStore('pendentes').delete(id);
        return new Promise((resolve) => { tx.oncomplete = resolve; });
    }

    async function renderPendentes() {
        const fotos = await getFotosPendentes();
        const container = document.getElementById('fotos-pendentes');
        const preview = document.getElementById('pendentes-preview');
        const countEl = document.getElementById('pendentes-count');

        if (fotos.length === 0) {
            container.classList.add('hidden');
            return;
        }

        container.classList.remove('hidden');
        countEl.textContent = fotos.length;
        preview.innerHTML = '';

        fotos.forEach(foto => {
            const blob = new Blob([foto.data], { type: foto.type });
            const url = URL.createObjectURL(blob);
            const div = document.createElement('div');
            div.className = 'photo-item';
            div.style.position = 'relative';
            div.innerHTML = `
                <img src="${url}" alt="${foto.name}" style="opacity: 0.7;">
                <span style="position: absolute; bottom: 4px; left: 4px; background: var(--color-warning); color: #000; font-size: 10px; padding: 1px 6px; border-radius: 4px; font-weight: 600;">Pendente</span>
            `;
            preview.appendChild(div);
        });
    }

    async function sincronizarFotos() {
        const fotos = await getFotosPendentes();
        if (fotos.length === 0) return;

        const btn = document.getElementById('btn-sync-fotos');
        const progress = document.getElementById('sync-progress');
        const bar = document.getElementById('sync-bar');
        const status = document.getElementById('sync-status');

        btn.disabled = true;
        btn.textContent = 'Sincronizando...';
        progress.classList.remove('hidden');

        let enviadas = 0;
        for (const foto of fotos) {
            status.textContent = `Enviando ${enviadas + 1} de ${fotos.length}...`;
            bar.style.width = `${(enviadas / fotos.length) * 100}%`;

            try {
                const blob = new Blob([foto.data], { type: foto.type });
                const file = new File([blob], foto.name, { type: foto.type });
                const formData = new FormData();
                formData.append('vistoria_id', VISTORIA_ID);
                formData.append('foto', file);

                const resp = await fetch(`${APP_BASE}/api/vistorias/fotos`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData
                });

                if (resp.ok) {
                    await removerFotoLocal(foto.id);
                    enviadas++;

                    // Adiciona thumb ao grid
                    const data = await resp.json();
                    const grid = document.getElementById('fotos-grid');
                    const a = document.createElement('a');
                    a.href = data.url;
                    a.target = '_blank';
                    a.className = 'photo-item';
                    a.innerHTML = `<img src="${data.thumb || data.url}" alt="Foto" loading="lazy">`;
                    grid.appendChild(a);
                } else {
                    console.error('Erro ao enviar foto:', await resp.text());
                }
            } catch (err) {
                console.error('Erro ao sincronizar:', err);
            }
        }

        bar.style.width = '100%';
        status.textContent = `${enviadas} de ${fotos.length} foto(s) enviada(s)`;
        document.getElementById('fotos-count').textContent =
            parseInt(document.getElementById('fotos-count').textContent) + enviadas;

        btn.disabled = false;
        btn.textContent = 'Sincronizar Fotos';

        await renderPendentes();
        if (enviadas === fotos.length) {
            setTimeout(() => { progress.classList.add('hidden'); }, 2000);
        }
    }

    document.getElementById('btn-sync-fotos').addEventListener('click', sincronizarFotos);

    // Inicialização
    vincularTempId().then(renderPendentes);
})();

window.openSlideshow = openSlideshow;
window.closeSlideshow = closeSlideshow;
window.slideMove = slideMove;
