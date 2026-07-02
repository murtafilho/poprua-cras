import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

function readPayload() {
    const el = document.getElementById('stack-projecao-data');
    if (!el) {
        return null;
    }

    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

function initStackProjecaoCharts() {
    const data = readPayload();
    if (!data) {
        return;
    }

    Chart.defaults.font.family = 'Outfit, system-ui, sans-serif';
    Chart.defaults.font.size = 12;

    const semLabels = data.vistoriasSemestre.map((r) => r.semestre);
    const legado = data.vistoriasSemestre.map((r) => (r.origem === 'legado' ? r.total : null));
    const sizem = data.vistoriasSemestre.map((r) => (r.origem === 'sizem' ? r.total : null));

    mountChart('chartVistorias', {
        type: 'bar',
        data: {
            labels: semLabels,
            datasets: [
                { label: 'Legado Geo', data: legado, backgroundColor: '#6b7280', borderRadius: 3 },
                { label: 'SIZEM orgânico', data: sizem, backgroundColor: '#184186', borderRadius: 3 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: 'Vistorias por semestre' } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        },
    });

    mountChart('chartVistoriasMes', {
        type: 'line',
        data: {
            labels: data.vistoriasMensalPosEtl.map((r) => r.mes),
            datasets: [{
                label: 'Vistorias SIZEM (pós-ETL)',
                data: data.vistoriasMensalPosEtl.map((r) => r.total),
                borderColor: '#184186',
                backgroundColor: 'rgba(24,65,134,.12)',
                fill: true,
                tension: 0.3,
                pointRadius: 5,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: 'Zeladorias/mês — operação SIZEM (desde 08/01/2026)' } },
            scales: { y: { beginAtZero: true } },
        },
    });

    mountChart('chartPontos', {
        type: 'bar',
        data: {
            labels: data.pontosSemestre.map((r) => r.semestre),
            datasets: [
                { label: 'Cadastro orgânico', data: data.pontosSemestre.map((r) => r.organico), backgroundColor: '#006633', borderRadius: 3 },
                { label: 'Migração ETL', data: data.pontosSemestre.map((r) => r.etl || null), backgroundColor: '#9333ea', borderRadius: 3 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: 'Pontos cadastrados por semestre' } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
        },
    });

    const distLabels = data.fotoDistribuicao.map((r) => `${r.faixa} fotos`);
    mountChart('chartDistFotos', {
        type: 'bar',
        data: {
            labels: distLabels,
            datasets: [{
                label: 'Vistorias',
                data: data.fotoDistribuicao.map((r) => r.total),
                backgroundColor: data.fotoDistribuicao.map((r) => (['10', '11–15', '16+'].includes(r.faixa) ? '#006633' : '#6b7280')),
                borderRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: true, text: 'Distribuição de fotos por vistoria (situação atual)' },
                legend: { display: false },
            },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Nº de vistorias' } } },
        },
    });

    mountChart('chartFotosMes', {
        type: 'bar',
        data: {
            labels: data.fotosMes.map((r) => r.mes),
            datasets: [{
                label: 'Fotografias registradas',
                data: data.fotosMes.map((r) => r.total),
                backgroundColor: '#184186',
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: 'Volume de fotografias/mês' } },
            scales: { y: { beginAtZero: true } },
        },
    });

    mountChart('chartProjFotos', {
        type: 'bar',
        data: {
            labels: data.projecao.map((r) => r.label),
            datasets: [
                { label: 'Fotos de vistorias', data: data.projecao.map((r) => r.fotosVistorias), backgroundColor: '#184186', borderRadius: 3 },
                { label: 'Fotos de moradores', data: data.projecao.map((r) => r.fotosMoradores), backgroundColor: '#006633', borderRadius: 3 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { title: { display: true, text: 'Projeção anual de fotografias (5 anos — cenário referência)' } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Fotografias/ano' } } },
        },
    });
}

function mountChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) {
        return;
    }

    // eslint-disable-next-line no-new
    new Chart(canvas, config);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStackProjecaoCharts);
} else {
    initStackProjecaoCharts();
}
