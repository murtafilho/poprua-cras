/**
 * SIZEM — política de sincronização offline.
 *
 * Este módulo existe porque a mesma sincronização roda em dois lugares: nas
 * páginas (quando o agente volta a ter sinal com o app aberto) e no Service
 * Worker (Background Sync, com o app fechado). As duas implementações eram
 * cópias mantidas à mão e divergiram: faltava `Accept: application/json` só na
 * cópia do Service Worker, e sem esse cabeçalho a sessão não autenticada
 * respondia com redirect para /login — que o fetch segue, recebe 200, e a foto
 * era apagada da fila sem nunca ter sido enviada.
 *
 * O que decide o destino de um registro (para onde enviar, com quais
 * cabeçalhos, e o que fazer com a resposta) mora aqui e só aqui. O
 * armazenamento continua em cada lado, porque IndexedDB é a parte que muda
 * pouco e cujo código é mecânico.
 */

/**
 * Status em que o servidor está dizendo "não adianta reenviar": dado inválido,
 * duplicidade ou falta de autorização. Vão para dead-letter em vez de ficarem
 * retentando para sempre.
 *
 * 401 e 419 ficam de fora de propósito: significam sessão perdida, não dado
 * ruim — o registro tem de esperar o novo login.
 */
export const REJEICAO_PERMANENTE = {
    vistoria: [422, 409, 403],
    // Ações são idempotentes (reenviar uma já aplicada devolve 200), então 409
    // não é permanente aqui; 404 é, porque a vistoria não existe mais.
    acao: [403, 404, 422],
    // Fotos ainda não têm dead-letter: não há status 'failed' no banco de fotos
    // nem lugar na interface para mostrá-lo. Enquanto não houver, retentar é
    // preferível a descartar — o custo de uma foto perdida é alto.
    foto: [],
};

export const DESTINO = {
    SUCESSO: 'sucesso',
    PERMANENTE: 'permanente',
    TRANSIENTE: 'transiente',
};

/**
 * Decide o que fazer com a resposta de um envio.
 *
 * A verificação de `redirected` vem antes de `ok` porque é justamente o caso
 * perigoso: sessão válida mas não autenticada devolve 302 para /login, o fetch
 * segue o redirect e entrega um 200 que não significa nada. Tratar como
 * transiente mantém o registro na fila até o agente entrar de novo.
 */
export function classificarResposta(resp, fluxo) {
    if (resp.redirected) {
        return DESTINO.TRANSIENTE;
    }
    if (resp.ok) {
        return DESTINO.SUCESSO;
    }
    if ((REJEICAO_PERMANENTE[fluxo] ?? []).includes(resp.status)) {
        return DESTINO.PERMANENTE;
    }

    return DESTINO.TRANSIENTE;
}

/**
 * Cabeçalhos de um envio.
 *
 * `Accept: application/json` não é cosmético: é ele que faz o Laravel responder
 * 401 em vez de redirecionar para a tela de login quando a sessão não está
 * autenticada. Sem ele, o envio "dá certo" e o dado se perde.
 *
 * O token vai como X-CSRF-TOKEN quando veio da meta tag da página, ou como
 * X-XSRF-TOKEN quando veio do cookie — é a única diferença legítima entre os
 * dois ambientes, porque o Service Worker não tem DOM para ler a meta.
 */
export function cabecalhos({ csrf = null, xsrf = null, json = false } = {}) {
    const headers = { Accept: 'application/json' };

    if (json) {
        headers['Content-Type'] = 'application/json';
    }
    if (csrf) {
        headers['X-CSRF-TOKEN'] = csrf;
    }
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    return headers;
}

/** Endpoints da fila offline, a partir da base da aplicação (sem barra final). */
export function endpoint(base, fluxo, params = {}) {
    const raiz = String(base ?? '').replace(/\/+$/, '');

    switch (fluxo) {
        case 'vistoria':
            return `${raiz}/api/vistorias`;
        case 'foto':
            return `${raiz}/api/vistorias/fotos`;
        case 'acao':
            return `${raiz}/api/vistorias/${params.vistoriaId}/${params.acao}`;
        default:
            throw new Error(`fluxo desconhecido: ${fluxo}`);
    }
}
