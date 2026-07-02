/**
 * SIZEM — Módulo compartilhado de preview de fotos com legenda e "Incluir em relatório"
 * Usado em vistoria-form.js, vistoria-edit.js (fotos novas) e ponto-form.js
 */

/**
 * Cria o HTML de um card de foto para preview (foto nova, antes de upload)
 * @param {Object} config
 * @param {number} config.index - Índice da foto no array
 * @param {string} config.preview - URL da prévia (dataURL)
 * @param {string} config.legenda - Texto da legenda
 * @param {boolean} config.publica - Flag "Incluir em relatório"
 * @param {boolean} config.readonly - Se true, remove botões de ação
 * @returns {string} HTML do card
 */
export function renderFotoPreviewCard(config) {
    const { index, preview, legenda = '', publica = false, readonly = false } = config;
    const publicaChecked = publica ? 'checked' : '';

    const removeBtn = readonly
        ? ''
        : `<button type="button" data-foto-index="${index}" class="photo-remove-btn" title="Remover foto">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
           </button>`;

    return `
        <div class="photo-preview-wrap ${publica ? 'foto-publica' : ''}" data-index="${index}">
            <div class="photo-preview">
                <img src="${preview}" alt="Foto ${index + 1}" loading="lazy">
                ${removeBtn}
            </div>
            <div class="photo-meta">
                <input type="text"
                       name="legendas_fotos[]"
                       placeholder="Legenda (opcional)..."
                       value="${escapeHtml(legenda)}"
                       class="photo-legenda-input form-input"
                       data-index="${index}"
                       ${readonly ? 'readonly' : ''}>
                <label class="photo-publica-label">
                    <input type="checkbox"
                           name="publicas_fotos[]"
                           value="1"
                           class="photo-publica-checkbox"
                           data-index="${index}"
                           ${publicaChecked}
                           ${readonly ? 'disabled' : ''}>
                    <input type="hidden" name="publicas_fotos[]" value="0" ${publicaChecked ? 'disabled' : ''}>
                    <span class="photo-publica-text">Incluir em relatório</span>
                </label>
            </div>
        </div>
    `;
}

/**
 * Renderiza a grade de previews em um container
 * @param {HTMLElement} container
 * @param {Array} fotos - Array de { preview, legenda, publica, file?, pendingId? }
 * @param {Object} options
 * @param {boolean} options.readonly
 * @param {Function} options.onLegendaChange - (index, value) => {}
 * @param {Function} options.onPublicaChange - (index, checked) => {}
 * @param {Function} options.onRemove - (index) => {}
 */
export function renderFotosGrid(container, fotos, options = {}) {
    const { readonly = false, onLegendaChange, onPublicaChange, onRemove } = options;

    container.innerHTML = '';

    fotos.forEach((foto, index) => {
        const div = document.createElement('div');
        div.innerHTML = renderFotoPreviewCard({
            index,
            preview: foto.preview,
            legenda: foto.legenda || '',
            publica: foto.publica ?? false,
            readonly,
        });
        container.appendChild(div.firstElementChild);
    });

    container.querySelectorAll('.photo-legenda-input').forEach((input) => {
        input.addEventListener('change', (e) => {
            const idx = parseInt(e.target.dataset.index, 10);
            if (onLegendaChange) onLegendaChange(idx, e.target.value);
        });
    });

    container.querySelectorAll('.photo-publica-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', (e) => {
            const idx = parseInt(e.target.dataset.index, 10);
            const wrap = e.target.closest('.photo-preview-wrap');

            wrap?.classList.toggle('foto-publica', e.target.checked);

            const hidden = e.target.nextElementSibling;
            if (hidden?.type === 'hidden') {
                hidden.disabled = e.target.checked;
            }

            if (onPublicaChange) onPublicaChange(idx, e.target.checked);
        });
    });

    container.querySelectorAll('.photo-remove-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            const idx = parseInt(e.currentTarget.dataset.fotoIndex, 10);
            if (onRemove) onRemove(idx);
        });
    });
}

function escapeHtml(text) {
    if (text == null) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Comprime uma imagem antes do upload
 * @param {File} file
 * @param {number} maxWidth
 * @param {number} quality
 * @returns {Promise<Blob>}
 */
export async function compressImage(file, maxWidth = 1920, quality = 0.7) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, maxWidth / img.width);
            canvas.width = img.width * scale;
            canvas.height = img.height * scale;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                URL.revokeObjectURL(img.src);
                resolve(blob);
            }, 'image/jpeg', quality);
        };
        img.onerror = reject;
        img.src = URL.createObjectURL(file);
    });
}

/**
 * Cria uma dataURL de preview a partir de um File/Blob
 * @param {File|Blob} file
 * @returns {Promise<string>}
 */
export function createPreviewUrl(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

/**
 * Valida tamanho e tipo de arquivo de imagem
 * @param {File} file
 * @param {number} maxSizeBytes - Default 10MB
 * @returns {Object} { ok: boolean, error?: string }
 */
export function validateImageFile(file, maxSizeBytes = 10 * 1024 * 1024) {
    if (!file.type.startsWith('image/')) {
        return { ok: false, error: 'O arquivo deve ser uma imagem.' };
    }
    if (file.size > maxSizeBytes) {
        const maxMB = Math.round(maxSizeBytes / 1024 / 1024);
        return { ok: false, error: `Imagem excede ${maxMB}MB.` };
    }
    return { ok: true };
}
