import { initDatePtBr } from './date-ptbr';

function nowDatetimeLocal() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());

    return d.toISOString().slice(0, 16);
}

function todayDateLocal() {
    return nowDatetimeLocal().slice(0, 10);
}

function getDataAbordagemValue() {
    return document.querySelector('[name="data_abordagem"]')?.value || '';
}

function syncFlatpickrValue(input) {
    if (input?._flatpickr && input.value) {
        input._flatpickr.setDate(input.value, false);
    }
}

function preencherDatasComunicadoZeladoria(container) {
    const dataComunicado = container.querySelector('[name="data_comunicado"]');
    const dataPrevista = container.querySelector('[name="data_prevista_zeladoria"]');

    if (dataComunicado && !dataComunicado.value) {
        dataComunicado.value = getDataAbordagemValue() || nowDatetimeLocal();
    }

    if (dataPrevista && !dataPrevista.value) {
        const abordagem = getDataAbordagemValue();
        dataPrevista.value = abordagem ? abordagem.slice(0, 10) : todayDateLocal();
    }

    initDatePtBr(container);
    syncFlatpickrValue(dataComunicado);
    syncFlatpickrValue(dataPrevista);
}

function limparDatasComunicadoZeladoria(container) {
    const dataComunicado = container.querySelector('[name="data_comunicado"]');
    const dataPrevista = container.querySelector('[name="data_prevista_zeladoria"]');
    const periodo = container.querySelector('[name="periodo_zeladoria"]');

    if (dataComunicado) {
        dataComunicado.value = '';
        dataComunicado._flatpickr?.clear();
    }
    if (dataPrevista) {
        dataPrevista.value = '';
        dataPrevista._flatpickr?.clear();
    }
    if (periodo) {
        periodo.value = '';
    }
}

/**
 * @param {() => boolean} shouldShow
 */
export function updateComunicadoZeladoriaCampos(shouldShow) {
    const container = document.getElementById('comunicado-zeladoria-campos');
    if (!container) {
        return;
    }

    const show = shouldShow();
    container.classList.toggle('hidden', !show);

    if (show) {
        preencherDatasComunicadoZeladoria(container);
    } else {
        limparDatasComunicadoZeladoria(container);
    }
}
