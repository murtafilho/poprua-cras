import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

document.addEventListener('DOMContentLoaded', function() {
const dados = window.DASHBOARD_DADOS;

const meses = dados.map(d => {
    const [ano, mes] = d.mes.split('-');
    const nomes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    return nomes[parseInt(mes) - 1] + '/' + ano.slice(2);
});

// Datasets com todas as series
const allDatasets = {
    ativos: {
        label: 'Pontos Ativos',
        data: dados.map(d => d.ativos),
        borderColor: '#8b5cf6',
        backgroundColor: 'rgba(139, 92, 246, 0.1)',
        borderWidth: 3,
        pointRadius: 2,
        tension: 0.3,
        fill: true,
        order: 0,
    },
    persiste: {
        label: 'Persiste',
        data: dados.map(d => d.persiste),
        borderColor: '#ef4444',
        backgroundColor: '#ef4444',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        order: 1,
    },
    impactado_parcial: {
        label: 'Impactado Parcialmente',
        data: dados.map(d => d.impactado_parcial),
        borderColor: '#f59e0b',
        backgroundColor: '#f59e0b',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        order: 2,
    },
    deixou_ocorrer: {
        label: 'Deixou de Ocorrer (extinto)',
        data: dados.map(d => d.deixou_ocorrer),
        borderColor: '#22c55e',
        backgroundColor: '#22c55e',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        order: 3,
    },
    ausente: {
        label: 'PSR Ausente',
        data: dados.map(d => d.ausente),
        borderColor: '#94a3b8',
        backgroundColor: '#94a3b8',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        order: 4,
    },
    nao_constatado: {
        label: 'Nao Constatado (extinto)',
        data: dados.map(d => d.nao_constatado),
        borderColor: '#3b82f6',
        backgroundColor: '#3b82f6',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        order: 5,
    },
    conformidade: {
        label: 'Em Conformidade',
        data: dados.map(d => d.conformidade),
        borderColor: '#10b981',
        backgroundColor: '#10b981',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.3,
        borderDash: [5, 5],
        order: 6,
    },
    sem_vistoria: {
        label: 'Sem Vistoria',
        data: dados.map(d => d.sem_vistoria),
        borderColor: '#cbd5e1',
        backgroundColor: '#cbd5e1',
        borderWidth: 1,
        pointRadius: 1,
        tension: 0.3,
        borderDash: [3, 3],
        order: 7,
    },
};

const ctx = document.getElementById('chart-evolucao').getContext('2d');

const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: meses,
        datasets: Object.values(allDatasets),
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    pointStyle: 'line',
                    padding: 16,
                    font: { size: 11 },
                },
            },
            tooltip: {
                callbacks: {
                    afterBody: function(items) {
                        const idx = items[0].dataIndex;
                        const d = dados[idx];
                        return [
                            '',
                            'Pontos existentes: ' + d.total_existentes,
                            'Extintos (-): ' + d.extintos,
                            'Total efetivo: ' + d.total_efetivo,
                            '',
                            'Ativos: ' + d.ativos,
                            'Sem vistoria: ' + d.sem_vistoria,
                        ];
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.06)' },
                ticks: { font: { size: 11 } },
                title: {
                    display: true,
                    text: 'Pontos',
                    font: { size: 11 },
                },
            },
            x: {
                grid: { display: false },
                ticks: { font: { size: 10 }, maxRotation: 45 },
            },
        },
    },
});

// Filtros
const filterButtons = document.querySelectorAll('.chart-filter');

filterButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        const series = this.dataset.series;

        // Toggle active
        filterButtons.forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        if (series === 'all') {
            // Mostrar todos
            chart.data.datasets = Object.values(allDatasets);
        } else {
            // Mostrar apenas a serie selecionada + ativos como referencia
            const selected = [allDatasets[series]];
            if (series !== 'ativos') {
                selected.unshift(allDatasets.ativos);
            }
            chart.data.datasets = selected;
        }
        chart.update();
    });
});
});
