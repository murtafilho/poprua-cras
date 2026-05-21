document.addEventListener('DOMContentLoaded', function() {
const APP_BASE = document.querySelector('meta[name="app-base"]').content;
const latInput = document.getElementById('lat');
const lngInput = document.getElementById('lng');

// Mini mapa
const lat = parseFloat(latInput.value) || -19.9191;
const lng = parseFloat(lngInput.value) || -43.9386;
const hasCoords = latInput.value && lngInput.value;

const map = L.map('mini-map').setView([lat, lng], hasCoords ? 18 : 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '&copy; OSM'
}).addTo(map);

let marker = null;
if (hasCoords) {
    marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        latInput.value = pos.lat.toFixed(14);
        lngInput.value = pos.lng.toFixed(14);
    });
}

map.on('click', function(e) {
    latInput.value = e.latlng.lat.toFixed(14);
    lngInput.value = e.latlng.lng.toFixed(14);
    if (marker) {
        marker.setLatLng(e.latlng);
    } else {
        marker = L.marker(e.latlng, { draggable: true }).addTo(map);
        marker.on('dragend', function(ev) {
            const pos = ev.target.getLatLng();
            latInput.value = pos.lat.toFixed(14);
            lngInput.value = pos.lng.toFixed(14);
        });
    }
});

// Busca de endereco com autocomplete
const buscaInput = document.getElementById('busca-endereco');
const resultsDiv = document.getElementById('endereco-results');
const hiddenId = document.getElementById('endereco_atualizado_id');
const selecionadoDiv = document.getElementById('endereco-selecionado');
const selecionadoTexto = document.getElementById('endereco-selecionado-texto');
let searchTimeout = null;

buscaInput.addEventListener('input', function() {
    if (searchTimeout) clearTimeout(searchTimeout);
    const termo = this.value.trim();
    if (termo.length < 3) {
        resultsDiv.style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(() => buscar(termo), 300);
});

async function buscar(termo) {
    resultsDiv.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
    resultsDiv.style.display = '';
    try {
        const resp = await fetch(`${APP_BASE}/api/enderecos/pesquisar?q=${encodeURIComponent(termo)}`);
        const data = await resp.json();
        if (data.length === 0) {
            resultsDiv.innerHTML = '<div class="autocomplete-empty">Nenhum endereco encontrado</div>';
            return;
        }
        resultsDiv.innerHTML = data.map(item => `
            <button type="button" class="autocomplete-item" data-id="${item.id}" data-lat="${item.lat}" data-lng="${item.lng}" data-label="${item.tipo || ''} ${item.logradouro}, ${item.numero || 's/n'} - ${item.bairro} (${item.regional})">
                <span class="autocomplete-item-title">${item.tipo || ''} ${item.logradouro}, ${item.numero || 's/n'}</span>
                <span class="autocomplete-item-detail">${item.bairro} - ${item.regional}</span>
            </button>
        `).join('');

        resultsDiv.querySelectorAll('.autocomplete-item').forEach(btn => {
            btn.addEventListener('click', function() {
                selecionarEndereco(this.dataset.id, this.dataset.label, this.dataset.lat, this.dataset.lng);
            });
        });
    } catch (err) {
        resultsDiv.innerHTML = '<div class="autocomplete-error">Erro ao buscar</div>';
    }
}

window.selecionarEndereco = function(id, label, endLat, endLng) {
    hiddenId.value = id;
    selecionadoTexto.textContent = label;
    selecionadoDiv.classList.remove('hidden');
    selecionadoDiv.style.display = 'flex';
    resultsDiv.style.display = 'none';
    buscaInput.value = '';

    // Atualizar coordenadas e mapa para o endereço selecionado
    if (endLat && endLng) {
        latInput.value = parseFloat(endLat).toFixed(14);
        lngInput.value = parseFloat(endLng).toFixed(14);
        const pos = L.latLng(parseFloat(endLat), parseFloat(endLng));
        map.setView(pos, 18);
        if (marker) {
            marker.setLatLng(pos);
        } else {
            marker = L.marker(pos, { draggable: true }).addTo(map);
            marker.on('dragend', function(ev) {
                const p = ev.target.getLatLng();
                latInput.value = p.lat.toFixed(14);
                lngInput.value = p.lng.toFixed(14);
            });
        }
    }
};

window.limparEndereco = function() {
    hiddenId.value = '';
    selecionadoDiv.classList.add('hidden');
    selecionadoDiv.style.display = 'none';
};

document.addEventListener('click', function(e) {
    if (!buscaInput.contains(e.target) && !resultsDiv.contains(e.target)) {
        resultsDiv.style.display = 'none';
    }
});
});
