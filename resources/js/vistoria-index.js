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
        if (texto.length < 2) {
            hideResults();
            return;
        }

        searchTimeout = setTimeout(() => {
            buscarLogradouros(texto, numero);
        }, 300);
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
});
