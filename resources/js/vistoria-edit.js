import { imgType, imgExt, imgName } from "./img-format";
const APP_BASE = document.querySelector('meta[name="app-base"]')?.content || '';

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
let fotosParaRemover = [];
const tiposAbrigo = window.VISTORIA_TIPOS_ABRIGO;
const abrigosTiposSelecionados = window.VISTORIA_ABRIGOS_SELECIONADOS;

const stepLabels = ['Dados', 'Caract.', 'Relatorio', 'Encam.', 'Pessoas', 'Fotos', 'Revisar'];
const checkmarkSVG = '<svg class="stepper-check" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';

document.addEventListener('DOMContentLoaded', function() {
    showTab(0);
    if (parseInt(document.getElementById('qtd_abrigos').value) > 0) {
        atualizarCamposAbrigos();
    }

    // Selecionar conteúdo ao focar + forçar teclado numérico
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('focus', function() { this.select(); });
        input.setAttribute('inputmode', 'numeric');
        input.setAttribute('pattern', '[0-9]*');
    });
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

    document.querySelector('.form-content')?.scrollTo({ top: 0, behavior: 'smooth' });

    const btnPrev = document.getElementById('btn-prev');
    const btnNext = document.getElementById('btn-next');
    if (btnPrev) btnPrev.style.display = index === 0 ? 'none' : '';
    if (btnNext) btnNext.style.display = index === totalTabs - 1 ? 'none' : '';

    if (index === totalTabs - 1) {
        buildReviewChecklist();
    }
}

function goToStep(index) {
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
            label: 'Observacoes preenchidas',
            step: 2,
            check: () => !!document.querySelector('[name="observacao"]')?.value?.trim(),
            optional: true
        },
        {
            label: 'Novas fotos anexadas',
            step: 3,
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
            ? `<span class="badge badge-danger" style="font-size:10px;cursor:pointer;" onclick="goToStep(${item.step})">Ir para etapa ${item.step + 1}</span>`
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
        statusEl.innerHTML = '<div class="alert alert-success" style="margin:0;"><strong>Tudo certo!</strong> A vistoria esta pronta para ser salva.</div>';
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('btn-disabled');
    } else {
        statusEl.innerHTML = '<div class="alert alert-danger" style="margin:0;"><strong>Campos obrigatorios pendentes.</strong> Corrija antes de salvar.</div>';
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
    const container = document.getElementById('qtd_casais_container');
    container.classList.toggle('hidden', !checkbox.checked);
    if (!checkbox.checked) document.getElementById('qtd_casais').value = 1;
}

function toggleQtdAnimais() {
    const checkbox = document.getElementById('checkbox_animais');
    const container = document.getElementById('qtd_animais_container');
    container.classList.toggle('hidden', !checkbox.checked);
    if (!checkbox.checked) document.getElementById('qtd_animais').value = 1;
}

function toggleConducaoObs() {
    const radioSim = document.querySelector('input[name="conducao_forcas_seguranca"][value="1"]');
    const container = document.getElementById('conducao_obs_container');
    container.classList.toggle('hidden', !radioSim.checked);
    if (!radioSim.checked) document.getElementById('conducao_forcas_observacao').value = '';
}

function toggleAutoNumero() {
    const radioSim = document.querySelector('input[name="auto_fiscalizacao_aplicado"][value="1"]');
    const container = document.getElementById('auto_numero_container');
    container.classList.toggle('hidden', !radioSim.checked);
    if (!radioSim.checked) document.getElementById('auto_fiscalizacao_numero').value = '';
}

function toggleProtocolo() {
    const radioSim = document.querySelector('input[name="houve_lavratura"][value="1"]');
    const container = document.getElementById('tipo_protocolo_container');
    if (container) {
        container.classList.toggle('hidden', !radioSim.checked);
        if (!radioSim.checked) {
            const select = container.querySelector('select[name="tipo_protocolo"]');
            if (select) select.value = '';
        }
    }
}

function toggleComunicado() {
    const sim = document.querySelector('input[name="houve_comunicado"][value="1"]');
    const container = document.getElementById('data_comunicado_container');
    if (!sim || !container) return;
    container.classList.toggle('hidden', !sim.checked);
    if (!sim.checked) {
        const dateInput = container.querySelector('input[name="data_comunicado"]');
        if (dateInput) dateInput.value = '';
    }
}

function toggleZeladoriaCampos() {
    const select = document.getElementById('tipo_abordagem_id');
    const container = document.getElementById('zeladoria-campos');
    if (!select || !container) return;
    const selectedOption = select.options[select.selectedIndex];
    const tipo = selectedOption ? selectedOption.getAttribute('data-tipo') : '';
    const isComunicacao = tipo && tipo.toLowerCase().includes('comunica');
    container.classList.toggle('hidden', !isComunicacao);
    if (!isComunicacao) {
        const dateInput = container.querySelector('input[name="data_prevista_zeladoria"]');
        const periodoSelect = container.querySelector('select[name="periodo_zeladoria"]');
        if (dateInput) dateInput.value = '';
        if (periodoSelect) periodoSelect.value = '';
    }
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
            const selectedValue = abrigosTiposSelecionados[i] || '';
            const div = document.createElement('div');
            div.className = 'flex items-center gap-2';
            div.innerHTML = `
                <span class="text-muted" style="width: 24px;">${i + 1}.</span>
                <select name="abrigos_tipos[]" class="form-input form-select flex-1">
                    <option value="">Selecione...</option>
                    ${tiposAbrigo.map(t => `<option value="${t.id}" ${t.id == selectedValue ? 'selected' : ''}>${t.tipo_abrigo}</option>`).join('')}
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

function marcarRemoverFoto(fotoId) {
    if (confirm('Remover esta foto?')) {
        fotosParaRemover.push(fotoId);
        formDirty = true;
        document.getElementById('foto-existente-' + fotoId).style.display = 'none';
        atualizarInputsRemocao();
    }
}

function atualizarInputsRemocao() {
    const container = document.getElementById('fotos-remover-inputs');
    container.innerHTML = '';
    fotosParaRemover.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remover_fotos[]';
        input.value = id;
        container.appendChild(input);
    });
}

function isMobileDevice() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
           (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));
}

function openCameraInput() {
    const input = document.getElementById('camera-input-back');
    if (input) {
        input.value = '';
        input.click();
    }
}

function openCamera() {
    openCameraInput();
}

function processPhotoFile(file) {
    if (!file.type.startsWith('image/')) return;
    const MAX_WIDTH = 1920;
    const MAX_HEIGHT = 1920;
    const QUALITY = 0.8;

    const img = new Image();
    img.onload = function() {
        let w = img.width;
        let h = img.height;

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
            fotosSelecionadas.push({ file: compressed, preview, id: Date.now() + Math.random() });
            formDirty = true;
            renderFotosPreview();
            salvarFotoLocal(compressed);
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
    container.innerHTML = '';
    fotosSelecionadas.forEach((foto, index) => {
        const div = document.createElement('div');
        div.className = 'photo-preview';
        div.innerHTML = `
            <img src="${foto.preview}" alt="Foto ${index + 1}">
            <button type="button" onclick="removerFoto(${index})" class="photo-remove-btn">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <input type="text" name="legendas_fotos[]" placeholder="Legenda..." value="${foto.legenda || ''}"
                   onchange="atualizarLegenda(${index}, this.value)"
                   style="width: 100%; font-size: 11px; padding: 4px 6px; margin-top: 4px; border: 1px solid var(--border-primary); border-radius: var(--radius-sm); background: var(--bg-secondary); color: var(--text-primary);">
        `;
        container.appendChild(div);
    });
    document.getElementById('foto-count').textContent = fotosSelecionadas.length;
}

function atualizarLegenda(index, legenda) {
    if (fotosSelecionadas[index]) {
        fotosSelecionadas[index].legenda = legenda;
    }
}

function removerFoto(index) {
    const foto = fotosSelecionadas[index];
    if (foto) removerFotoLocal(foto.file.name);
    fotosSelecionadas.splice(index, 1);
    formDirty = true;
    renderFotosPreview();
}

// IndexedDB para fotos pendentes
const vistoriaId = window.VISTORIA_ID;

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

// Salva foto no IndexedDB imediatamente ao tirar
async function salvarFotoLocal(file) {
    try {
        const buffer = await file.arrayBuffer();
        const db = await openFotosDB();
        const tx = db.transaction('pendentes', 'readwrite');
        tx.objectStore('pendentes').add({
            vistoria_id: vistoriaId,
            name: file.name,
            type: file.type,
            data: buffer,
            created_at: new Date().toISOString()
        });
        await new Promise((resolve, reject) => {
            tx.oncomplete = resolve;
            tx.onerror = reject;
        });
        console.log('Foto salva no dispositivo:', file.name);
    } catch (err) {
        console.error('Erro ao salvar foto localmente:', err);
    }
}

// Remove foto do IndexedDB ao remover do preview
async function removerFotoLocal(fileName) {
    try {
        const db = await openFotosDB();
        const tx = db.transaction('pendentes', 'readonly');
        const store = tx.objectStore('pendentes');
        const all = await new Promise(r => { const req = store.getAll(); req.onsuccess = () => r(req.result); });
        const foto = all.find(f => f.vistoria_id === vistoriaId && f.name === fileName);
        if (foto) {
            const delTx = db.transaction('pendentes', 'readwrite');
            delTx.objectStore('pendentes').delete(foto.id);
        }
    } catch (err) {
        console.error('Erro ao remover foto local:', err);
    }
}

const formEl = document.getElementById('vistoria-form');
formEl.addEventListener('submit', function(e) {
    formSubmitting = true;
    // Fotos já estão no IndexedDB — form submete sem elas
});

// Rastrear alterações no formulário para o dirty check
formEl.addEventListener('change', () => { formDirty = true; });
formEl.addEventListener('input', () => { formDirty = true; });

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

let novosMoradores = [];

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
    document.getElementById('modal-morador').classList.add('hidden');
    document.body.style.overflow = '';
}

function salvarMorador() {
    const nome = document.getElementById('morador-nome-social').value.trim();
    if (!nome) return;

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
    if (!container) return;
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
                <button type="button" onclick="abrirModalMorador(${index})" class="btn btn-ghost btn-icon btn-sm">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button" onclick="removerMorador(${index})" class="btn btn-ghost btn-icon btn-sm">
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

    const countEl = document.getElementById('morador-count');
    if (countEl) countEl.textContent = novosMoradores.length;
}

window.abrirModalMorador = abrirModalMorador;
window.fecharModalMorador = fecharModalMorador;
window.salvarMorador = salvarMorador;
window.removerMorador = removerMorador;
// === Busca e vinculação de pessoas existentes ===
const pessoasVinculadas = [];

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
            .map(m => `
                <button type="button" class="autocomplete-item" onclick="vincularPessoa(${m.id}, '${(m.nome_social || '').replace(/'/g, "\\'")}', '${(m.apelido || '').replace(/'/g, "\\'")}', '${(m.ponto_endereco || '').replace(/'/g, "\\'")}')">
                    <div class="autocomplete-item-title">${m.nome_social}${m.apelido ? ' — "' + m.apelido + '"' : ''}</div>
                    <div class="autocomplete-item-subtitle">${m.ponto_endereco || 'Sem ponto atual'}</div>
                </button>
            `).join('') || '<div class="autocomplete-empty">Todas as pessoas encontradas já estão neste ponto</div>';
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
            <button type="button" onclick="desvincularPessoa(${p.id})" class="btn btn-ghost btn-icon btn-sm" style="color: var(--color-danger);">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    `).join('');
}

document.addEventListener('DOMContentLoaded', initBuscaPessoa);
window.vincularPessoa = vincularPessoa;
window.desvincularPessoa = desvincularPessoa;

window.goToStep = goToStep;
window.openCamera = openCamera;
window.removerFoto = removerFoto;
window.marcarRemoverFoto = marcarRemoverFoto;
window.startVoiceInput = startVoiceInput;
window.toggleQtdCasais = toggleQtdCasais;
window.toggleQtdAnimais = toggleQtdAnimais;
window.toggleConducaoObs = toggleConducaoObs;
window.toggleAutoNumero = toggleAutoNumero;
window.toggleProtocolo = toggleProtocolo;
window.toggleComunicado = toggleComunicado;
window.toggleZeladoriaCampos = toggleZeladoriaCampos;
window.atualizarCamposAbrigos = atualizarCamposAbrigos;
window.atualizarLegenda = atualizarLegenda;

const tipoAbordagemSelect = document.getElementById('tipo_abordagem_id');
if (tipoAbordagemSelect) {
    tipoAbordagemSelect.addEventListener('change', toggleZeladoriaCampos);
    toggleZeladoriaCampos();
}
toggleProtocolo();

const buscaPontoInput = document.getElementById('busca-ponto');
if (buscaPontoInput) {
    let buscaTimeout;
    buscaPontoInput.addEventListener('input', function() {
        clearTimeout(buscaTimeout);
        const termo = this.value.trim();
        const container = document.getElementById('resultados-ponto');
        if (termo.length < 3) {
            container.classList.add('hidden');
            return;
        }
        buscaTimeout = setTimeout(async () => {
            const res = await fetch(`${APP_BASE}/api/pontos/busca?q=${encodeURIComponent(termo)}`);
            const pontos = await res.json();
            container.innerHTML = '';
            if (pontos.length === 0) {
                container.innerHTML = '<div style="padding: 8px 12px; color: var(--text-muted); font-size: var(--text-xs);">Nenhum ponto encontrado</div>';
            }
            pontos.forEach(p => {
                const div = document.createElement('div');
                div.style.cssText = 'padding: 8px 12px; cursor: pointer; font-size: var(--text-sm); border-bottom: 1px solid var(--border-primary);';
                div.textContent = `${p.tipo || ''} ${p.logradouro}, ${p.numero} - ${p.bairro || ''} (${p.regional || ''})`;
                div.addEventListener('click', () => {
                    document.getElementById('ponto_id_input').value = p.id;
                    formDirty = true;
                    document.getElementById('ponto-atual').innerHTML = `
                        <p style="font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--status-success);">
                            Ponto alterado: ${p.tipo || ''} ${p.logradouro}, ${p.numero} - ${p.bairro || ''}
                        </p>`;
                    container.classList.add('hidden');
                    document.getElementById('alterar-ponto-section').classList.add('hidden');
                });
                div.addEventListener('mouseenter', () => div.style.background = 'var(--bg-tertiary)');
                div.addEventListener('mouseleave', () => div.style.background = '');
                container.appendChild(div);
            });
            container.classList.remove('hidden');
        }, 300);
    });
}
