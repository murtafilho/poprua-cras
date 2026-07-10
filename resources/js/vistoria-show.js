import {
    blobFromRecord,
    clearTempPhotoId,
    getPendingPhotosFor,
    getTempPhotoId,
    reconcileTempId,
    removePendingPhotoById,
    uploadPendingPhoto,
} from './offline-upload';
import { enqueueAcao, syncPendingAcoes } from './offline-vistoria-acao';

const slideshowUrls = [...(window.SLIDESHOW_URLS || [])];
const slideshowLegends = [...(window.SLIDESHOW_LEGENDS || [])];
let slideIndex = 0;

const overlay = document.getElementById('slideshow-overlay');
const slideImage = document.getElementById('slide-image');
const slideCounter = document.getElementById('slide-counter');
const slideCaption = document.getElementById('slide-caption');
const slidePrev = document.getElementById('slide-prev');
const slideNext = document.getElementById('slide-next');
const slideClose = document.getElementById('slide-close');
const fotosGrid = document.getElementById('fotos-grid');

function openSlideshow(index) {
    if (!overlay || slideshowUrls.length === 0) {
        return;
    }

    slideIndex = index;
    overlay.hidden = false;
    overlay.classList.add('is-open');
    document.body.classList.add('slideshow-open');
    updateSlide();
}

function closeSlideshow() {
    if (!overlay) {
        return;
    }

    overlay.classList.remove('is-open');
    overlay.hidden = true;
    document.body.classList.remove('slideshow-open');
    if (slideImage) {
        slideImage.removeAttribute('src');
    }
}

function slideMove(dir) {
    if (slideshowUrls.length === 0) {
        return;
    }

    slideIndex = (slideIndex + dir + slideshowUrls.length) % slideshowUrls.length;
    updateSlide();
}

function updateSlide() {
    if (!slideImage || slideshowUrls.length === 0) {
        return;
    }

    slideImage.src = slideshowUrls[slideIndex];

    if (slideCounter) {
        slideCounter.textContent = `${slideIndex + 1} / ${slideshowUrls.length}`;
    }

    const legend = slideshowLegends[slideIndex] || '';
    if (slideCaption) {
        if (legend) {
            slideCaption.textContent = legend;
            slideCaption.hidden = false;
        } else {
            slideCaption.textContent = '';
            slideCaption.hidden = true;
        }
    }

    const showNav = slideshowUrls.length > 1;
    if (slidePrev) {
        slidePrev.hidden = !showNav;
    }
    if (slideNext) {
        slideNext.hidden = !showNav;
    }
}

function appendSyncedPhoto(data) {
    if (!fotosGrid) {
        return;
    }

    const index = slideshowUrls.length;
    slideshowUrls.push(data.url);
    slideshowLegends.push(data.legenda || '');

    const div = document.createElement('div');
    div.className = 'photo-item photo-item-expandable';
    div.dataset.slideIndex = String(index);
    div.setAttribute('role', 'button');
    div.setAttribute('tabindex', '0');
    div.setAttribute('aria-label', `Ampliar foto ${index + 1}`);
    div.innerHTML = `<img src="${data.thumb || data.url}" alt="Foto" loading="lazy">`;
    fotosGrid.appendChild(div);
}

function bindSlideshowUi() {
    if (!overlay) {
        return;
    }

    fotosGrid?.addEventListener('click', (event) => {
        const item = event.target.closest('.photo-item-expandable[data-slide-index]');
        if (!item) {
            return;
        }

        openSlideshow(Number(item.dataset.slideIndex));
    });

    fotosGrid?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        const item = event.target.closest('.photo-item-expandable[data-slide-index]');
        if (!item) {
            return;
        }

        event.preventDefault();
        openSlideshow(Number(item.dataset.slideIndex));
    });

    slideClose?.addEventListener('click', closeSlideshow);
    slidePrev?.addEventListener('click', () => slideMove(-1));
    slideNext?.addEventListener('click', () => slideMove(1));

    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            closeSlideshow();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (overlay.hidden) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            slideMove(-1);
        } else if (event.key === 'ArrowRight') {
            slideMove(1);
        } else if (event.key === 'Escape') {
            closeSlideshow();
        }
    });

    let touchStartX = 0;
    overlay.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0].screenX;
    }, { passive: true });

    overlay.addEventListener('touchend', (event) => {
        const diff = event.changedTouches[0].screenX - touchStartX;
        if (Math.abs(diff) > 50) {
            slideMove(diff > 0 ? -1 : 1);
        }
    }, { passive: true });
}

bindSlideshowUi();

(function() {
    const VISTORIA_ID = window.VISTORIA_ID;
    const APP_BASE = document.querySelector('meta[name="app-base"]').content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    async function getFotosPendentes() {
        const tempId = getTempPhotoId();
        const ids = [VISTORIA_ID];
        if (tempId) {
            ids.push(tempId);
        }

        const fotos = [];
        for (const id of ids) {
            const batch = await getPendingPhotosFor(id);
            fotos.push(...batch);
        }
        return fotos;
    }

    async function vincularTempId() {
        const tempId = getTempPhotoId();
        if (!tempId) {
            return;
        }
        await reconcileTempId(tempId, VISTORIA_ID);
        clearTempPhotoId();
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
            const blob = blobFromRecord(foto);
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
                const data = await uploadPendingPhoto(foto, { appBase: APP_BASE, csrfToken });
                await removePendingPhotoById(foto.id);
                enviadas++;

                appendSyncedPhoto(data);
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

    document.getElementById('btn-sync-fotos')?.addEventListener('click', sincronizarFotos);

    vincularTempId().then(renderPendentes);
})();

document.getElementById('btn-print-vistoria')?.addEventListener('click', () => window.print());

(function wireAcoesEstado() {
    const APP_BASE = document.querySelector('meta[name="app-base"]')?.content ?? '';
    const forms = document.querySelectorAll('form[data-acao-offline]');

    forms.forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const acao = form.getAttribute('data-acao-offline');
            const msg = form.getAttribute('data-confirm');
            if (msg && !confirm(msg)) return;

            const vistoriaId = window.VISTORIA_ID;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const botoes = document.querySelectorAll('form[data-acao-offline] button');
            botoes.forEach((b) => (b.disabled = true));

            let resp;
            try {
                resp = await fetch(`${APP_BASE}/api/vistorias/${vistoriaId}/${acao}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                });
            } catch (_networkErr) {
                await enqueueAcao({ vistoria_id: vistoriaId, acao });
                window.updateSyncBadge?.();
                window.showToast?.('Ação salva no aparelho — será enviada quando houver conexão.', 'info');
                marcarPendente(form, acao);
                return;
            }

            if (!resp.ok) {
                botoes.forEach((b) => (b.disabled = false));
                window.showToast?.('Não foi possível registrar a ação. Tente novamente.', 'error');
                return;
            }
            window.location.reload(); // reflete o novo estado (espelha o comportamento web)
        });
    });

    function marcarPendente(form, acao) {
        const btn = form.querySelector('button');
        if (btn) btn.textContent = 'Pendente de envio…';
    }

    // Auto-sync ao voltar a conexão nesta página.
    window.addEventListener('online', () => {
        syncPendingAcoes().then((r) => { if (r.enviadas > 0) window.location.reload(); });
    });
})();
