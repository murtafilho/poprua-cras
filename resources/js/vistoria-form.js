// Impedir saída acidental sem confirmação (só quando há alterações)
let formSubmitting = false;
let formDirty = false;
window.addEventListener('beforeunload', function(e) {
    if (formSubmitting || !formDirty) return;
    e.preventDefault();
    e.returnValue = '';
});

let currentTab = 0;
const totalTabs = 5;
let visitedSteps = new Set([0]);
let recognition = null;
let activeInput = null;
let fotosSelecionadas = [];
let novosMoradores = [];
const tiposAbrigo = window.VISTORIA_TIPOS_ABRIGO;

const stepLabels = ['Dados', 'Caract.', 'Relatorio', 'Moradores', 'Revisar'];
const checkmarkSVG = '<svg class="stepper-check" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>';


document.addEventListener('DOMContentLoaded', function() {
    showTab(0);

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
    updateStepper(index);

    document.querySelectorAll('.tab-content').forEach((content, i) => {
        content.classList.toggle('hidden', i !== index);
    });

    // Ao entrar na aba de revisao, montar checklist
    if (index === 4) {
        buildReviewChecklist();
    }

    document.querySelector('.form-content')?.scrollTo({ top: 0, behavior: 'smooth' });
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
            label: 'Quantidade de Pessoas',
            step: 0,
            check: () => {
                const v = document.querySelector('[name="qtd_pessoas"]')?.value;
                return v && parseInt(v) > 0;
            },
            optional: true
        },
        {
            label: 'Observacoes preenchidas',
            step: 2,
            check: () => !!document.querySelector('[name="observacoes"]')?.value?.trim(),
            optional: true
        },
        {
            label: 'Fotos anexadas',
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
                const file = new File([blob], 'foto-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                processPhotoFile(file);
                stream.getTracks().forEach(track => track.stop());
                document.body.removeChild(modal);
            }, 'image/jpeg', 0.9);
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
    const MAX_FILE_SIZE_BYTES = 30 * 1024 * 1024;
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
            const compressed = new File([blob], file.name, { type: 'image/jpeg', lastModified: Date.now() });
            const preview = canvas.toDataURL('image/jpeg', 0.5);
            fotosSelecionadas.push({ file: compressed, preview, id: Date.now() + Math.random() });
            formDirty = true;
            renderFotosPreview();
            salvarFotoLocal(compressed);
        }, 'image/jpeg', QUALITY);
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
// Gera um tempId único para esta sessão de criação
const fotoTempId = 'temp_' + Date.now();
sessionStorage.setItem('poprua_fotos_temp_id', fotoTempId);

// Schema espelha o de offline-upload.js (poprua_fotos v1, store 'pendentes'
// com indices 'status' e 'vistoriaId'). Manter ambos sincronizados evita
// a race condition antes apontada em ux-004 do foto-audit.
function openFotosDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('poprua_fotos', 1);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('pendentes')) {
                const store = db.createObjectStore('pendentes', { keyPath: 'id', autoIncrement: true });
                store.createIndex('status', 'status', { unique: false });
                store.createIndex('vistoriaId', 'vistoriaId', { unique: false });
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
            vistoria_id: fotoTempId,
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
        const foto = all.find(f => f.vistoria_id === fotoTempId && f.name === fileName);
        if (foto) {
            const delTx = db.transaction('pendentes', 'readwrite');
            delTx.objectStore('pendentes').delete(foto.id);
        }
    } catch (err) {
        console.error('Erro ao remover foto local:', err);
    }
}

document.getElementById('vistoria-form').addEventListener('submit', function(e) {
    formSubmitting = true;
    // Fotos já estão no IndexedDB — form submete sem elas
});

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
        titulo.textContent = 'Editar Morador';
        const m = novosMoradores[index];
        document.getElementById('morador-nome-social').value = m.nome_social || '';
        document.getElementById('morador-apelido').value = m.apelido || '';
        document.getElementById('morador-genero').value = m.genero || '';
        document.getElementById('morador-documento').value = m.documento || '';
        document.getElementById('morador-contato').value = m.contato || '';
        document.getElementById('morador-observacoes').value = m.observacoes || '';
    } else {
        titulo.textContent = 'Novo Morador';
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
    if (confirm('Remover este morador?')) {
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
                <button type="button" onclick="abrirModalMorador(${index})" class="btn btn-ghost btn-icon btn-sm">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
                <button type="button" onclick="removerMorador(${index})" class="btn btn-ghost btn-icon btn-sm text-danger">
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

// Salvamento parcial por etapa (localStorage)
const DRAFT_KEY = 'poprua_vistoria_draft';

function salvarRascunho() {
    const form = document.getElementById('vistoria-form');
    if (!form) return;
    const data = new FormData(form);
    const draft = {};
    for (const [key, value] of data.entries()) {
        if (key === 'fotos[]' || key.startsWith('fotos')) continue;
        if (draft[key]) {
            if (!Array.isArray(draft[key])) draft[key] = [draft[key]];
            draft[key].push(value);
        } else {
            draft[key] = value;
        }
    }
    draft._step = currentTab;
    draft._timestamp = Date.now();
    draft._moradores = JSON.stringify(novosMoradores);
    try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    } catch (e) {}
}

function restaurarRascunho() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return false;
        const draft = JSON.parse(raw);
        if (Date.now() - draft._timestamp > 86400000) {
            localStorage.removeItem(DRAFT_KEY);
            return false;
        }
        const form = document.getElementById('vistoria-form');
        if (!form) return false;

        Object.entries(draft).forEach(([key, value]) => {
            if (key.startsWith('_') || key === 'fotos[]') return;
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
            try {
                novosMoradores = JSON.parse(draft._moradores);
                renderMoradores();
            } catch (e) {}
        }

        if (draft._step > 0) {
            for (let i = 0; i <= draft._step; i++) visitedSteps.add(i);
            showTab(draft._step);
        }

        toggleConducaoObs();
        toggleAutoNumero();
        toggleProtocolo();
        toggleZeladoriaCampos();

        return true;
    } catch (e) {
        return false;
    }
}

function limparRascunho() {
    localStorage.removeItem(DRAFT_KEY);
}

const restored = restaurarRascunho();
if (restored) {
    formDirty = true;
    const banner = document.createElement('div');
    banner.style.cssText = 'padding: 8px 16px; background: var(--bg-warning-subtle, rgba(234,179,8,0.15)); border-radius: var(--radius-md); font-size: var(--text-xs); color: var(--text-primary); margin-bottom: var(--space-3); display: flex; align-items: center; justify-content: space-between;';
    banner.innerHTML = `
        <span>Rascunho restaurado automaticamente.</span>
        <button type="button" onclick="limparRascunho(); location.reload();" style="background:none; border:none; color:var(--status-danger); cursor:pointer; font-size:var(--text-xs); text-decoration:underline;">Descartar</button>
    `;
    const formContent = document.querySelector('.form-content');
    if (formContent) formContent.prepend(banner);
}

const origShowTab = showTab;
const _showTabWithSave = function(index) {
    salvarRascunho();
    origShowTab(index);
};

const formEl = document.getElementById('vistoria-form');
if (formEl) {
    formEl.addEventListener('submit', function() {
        formSubmitting = true;
        limparRascunho();
    });

    let saveTimeout;
    formEl.addEventListener('change', () => {
        formDirty = true;
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(salvarRascunho, 1000);
    });
    formEl.addEventListener('input', () => {
        formDirty = true;
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(salvarRascunho, 2000);
    });
}

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
window.toggleProtocolo = toggleProtocolo;
window.toggleZeladoriaCampos = toggleZeladoriaCampos;
window.atualizarCamposAbrigos = atualizarCamposAbrigos;
window.atualizarLegenda = atualizarLegenda;

const tipoAbordagemSelect = document.getElementById('tipo_abordagem_id');
if (tipoAbordagemSelect) {
    tipoAbordagemSelect.addEventListener('change', toggleZeladoriaCampos);
}
