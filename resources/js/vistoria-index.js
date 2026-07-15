import { getSyncableVistorias } from './offline-vistoria';

document.addEventListener('DOMContentLoaded', function() {
const APP_BASE = document.querySelector('meta[name="app-base"]')?.content || '';
const searchInput = document.getElementById('search-endereco');
const searchResults = document.getElementById('search-results');
const hiddenEndereco = document.getElementById('hidden-endereco');
const hiddenNumero = document.getElementById('hidden-numero-endereco');
const form = document.getElementById('form-busca-endereco');
let searchTimeout = null;
let selectedLogradouro = null;

function parseEnderecoInput(valor) {
    const trimmed = valor.trim();
    const matchVirgula = trimmed.match(/^(.+?),\s*(\d+)\s*(?:-.*)?$/);
    if (matchVirgula) {
        return { texto: matchVirgula[1].trim(), numero: parseInt(matchVirgula[2]) };
    }
    const matchVirgulaSemNum = trimmed.match(/^(.+?),\s*(?:-.*)?$/);
    if (matchVirgulaSemNum) {
        return { texto: matchVirgulaSemNum[1].trim(), numero: null };
    }
    const match = trimmed.match(/^(.+?)[\s]+(\d+)\s*$/);
    if (match) {
        return { texto: match[1].trim(), numero: parseInt(match[2]) };
    }
    return { texto: trimmed, numero: null };
}

function extrairLogradouro(texto) {
    return texto.replace(/^(AVE|RUA|PCA|ALA|TRV|BEC|PRC|VIA|ROD|EST|LAD)\s+/i, '');
}

function showResults() { searchResults.style.display = ''; }
function hideResults() { searchResults.style.display = 'none'; }

if (searchInput && searchResults) {
    searchInput.addEventListener('input', function() {
        if (searchTimeout) clearTimeout(searchTimeout);

        if (selectedLogradouro) {
            const prefixo = `${selectedLogradouro.tipo} ${selectedLogradouro.logradouro},`;
            if (!this.value.startsWith(prefixo)) {
                selectedLogradouro = null;
            } else {
                hideResults();
                return;
            }
        }

        const { texto, numero } = parseEnderecoInput(this.value);
        // Minimo 3 caracteres para buscar (reduz carga no banco)
        if (texto.length < 3) {
            hideResults();
            return;
        }

        // Debounce aumentado para 400ms (menos requisicoes)
        searchTimeout = setTimeout(() => {
            buscarLogradouros(texto, numero);
        }, 400);
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            hideResults();
            submeterBusca();
        }
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            hideResults();
        }
    });
}

async function buscarLogradouros(termo, numero = null) {
    try {
        searchResults.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
        showResults();

        const termoBusca = extrairLogradouro(termo);
        let url = `${APP_BASE}/api/vistorias/logradouros?q=${encodeURIComponent(termoBusca)}`;
        if (numero) url += `&numero=${numero}`;

        const response = await fetch(url);
        const resultados = await response.json();

        if (resultados.length === 0) {
            searchResults.innerHTML = '<div class="autocomplete-empty">Nenhuma vistoria encontrada</div>';
        } else {
            searchResults.innerHTML = resultados.map(item => {
                const tipo = item.tipo || '';
                const logr = item.logradouro || '';
                const reg = item.regional || '';
                const num = item.numero || '';
                const label = `${tipo} ${logr}, ${num} - ${reg}`;
                return `
                    <button type="button" class="autocomplete-item" data-tipo="${tipo}" data-logradouro="${logr}" data-regional="${reg}" data-numero="${num}">
                        <span class="autocomplete-item-title">${label}</span>
                    </button>
                `;
            }).join('');

            searchResults.querySelectorAll('.autocomplete-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const logradouro = this.dataset.logradouro;
                    const num = this.dataset.numero;
                    hideResults();
                    hiddenEndereco.value = logradouro;
                    hiddenNumero.value = num;
                    searchInput.value = this.querySelector('.autocomplete-item-title').textContent;
                    form.submit();
                });
            });
        }
    } catch (err) {
        console.error('Erro na busca:', err);
        searchResults.innerHTML = '<div class="autocomplete-error">Erro ao buscar</div>';
    }
}

function submeterBusca() {
    const { texto, numero } = parseEnderecoInput(searchInput.value);
    if (!texto) return;

    const logradouro = selectedLogradouro
        ? selectedLogradouro.logradouro
        : extrairLogradouro(texto);

    hiddenEndereco.value = logradouro;
    hiddenNumero.value = numero || '';
    form.submit();
}

/** Fatia 3: injeta linhas da outbox só em Minhas Zeladorias. */
async function injectPendentesOffline() {
    const path = window.location.pathname.replace(/\/+$/, '');
    if (!path.endsWith('/minhas-vistorias')) return;

    const tbody = document.querySelector('.vistorias-table tbody');
    if (!tbody) return;

    let pendentes = [];
    try {
        pendentes = await getSyncableVistorias();
    } catch {
        return;
    }
    if (!pendentes.length) return;

    // Remove empty-state se existir (lista só com outbox local).
    const emptyRow = tbody.querySelector('.empty-state')?.closest('tr');
    if (emptyRow) emptyRow.remove();

    const frag = document.createDocumentFragment();
    for (const record of pendentes.sort((a, b) =>
        String(b.created_at || '').localeCompare(String(a.created_at || ''))
    )) {
        const p = record.payload || {};
        const display = record.display || {};
        const rawDate = p.data_abordagem || record.created_at;
        let dataHtml = '—';
        if (rawDate) {
            const d = new Date(rawDate);
            if (!Number.isNaN(d.getTime())) {
                const dd = String(d.getDate()).padStart(2, '0');
                const mm = String(d.getMonth() + 1).padStart(2, '0');
                const yyyy = d.getFullYear();
                const hh = String(d.getHours()).padStart(2, '0');
                const mi = String(d.getMinutes()).padStart(2, '0');
                dataHtml = `<span style="font-weight: var(--font-semibold);">${dd}/${mm}/${yyyy}</span>`
                    + (hh !== '00' || mi !== '00'
                        ? `<span class="text-muted" style="font-size: var(--text-xs); display: block;">${hh}:${mi}</span>`
                        : '');
            }
        }

        const endereco = display.endereco_label
            || (p.lat != null && p.lng != null
                ? `Lat ${Number(p.lat).toFixed(5)} · Lng ${Number(p.lng).toFixed(5)}`
                : 'Pendente de envio');
        const tipo = display.tipo_label || '—';
        const pessoas = p.quantidade_pessoas || '—';

        const tr = document.createElement('tr');
        tr.className = 'vistoria-pendente-offline';
        tr.dataset.pendingId = String(record.id);
        tr.innerHTML = `
            <td style="white-space: nowrap;">${dataHtml}</td>
            <td class="col-endereco">
                <span style="font-weight: var(--font-medium); display: block;">${escapeHtml(endereco)}</span>
                <span class="text-muted" style="font-size: var(--text-xs);">Salva no aparelho</span>
            </td>
            <td class="hide-mobile"><span class="text-muted" style="font-size: var(--text-sm);">Você</span></td>
            <td class="hide-mobile"><span class="badge badge-secondary">${escapeHtml(tipo)}</span></td>
            <td style="white-space: nowrap;"><span class="badge badge-warning">Pendente de envio</span></td>
            <td class="hide-mobile"><span class="text-muted">—</span></td>
            <td class="hide-mobile"><span class="text-muted">—</span></td>
            <td class="hide-mobile text-center">${escapeHtml(String(pessoas))}</td>
            <td class="hide-mobile text-center"><span class="text-muted" style="font-size: var(--text-xs);">Aguarda sync</span></td>
        `;
        frag.appendChild(tr);
    }
    tbody.prepend(frag);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

injectPendentesOffline();
});
