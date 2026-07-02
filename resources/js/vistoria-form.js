import { imgType, imgExt, imgName } from "./img-format";
import "./date-ptbr";
import {
    initTempPhotoSession,
    removePendingPhotoById,
    removePendingPhotoByName,
    savePendingPhoto,
    updatePendingPhotoLegenda,
    updatePendingPhotoPublica,
    MAX_FILE_SIZE_BYTES,
} from './offline-upload';
import { initDynamicClickHandlers, initStepperNavigation } from './vistoria-delegation';
import { updateComunicadoZeladoriaCampos as syncComunicadoZeladoriaCampos } from './comunicado-zeladoria-campos';
import { renderFotosGrid } from './foto-preview';

const fotoTempId = initTempPhotoSession();

const APP_BASE = document.querySelector('meta[name="app-base"]')?.content || '';
const RASCUNHO_DEBOUNCE_MS = Number(window.VISTORIA_RASCUNHO_CTX?.debounce_ms) || 5000;
// Impedir saída acidental sem confirmação (só quando há alterações)
let formSubmitting = false;
let formDirty = false;
window.addEventListener('beforeunload', function(e) {
    if (formSubmitting || !formDirty) return;
    e.preventDefault();
    e.returnValue = '';
});

let currentTab = 0;
const totalTabs = 7;
let visitedSteps = new Set([0]);
let recognition = null;
let activeInput = null;
let fotosSelecionadas = [];
let novosMoradores = [];
const pessoasVinculadas = [];
const tiposAbrigo = window.VISTORIA_TIPOS_ABRIGO;

const stepLabels = ['Dados', 'Caract.', 'Relatorio', 'Encam.', 'Pessoas', 'Fotos', 'Revisar'];
const checkmarkSVG = '<svg class="stepper-check" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';
const submitSpinnerSVG = '<svg class="spinner" style="width: 20px; height: 20px;" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';


document.addEventListener('DOMContentLoaded', function() {
    showTab(0);

    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('focus', function() { this.select(); });
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '[0-9]*');
    });

    initRascunho();

    const btnSalvar = document.getElementById('btn-salvar-rascunho');
    if (btnSalvar) {
        btnSalvar.addEventListener('click', () => {
            clearTimeout(rascunhoSaveTimeout);
            salvarRascunho();
        });
    }

    initBuscaPessoa();

    initStepperNavigation({ goToStep, getCurrentTab: () => currentTab, totalTabs, withPrevNext: true });
    initDynamicClickHandlers({
        goToStep,
        removerFoto,
        abrirModalMorador,
        removerMorador,
        desvincularPessoa,
        vincularPessoa,
    });

    const tipoSel = document.getElementById('tipo_abordagem_id');
    if (tipoSel) {
        tipoSel.addEventListener('change', toggleZeladoriaCampos);
        toggleZeladoriaCampos();
    }
});

function updateStepper(currentIndex) {
    visitedSteps.add(currentIndex);

    document.querySelectorAll('.stepper-item').forEach((item, i) => {
        const circle = item.querySelector('.stepper-circle');
        item.classList.remove('active', 'visited', 'completed');

        if (i === currentIndex) {
            item.classList.add('active');
            circle.innerHTML = i + 1;
        } else if (visitedSteps.has(i)) {
            item.classList.add('visited');
            circle.innerHTML = checkmarkSVG;
        } else {
            circle.innerHTML = i + 1;
        }
    });

    document.getElementById('step-indicator').innerHTML =
        `Etapa <span class="step-indicator-text">${currentIndex + 1}</span> de <span class="step-indicator-text">${totalTabs}</span> - ${stepLabels[currentIndex]}`;
}

function showTab(index) {
    currentTab = index;
    window.__currentTab = index;
    updateStepper(index);

    document.querySelectorAll('.tab-content').forEach((content, i) => {
        content.classList.toggle('hidden', i !== index);
    });

    // Atualizar visibilidade dos botoes Anterior/Proximo
    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    if (btnPrev) btnPrev.style.display = index === 0 ? 'none' : '';
    if (btnNext) btnNext.style.display = index === totalTabs - 1 ? 'none' : '';

    // Ao entrar na aba de revisao, montar checklist
    if (index === 6) {
        buildReviewChecklist();
    }

    document.querySelector('.form-content')?.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep(index) {
    salvarRascunho();
    showTab(index);
}

function buildReviewChecklist() {
    const container = document.getElementById('review-checklist');
    const statusEl = document.getElementById('review-status');
    const btnSubmit = document.getElementById('btn-submit');

    const checks = [
        {
            label: 'Data/Hora da Abordagem',
            step: 0,
            check: () => !!document.querySelector('[name="data_abordagem"]')?.value
        },
        {
            label: 'Tipo de Abordagem',
            step: 0,
            check: () => {
                const v = document.querySelector('[name="tipo_abordagem_id"]')?.value;
                return v && v !== '';
            }
        },
        {
            label: 'Resultado da Acao',
            step: 2,
            check: () => {
                const v = document.querySelector('[name="resultado_acao_id"]')?.value;
                return v && v !== '';
            }
        },
        {
            label: 'Quantidade de Pessoas',
            step: 0,
            check: () => {
                const v = document.querySelector('[name="quantidade_pessoas"]')?.value;
                return v && parseInt(v) > 0;
            },
            optional: true
        },
        {
            label: 'Observacoes preenchidas',
            step: 2,
            check: () => !!document.querySelector('[name="observacao"]')?.value?.trim(),
            optional: true
        },
        {
            label: 'Fotos anexadas',
            step: 5,
            check: () => fotosSelecionadas.length > 0,
            optional: true
        }
    ];

    let html = '';
    let allRequiredOk = true;

    checks.forEach(item => {
        const ok = item.check();
        if (!item.optional && !ok) allRequiredOk = false;

        const icon = ok
            ? '<svg style="width:20px;height:20px;color:var(--color-success);flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'
            : item.optional
                ? '<svg style="width:20px;height:20px;color:var(--text-muted);flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>'
                : '<svg style="width:20px;height:20px;color:var(--color-danger);flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>';

        const tag = !ok && !item.optional
            ? `<span class="badge badge-danger review-go-step" data-go-step="${item.step}" style="font-size:10px;cursor:pointer;">Ir para etapa ${item.step + 1}</span>`
            : item.optional && !ok
                ? '<span class="badge" style="font-size:10px;">Opcional</span>'
                : '';

        html += `
            <div class="review-item ${ok ? 'ok' : (!item.optional ? 'missing' : 'optional')}">
                ${icon}
                <span class="review-item-label">${item.label}</span>
                ${tag}
            </div>`;
    });

    container.innerHTML = html;

    if (allRequiredOk) {
        statusEl.innerHTML = '<div class="alert alert-success" style="margin:0;"><strong>Tudo certo!</strong> A vistoria esta pronta para ser registrada.</div>';
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('btn-disabled');
    } else {
        statusEl.innerHTML = '<div class="alert alert-danger" style="margin:0;"><strong>Campos obrigatorios pendentes.</strong> Corrija antes de finalizar.</div>';
        btnSubmit.disabled = true;
        btnSubmit.classList.add('btn-disabled');
    }
}

function checkRequiredFields(stepIndex) {
    const stepContent = document.querySelector(`.tab-content[data-tab="${stepIndex}"]`);
    if (!stepContent) return [];

    const requiredFields = stepContent.querySelectorAll('[required]');
    let missingFields = [];

    requiredFields.forEach(field => {
        if (!field.value || field.value === '') {
            const label = field.closest('.form-group')?.querySelector('.form-label');
            const fieldName = label ? label.textContent.replace(' *', '').trim() : 'Campo';
            missingFields.push(fieldName);
        }
    });

    return missingFields;
}

function toggleQtdCasais() {
    const checkbox = document.getElementById('checkbox_casal');
    const input = document.getElementById('qtd_casais');
    input.classList.toggle('hidden', !checkbox.checked);
    if (!checkbox.checked) input.value = 1;
}

function toggleQtdAnimais() {
    const checkbox = document.getElementById('checkbox_animais');
    const input = document.getElementById('qtd_animais');
    input.classList.toggle('hidden', !checkbox.checked);
    if (!checkbox.checked) input.value = 1;
}

// Helper: le um campo Sim/Nao tanto em <select> quanto em radio (defensivo).
function _simNao(name) {
    const el = document.querySelector('select[name="' + name + '"], input[name="' + name + '"][value="1"]');
    if (!el) return false;
    return el.tagName === 'SELECT' ? el.value === '1' : el.checked;
}

function toggleConducaoObs() {
    const isSim = _simNao('conducao_forcas_seguranca');
    const container = document.getElementById('conducao_obs_container');
    if (container) container.classList.toggle('hidden', !isSim);
    if (!isSim) { const o = document.getElementById('conducao_forcas_observacao'); if (o) o.value = ''; }
}

function toggleAutoNumero() {
    const isSim = _simNao('auto_fiscalizacao_aplicado');
    const container = document.getElementById('auto_numero_container');
    if (container) container.classList.toggle('hidden', !isSim);
    if (!isSim) { const n = document.getElementById('auto_fiscalizacao_numero'); if (n) n.value = ''; }
}

function toggleComunicado() {
    syncComunicadoZeladoriaCampos(shouldShowComunicadoZeladoriaCampos);
}

function isTipoComunicacaoZeladoria() {
    const sel = document.getElementById('tipo_abordagem_id');
    if (!sel?.value) {
        return false;
    }
    const opt = sel.options[sel.selectedIndex];
    const tipo = (opt?.dataset?.tipo || opt?.textContent || '').toLowerCase();

    return tipo.includes('comunic') && tipo.includes('zeladoria');
}

function shouldShowComunicadoZeladoriaCampos() {
    return isTipoComunicacaoZeladoria() || _simNao('houve_comunicado');
}

function toggleZeladoriaCampos() {
    syncComunicadoZeladoriaCampos(shouldShowComunicadoZeladoriaCampos);
}

function atualizarCamposAbrigos() {
    const qtd = parseInt(document.getElementById('qtd_abrigos').value) || 0;
    const container = document.getElementById('abrigos-container');
    const list = document.getElementById('abrigos-list');
    const tipoUnico = document.getElementById('tipo-abrigo-unico');

    if (qtd > 0) {
        container.classList.remove('hidden');
        tipoUnico.classList.add('hidden');
        list.innerHTML = '';
        for (let i = 0; i < qtd; i++) {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = `
                <span class="text-muted" style="width: 24px;">${i + 1}.</span>
                <select name="abrigos_tipos[]" class="form-input form-select flex-1">
                    <option value="">Selecione...</option>
                    ${tiposAbrigo.map(t => `<option value="${t.id}">${t.tipo_abrigo}</option>`).join('')}
                </select>
            `;
            list.appendChild(div);
        }
    } else {
        container.classList.add('hidden');
        tipoUnico.classList.remove('hidden');
        list.innerHTML = '';
    }
}

function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
           (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));
}

function openCameraWithAPI(type = 'back') {
    const facingMode = type === 'back' ? 'environment' : 'user';
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        openCameraInput(type);
        return;
    }

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } }
    })
    .then(function(stream) {
        const video = document.createElement('video');
        video.srcObject = stream;
        video.autoplay = true;
        video.playsInline = true;
        video.style.width = '100%';
        video.style.maxHeight = '400px';
        video.style.objectFit = 'contain';

        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Tire uma foto</h3>
                </div>
                <div class="modal-body">
                    <div id="camera-preview" style="background: black; border-radius: var(--card-radius); overflow: hidden;"></div>
                </div>
                <div class="modal-footer">
                    <button id="capture-btn" class="btn btn-primary flex-1">Capturar</button>
                    <button id="cancel-camera-btn" class="btn btn-secondary flex-1">Cancelar</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        modal.querySelector('#camera-preview').appendChild(video);

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        modal.querySelector('#capture-btn').addEventListener('click', function() {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0);
            canvas.toBlob(function(blob) {
                const file = new File([blob], 'foto-' + Date.now() + '.' + imgExt(), { type: imgType() });
                processPhotoFile(file);
                stream.getTracks().forEach(track => track.stop());
                document.body.removeChild(modal);
            }, imgType(), 0.9);
        });

        modal.querySelector('#cancel-camera-btn').addEventListener('click', function() {
            stream.getTracks().forEach(track => track.stop());
            document.body.removeChild(modal);
        });
    })
    .catch(function() {
        openCameraInput(type);
    });
}

function openCameraInput() {
    const input = document.getElementById('camera-input-back');
    if (input) {
        input.value = '';
        input.click();
    }
}

function openCamera() {
    if (isMobileDevice()) {
        openCameraWithAPI('back');
    } else {
        openCameraInput();
    }
}

function processPhotoFile(file) {
    if (!file.type.startsWith('image/')) return;

    // Limite client-side (ver MAX_FILE_SIZE_BYTES em offline-upload.js).
    if (file.size > MAX_FILE_SIZE_BYTES) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        if (typeof window.showToast === 'function') {
            window.showToast(`Arquivo ${sizeMB}MB excede o limite de 30MB.`, 'warning');
        }
        return;
    }

    const MAX_WIDTH = 1920;
    const MAX_HEIGHT = 1920;
    const QUALITY = 0.8;

    const img = new Image();
    img.onload = function() {
        let w = img.width;
        let h = img.height;

        // Redimensionar mantendo proporção
        if (w > MAX_WIDTH || h > MAX_HEIGHT) {
            const ratio = Math.min(MAX_WIDTH / w, MAX_HEIGHT / h);
            w = Math.round(w * ratio);
            h = Math.round(h * ratio);
        }

        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);

        canvas.toBlob(function(blob) {
            const compressed = new File([blob], imgName(file.name), { type: imgType(), lastModified: Date.now() });
            const preview = canvas.toDataURL(imgType(), 0.5);
            const entry = { file: compressed, preview, id: Date.now() + Math.random(), legenda: '', publica: false, pendingId: null };
            fotosSelecionadas.push(entry);
            formDirty = true;
            renderFotosPreview();
            savePendingPhoto(fotoTempId, compressed, { name: compressed.name, legenda: '', publica: false })
                .then((pendingId) => {
                    entry.pendingId = pendingId;
                    if (entry.legenda !== '') {
                        updatePendingPhotoLegenda(pendingId, entry.legenda).catch(() => {});
                    }
                })
                .catch((err) => {
                    console.error('Erro ao salvar foto localmente:', err);
                });
        }, imgType(), QUALITY);
    };
    img.src = URL.createObjectURL(file);
}

document.getElementById('camera-input-back').addEventListener('change', function(e) {
    if (e.target.files[0]) processPhotoFile(e.target.files[0]);
    e.target.value = '';
});

const galleryInput = document.getElementById('gallery-input');
if (galleryInput) {
    galleryInput.addEventListener('change', function(e) {
        Array.from(e.target.files).forEach(file => processPhotoFile(file));
        e.target.value = '';
    });
}

// Drop-zone desktop: arrastar arquivos do explorer pra dentro do form.
// Reusa processPhotoFile (mesma validacao + compress + preview que o gallery-input).
const fotosDropZone = document.getElementById('fotos-drop-zone');
if (fotosDropZone && galleryInput) {
    fotosDropZone.addEventListener('click', () => galleryInput.click());

    ['dragenter', 'dragover'].forEach(ev => {
        fotosDropZone.addEventListener(ev, e => {
            e.preventDefault();
            e.stopPropagation();
            fotosDropZone.style.background = 'var(--bg-hover, rgba(31, 111, 235, 0.05))';
            fotosDropZone.style.borderColor = 'var(--accent-primary, #1f6feb)';
        });
    });

    ['dragleave', 'drop'].forEach(ev => {
        fotosDropZone.addEventListener(ev, e => {
            e.preventDefault();
            e.stopPropagation();
            fotosDropZone.style.background = '';
            fotosDropZone.style.borderColor = '';
        });
    });

    fotosDropZone.addEventListener('drop', e => {
        const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'));
        if (files.length === 0) {
            if (typeof window.showToast === 'function') {
                window.showToast('Arraste apenas arquivos de imagem.', 'warning');
            }
            return;
        }
        files.forEach(processPhotoFile);
    });
}


function renderFotosPreview() {
    const container = document.getElementById('fotos-preview');
    if (!container) return;

    renderFotosGrid(container, fotosSelecionadas, {
        onLegendaChange: (index, value) => atualizarLegenda(index, value),
        onPublicaChange: (index, checked) => atualizarPublica(index, checked),
        onRemove: (index) => removerFoto(index),
    });

    document.getElementById('foto-count').textContent = fotosSelecionadas.length;
}

function atualizarLegenda(index, legenda) {
    const foto = fotosSelecionadas[index];
    if (!foto) {
        return;
    }
    foto.legenda = legenda;
    formDirty = true;
    if (foto.pendingId != null) {
        updatePendingPhotoLegenda(foto.pendingId, legenda).catch((err) => {
            console.error('Erro ao atualizar legenda local:', err);
        });
    }
    // Se pendingId ainda nao chegou, sera sincronizado pelo callback do savePendingPhoto
}

function atualizarPublica(index, publica) {
    const foto = fotosSelecionadas[index];
    if (!foto) {
        return;
    }
    foto.publica = publica;
    formDirty = true;
    if (foto.pendingId != null) {
        updatePendingPhotoPublica(foto.pendingId, publica).catch((err) => {
            console.error('Erro ao atualizar publica local:', err);
        });
    }
}

function removerFoto(index) {
    if (! confirm('Remover esta foto?')) {
        return;
    }

    const foto = fotosSelecionadas[index];
    if (foto) {
        const removePromise = foto.pendingId != null
            ? removePendingPhotoById(foto.pendingId)
            : removePendingPhotoByName(fotoTempId, foto.file.name);
        removePromise.catch((err) => {
            console.error('Erro ao remover foto local:', err);
        });
    }
    fotosSelecionadas.splice(index, 1);
    formDirty = true;
    renderFotosPreview();
}

function showSubmitSavingIndicator() {
    const btn = document.getElementById('btn-submit');
    if (!btn || btn.dataset.submitting === '1') {
        return;
    }

    btn.dataset.submitting = '1';
    btn.disabled = true;
    btn.classList.add('btn-disabled');
    btn.setAttribute('aria-busy', 'true');
    btn.innerHTML = `${submitSpinnerSVG} Registrando...`;

    const statusEl = document.getElementById('submit-status');
    if (statusEl) {
        statusEl.style.display = 'flex';
    }

    const cancelBtn = document.getElementById('btn-cancelar-vistoria');
    if (cancelBtn) {
        cancelBtn.style.pointerEvents = 'none';
        cancelBtn.style.opacity = '0.5';
        cancelBtn.setAttribute('aria-disabled', 'true');
    }

    const rascunhoEl = document.getElementById('rascunho-status');
    if (rascunhoEl) {
        rascunhoEl.style.display = 'block';
        rascunhoEl.textContent = 'Registrando zeladoria...';
        rascunhoEl.style.color = 'var(--text-muted)';
    }
}

function startVoiceInput(inputId) {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        showToast('Seu navegador nao suporta reconhecimento de voz.', 'warning');
        return;
    }

    if (recognition && activeInput === inputId) {
        recognition.stop();
        return;
    }

    if (recognition) recognition.stop();

    recognition = new SpeechRecognition();
    recognition.lang = 'pt-BR';
    recognition.continuous = false;
    recognition.interimResults = true;

    const input = document.getElementById(inputId);
    const button = input.parentElement.querySelector('.voice-btn');
    activeInput = inputId;

    button.classList.add('recording');

    recognition.onresult = (event) => {
        let transcript = '';
        for (let i = 0; i < event.results.length; i++) {
            transcript += event.results[i][0].transcript;
        }
        if (input.value && !input.value.endsWith('\n')) input.value += '\n';
        input.value = input.value.trimEnd() + (input.value ? '\n' : '') + transcript;
    };

    recognition.onend = () => {
        button.classList.remove('recording');
        activeInput = null;
    };

    recognition.onerror = () => {
        button.classList.remove('recording');
        activeInput = null;
    };

    recognition.start();
}

// Funcoes para gerenciar moradores
function abrirModalMorador(index = null) {
    const modal = document.getElementById('modal-morador');
    const titulo = document.getElementById('modal-morador-titulo');

    document.getElementById('morador-edit-index').value = index !== null ? index : '';
    document.getElementById('morador-nome-social').value = '';
    document.getElementById('morador-apelido').value = '';
    document.getElementById('morador-genero').value = '';
    document.getElementById('morador-documento').value = '';
    document.getElementById('morador-contato').value = '';
    document.getElementById('morador-observacoes').value = '';

    if (index !== null && novosMoradores[index]) {
        titulo.textContent = 'Editar Pessoa';
        const m = novosMoradores[index];
        document.getElementById('morador-nome-social').value = m.nome_social || '';
        document.getElementById('morador-apelido').value = m.apelido || '';
        document.getElementById('morador-genero').value = m.genero || '';
        document.getElementById('morador-documento').value = m.documento || '';
        document.getElementById('morador-contato').value = m.contato || '';
        document.getElementById('morador-observacoes').value = m.observacoes || '';
    } else {
        titulo.textContent = 'Nova Pessoa';
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function fecharModalMorador() {
    const modal = document.getElementById('modal-morador');
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function salvarMorador() {
    const nome = document.getElementById('morador-nome-social').value.trim();
    if (!nome) {
        showToast('Nome social e obrigatorio', 'warning');
        return;
    }

    const morador = {
        nome_social: nome,
        apelido: document.getElementById('morador-apelido').value.trim(),
        genero: document.getElementById('morador-genero').value,
        documento: document.getElementById('morador-documento').value.trim(),
        contato: document.getElementById('morador-contato').value.trim(),
        observacoes: document.getElementById('morador-observacoes').value.trim(),
        id: Date.now()
    };

    const editIndex = document.getElementById('morador-edit-index').value;
    if (editIndex !== '') {
        novosMoradores[parseInt(editIndex)] = morador;
    } else {
        novosMoradores.push(morador);
    }

    formDirty = true;
    renderNovosMoradores();
    fecharModalMorador();
}

function removerMorador(index) {
    if (confirm('Remover esta pessoa?')) {
        novosMoradores.splice(index, 1);
        formDirty = true;
        renderNovosMoradores();
    }
}

function renderNovosMoradores() {
    const container = document.getElementById('novos-moradores');
    container.innerHTML = '';

    novosMoradores.forEach((m, index) => {
        const div = document.createElement('div');
        div.className = 'morador-card morador-card-new';
        div.innerHTML = `
            <div class="morador-avatar morador-avatar-new">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div class="morador-info">
                <p class="morador-name">${m.nome_social}</p>
                ${m.apelido ? `<p class="morador-nickname">"${m.apelido}"</p>` : ''}
                <span class="badge badge-success">Novo</span>
            </div>
            <div class="morador-actions">
                <button type="button" data-edit-morador="${index}" class="btn btn-ghost btn-icon btn-sm">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button" data-remove-morador="${index}" class="btn btn-ghost btn-icon btn-sm text-danger">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
            <input type="hidden" name="novos_moradores[${index}][nome_social]" value="${m.nome_social}">
            <input type="hidden" name="novos_moradores[${index}][apelido]" value="${m.apelido || ''}">
            <input type="hidden" name="novos_moradores[${index}][genero]" value="${m.genero || ''}">
            <input type="hidden" name="novos_moradores[${index}][documento]" value="${m.documento || ''}">
            <input type="hidden" name="novos_moradores[${index}][contato]" value="${m.contato || ''}">
            <input type="hidden" name="novos_moradores[${index}][observacoes]" value="${m.observacoes || ''}">
        `;
        container.appendChild(div);
    });

    document.getElementById('morador-count').textContent = novosMoradores.length;
}

// Salvamento parcial por etapa (servidor + localStorage como fallback offline)
const DRAFT_KEY = 'poprua_vistoria_draft';
let rascunhoSaveTimeout = null;
let rascunhoSaving = false;

function getRascunhoContexto() {
    const ctx = window.VISTORIA_RASCUNHO_CTX || {};
    const form = document.getElementById('vistoria-form');
    const pontoInput = form?.querySelector('input[name="ponto_id"]');
    const latInput = form?.querySelector('input[name="lat"]');
    const lngInput = form?.querySelector('input[name="lng"]');

    return {
        ponto_id: pontoInput?.value ? parseInt(pontoInput.value, 10) : (ctx.ponto_id ?? null),
        lat: latInput?.value ? parseFloat(latInput.value) : (ctx.lat ?? null),
        lng: lngInput?.value ? parseFloat(lngInput.value) : (ctx.lng ?? null),
    };
}

function buildRascunhoQuery(ctx) {
    const params = new URLSearchParams();
    if (ctx.ponto_id) {
        params.set('ponto_id', String(ctx.ponto_id));
    } else if (ctx.lat != null && ctx.lng != null) {
        params.set('lat', String(ctx.lat));
        params.set('lng', String(ctx.lng));
    }
    return params;
}

function serializeFormToPayload(form) {
    const data = new FormData(form);
    const draft = {};
    for (const [key, value] of data.entries()) {
        if (key === 'fotos[]' || key.startsWith('fotos') || key === '_token') continue;
        if (draft[key]) {
            if (!Array.isArray(draft[key])) draft[key] = [draft[key]];
            draft[key].push(value);
        } else {
            draft[key] = value;
        }
    }
    draft._moradores = novosMoradores;
    draft._pessoas_vinculadas = pessoasVinculadas;
    return draft;
}

function setRascunhoStatus(state, savedAt = null) {
    const el = document.getElementById('rascunho-status');
    if (!el) return;
    el.style.display = 'block';

    if (state === 'saving') {
        el.textContent = 'Salvando rascunho...';
        el.style.color = 'var(--text-muted)';
    } else if (state === 'saved' && savedAt) {
        const time = savedAt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        el.textContent = `Rascunho salvo às ${time}`;
        el.style.color = 'var(--status-success, #10b981)';
    } else if (state === 'error') {
        el.textContent = 'Não foi possível salvar — tentará novamente';
        el.style.color = 'var(--status-danger, #ef4444)';
    } else {
        el.style.display = 'none';
    }
}

function salvarRascunhoLocal(payload, step) {
    try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify({
            ...payload,
            _step: step,
            _timestamp: Date.now(),
        }));
    } catch (e) {}
}

function salvarRascunho() {
    const form = document.getElementById('vistoria-form');
    if (!form || rascunhoSaving) return;

    const payload = serializeFormToPayload(form);
    salvarRascunhoLocal(payload, currentTab);

    const ctx = getRascunhoContexto();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) return;

    rascunhoSaving = true;
    setRascunhoStatus('saving');

    fetch(`${APP_BASE}/api/vistorias/rascunho`, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify({
            payload,
            etapa_atual: currentTab,
            ponto_id: ctx.ponto_id,
            lat: ctx.lat,
            lng: ctx.lng,
        }),
    })
        .then(resp => {
            if (!resp.ok) throw new Error('save failed');
            return resp.json();
        })
        .then(data => {
            formDirty = false;
            const savedAt = data.rascunho?.updated_at ? new Date(data.rascunho.updated_at) : new Date();
            setRascunhoStatus('saved', savedAt);
        })
        .catch(() => setRascunhoStatus('error'))
        .finally(() => { rascunhoSaving = false; });
}

function aplicarRascunho(draft, step) {
    const form = document.getElementById('vistoria-form');
    if (!form || !draft) return false;

    Object.entries(draft).forEach(([key, value]) => {
        if (key.startsWith('_')) return;
        const elements = form.querySelectorAll(`[name="${key}"]`);
        elements.forEach(el => {
            if (el.type === 'radio') {
                el.checked = el.value === value;
            } else if (el.type === 'checkbox') {
                const values = Array.isArray(value) ? value : [value];
                el.checked = values.includes(el.value);
            } else if (el.tagName === 'SELECT' || el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                el.value = value;
            }
        });
    });

    if (draft._moradores) {
        novosMoradores = Array.isArray(draft._moradores) ? draft._moradores : [];
        renderMoradores();
    }

    if (draft._pessoas_vinculadas && Array.isArray(draft._pessoas_vinculadas)) {
        pessoasVinculadas.length = 0;
        draft._pessoas_vinculadas.forEach(p => pessoasVinculadas.push(p));
        renderPessoasVinculadas();
    }

    const stepIndex = typeof step === 'number' ? step : (draft._step ?? 0);
    if (stepIndex > 0) {
        for (let i = 0; i <= stepIndex; i++) visitedSteps.add(i);
        showTab(stepIndex);
    }

    toggleConducaoObs();
    toggleAutoNumero();
    toggleZeladoriaCampos();

    return true;
}

function restaurarRascunhoLocal() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return false;
        const draft = JSON.parse(raw);
        if (Date.now() - draft._timestamp > 86400000) {
            localStorage.removeItem(DRAFT_KEY);
            return false;
        }
        return aplicarRascunho(draft, draft._step);
    } catch (e) {
        return false;
    }
}

async function descartarRascunhoServidor() {
    const ctx = getRascunhoContexto();
    const params = buildRascunhoQuery(ctx);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) return;

    await fetch(`${APP_BASE}/api/vistorias/rascunho?${params}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    }).catch(() => {});
}

function limparRascunho() {
    localStorage.removeItem(DRAFT_KEY);
    descartarRascunhoServidor();
}

async function initRascunho() {
    const ctx = getRascunhoContexto();
    const params = buildRascunhoQuery(ctx);

    try {
        const resp = await fetch(`${APP_BASE}/api/vistorias/rascunho?${params}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (resp.ok) {
            const data = await resp.json();
            if (data.rascunho?.payload) {
                const updated = new Date(data.rascunho.updated_at);
                const msg = `Continuar rascunho de ${updated.toLocaleString('pt-BR')}?`;
                if (confirm(msg)) {
                    aplicarRascunho(data.rascunho.payload, data.rascunho.etapa_atual);
                    formDirty = false;
                    setRascunhoStatus('saved', updated);
                    return;
                }
                await descartarRascunhoServidor();
                localStorage.removeItem(DRAFT_KEY);
                return;
            }
        }
    } catch (e) {}

    if (restaurarRascunhoLocal()) {
        formDirty = true;
        const banner = document.createElement('div');
        banner.style.cssText = 'padding: 8px 16px; background: var(--bg-warning-subtle, rgba(234,179,8,0.15)); border-radius: var(--radius-md); font-size: var(--text-xs); color: var(--text-primary); margin-bottom: var(--space-3); display: flex; align-items: center; justify-content: space-between;';
        const label = document.createElement('span');
        label.textContent = 'Rascunho local restaurado (sem conexão anterior).';
        const discardBtn = document.createElement('button');
        discardBtn.type = 'button';
        discardBtn.textContent = 'Descartar';
        discardBtn.style.cssText = 'background:none; border:none; color:var(--status-danger); cursor:pointer; font-size:var(--text-xs); text-decoration:underline;';
        discardBtn.addEventListener('click', () => {
            limparRascunho();
            location.reload();
        });
        banner.append(label, discardBtn);
        const formContent = document.querySelector('.form-content');
        if (formContent) formContent.prepend(banner);
    }
}

function scheduleSalvarRascunho() {
    clearTimeout(rascunhoSaveTimeout);
    rascunhoSaveTimeout = setTimeout(salvarRascunho, RASCUNHO_DEBOUNCE_MS);
}

const formEl = document.getElementById('vistoria-form');
if (formEl) {
    formEl.addEventListener('submit', function() {
        formSubmitting = true;
        showSubmitSavingIndicator();
        limparRascunho();
    });

    formEl.addEventListener('change', () => {
        formDirty = true;
        scheduleSalvarRascunho();
    });
    formEl.addEventListener('input', () => {
        formDirty = true;
        scheduleSalvarRascunho();
    });
}

window.salvarRascunho = salvarRascunho;
window.limparRascunho = limparRascunho;
window.goToStep = goToStep;
window.openCamera = openCamera;
window.removerFoto = removerFoto;
window.startVoiceInput = startVoiceInput;
window.abrirModalMorador = abrirModalMorador;
window.fecharModalMorador = fecharModalMorador;
window.salvarMorador = salvarMorador;
window.removerMorador = removerMorador;
window.toggleQtdCasais = toggleQtdCasais;
window.toggleQtdAnimais = toggleQtdAnimais;
window.toggleConducaoObs = toggleConducaoObs;
window.toggleAutoNumero = toggleAutoNumero;
window.toggleComunicado = toggleComunicado;
window.toggleZeladoriaCampos = toggleZeladoriaCampos;
window.atualizarCamposAbrigos = atualizarCamposAbrigos;
window.atualizarLegenda = atualizarLegenda;
window.atualizarPublica = atualizarPublica;

// === Busca e vinculação de pessoas existentes ===
function initBuscaPessoa() {
    const input = document.getElementById('busca-pessoa-existente');
    const resultados = document.getElementById('busca-pessoa-resultados');
    if (!input || !resultados) return;

    let debounce = null;

    input.addEventListener('input', function () {
        clearTimeout(debounce);
        const termo = this.value.trim();
        if (termo.length < 2) {
            resultados.style.display = 'none';
            return;
        }
        debounce = setTimeout(() => buscarPessoas(termo), 300);
    });

    input.addEventListener('blur', () => {
        setTimeout(() => { resultados.style.display = 'none'; }, 200);
    });
}

async function buscarPessoas(termo) {
    const resultados = document.getElementById('busca-pessoa-resultados');
    resultados.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
    resultados.style.display = 'block';

    try {
        const resp = await fetch(`${APP_BASE}/api/moradores/buscar?termo=${encodeURIComponent(termo)}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const data = await resp.json();

        if (!data.length) {
            resultados.innerHTML = '<div class="autocomplete-empty">Nenhuma pessoa encontrada</div>';
            return;
        }

        const idsJaPresentes = [];
        document.querySelectorAll('input[name="moradores_presentes[]"]').forEach(cb => idsJaPresentes.push(parseInt(cb.value)));
        pessoasVinculadas.forEach(p => idsJaPresentes.push(p.id));

        resultados.innerHTML = data
            .filter(m => !idsJaPresentes.includes(m.id))
            .map(m => {
                const payload = JSON.stringify({
                    id: m.id,
                    nome: m.nome_social || '',
                    apelido: m.apelido || '',
                    pontoOrigem: m.ponto_endereco || '',
                });
                return `
                <button type="button" class="autocomplete-item" data-vincular-pessoa='${payload.replace(/'/g, '&#39;')}'>
                    <div class="autocomplete-item-title">${m.nome_social}${m.apelido ? ' — "' + m.apelido + '"' : ''}</div>
                    <div class="autocomplete-item-subtitle">${m.ponto_endereco || 'Sem ponto atual'}</div>
                </button>`;
            }).join('') || '<div class="autocomplete-empty">Todas as pessoas encontradas já estão neste ponto</div>';
    } catch {
        resultados.innerHTML = '<div class="autocomplete-error">Erro na busca</div>';
    }
}

function vincularPessoa(id, nome, apelido, pontoOrigem) {
    if (pessoasVinculadas.find(p => p.id === id)) return;

    pessoasVinculadas.push({ id, nome, apelido, pontoOrigem });
    formDirty = true;
    renderPessoasVinculadas();

    document.getElementById('busca-pessoa-existente').value = '';
    document.getElementById('busca-pessoa-resultados').style.display = 'none';
}

function desvincularPessoa(id) {
    const idx = pessoasVinculadas.findIndex(p => p.id === id);
    if (idx !== -1) pessoasVinculadas.splice(idx, 1);
    renderPessoasVinculadas();
}

function renderPessoasVinculadas() {
    const container = document.getElementById('pessoas-vinculadas');
    if (!container) return;

    container.innerHTML = pessoasVinculadas.map(p => `
        <div class="morador-card morador-card-new" style="border-left: 3px solid var(--color-info);">
            <div class="morador-avatar" style="background: var(--color-info-dim); color: var(--color-info);">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div class="morador-info">
                <p class="morador-name">${p.nome}${p.apelido ? ' — "' + p.apelido + '"' : ''}</p>
                <p class="morador-nickname" style="font-size: 10px; color: var(--color-info);">Transferido de: ${p.pontoOrigem || 'sem ponto'}</p>
            </div>
            <input type="hidden" name="moradores_presentes[]" value="${p.id}">
            <button type="button" data-desvincular-pessoa="${p.id}" class="btn btn-ghost btn-icon btn-sm" style="color: var(--color-danger);">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    `).join('');
}

window.vincularPessoa = vincularPessoa;
window.desvincularPessoa = desvincularPessoa;
