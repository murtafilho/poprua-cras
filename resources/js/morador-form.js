// Preview de foto da camera ou galeria
['camera-input', 'gallery-input'].forEach(function(inputId) {
    document.getElementById(inputId).addEventListener('change', function(e) {
const file = e.target.files[0];
if (file) {
    // Limite client-side (ver MAX_FILE_SIZE_BYTES em offline-upload.js).
    const MAX_FILE_SIZE_BYTES = 30 * 1024 * 1024;
    if (file.size > MAX_FILE_SIZE_BYTES) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
        if (typeof window.showToast === 'function') {
            window.showToast(`Arquivo ${sizeMB}MB excede o limite de 30MB.`, 'warning');
        }
        e.target.value = '';
        return;
    }

    // Copiar arquivo para o input hidden de envio
    if (inputId === 'camera-input') {
        const galleryInput = document.getElementById('gallery-input');
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        galleryInput.files = dataTransfer.files;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('foto-preview');
        preview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">`;
    };
    reader.readAsDataURL(file);
}
    });
});

// Voice input
let recognition = null;
let currentField = null;

function startVoiceInput(fieldId) {
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
showToast('Reconhecimento de voz nao suportado neste navegador.', 'warning');
return;
    }

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    recognition = new SpeechRecognition();
    recognition.lang = 'pt-BR';
    recognition.continuous = false;
    recognition.interimResults = false;

    currentField = document.getElementById(fieldId);
    const voiceBtn = currentField.parentElement.querySelector('.voice-btn');

    recognition.onstart = function() {
voiceBtn.classList.add('recording');
    };

    recognition.onend = function() {
voiceBtn.classList.remove('recording');
    };

    recognition.onresult = function(event) {
const transcript = event.results[0][0].transcript;
if (currentField.tagName === 'TEXTAREA') {
    currentField.value += (currentField.value ? '\n' : '') + transcript;
} else {
    currentField.value = transcript;
}
    };

    recognition.onerror = function(event) {
voiceBtn.classList.remove('recording');
console.error('Erro no reconhecimento de voz:', event.error);
    };

    recognition.start();
}
window.startVoiceInput = startVoiceInput;
