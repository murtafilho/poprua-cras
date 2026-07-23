import { describe, expect, it } from 'vitest';
import {
    DESTINO,
    REJEICAO_PERMANENTE,
    cabecalhos,
    classificarResposta,
    endpoint,
} from '../../resources/js/offline/politica.js';

/** Resposta mínima com o que a política olha. */
const resposta = ({ status = 200, redirected = false }) => ({
    status,
    ok: status >= 200 && status < 300,
    redirected,
});

describe('classificarResposta', () => {
    it('trata 200 como sucesso', () => {
        expect(classificarResposta(resposta({ status: 200 }), 'vistoria')).toBe(DESTINO.SUCESSO);
    });

    // A regressão que apagava fotos: sessão válida mas não autenticada devolve
    // 302 para /login, o fetch segue e entrega 200. Sem esta checagem o registro
    // era removido da fila sem nunca ter chegado ao servidor.
    it('trata 200 vindo de redirect como transiente, e não como sucesso', () => {
        const r = resposta({ status: 200, redirected: true });

        expect(classificarResposta(r, 'foto')).toBe(DESTINO.TRANSIENTE);
        expect(classificarResposta(r, 'vistoria')).toBe(DESTINO.TRANSIENTE);
        expect(classificarResposta(r, 'acao')).toBe(DESTINO.TRANSIENTE);
    });

    it('trata sessão perdida (401 e 419) como transiente, para o registro esperar novo login', () => {
        for (const status of [401, 419]) {
            for (const fluxo of ['vistoria', 'foto', 'acao']) {
                expect(classificarResposta(resposta({ status }), fluxo)).toBe(DESTINO.TRANSIENTE);
            }
        }
    });

    it('trata 5xx como transiente', () => {
        expect(classificarResposta(resposta({ status: 500 }), 'vistoria')).toBe(DESTINO.TRANSIENTE);
        expect(classificarResposta(resposta({ status: 503 }), 'acao')).toBe(DESTINO.TRANSIENTE);
    });

    it('manda para dead-letter os status em que reenviar não adianta', () => {
        expect(classificarResposta(resposta({ status: 422 }), 'vistoria')).toBe(DESTINO.PERMANENTE);
        expect(classificarResposta(resposta({ status: 409 }), 'vistoria')).toBe(DESTINO.PERMANENTE);
        expect(classificarResposta(resposta({ status: 403 }), 'vistoria')).toBe(DESTINO.PERMANENTE);
        expect(classificarResposta(resposta({ status: 404 }), 'acao')).toBe(DESTINO.PERMANENTE);
    });

    it('não dá dead-letter em 409 de ação, porque as ações são idempotentes', () => {
        expect(classificarResposta(resposta({ status: 409 }), 'acao')).toBe(DESTINO.TRANSIENTE);
    });

    it('nunca descarta foto: sem dead-letter enquanto não houver onde mostrar a falha', () => {
        expect(REJEICAO_PERMANENTE.foto).toEqual([]);
        expect(classificarResposta(resposta({ status: 422 }), 'foto')).toBe(DESTINO.TRANSIENTE);
    });
});

describe('cabecalhos', () => {
    // Sem Accept o Laravel redireciona para /login em vez de responder 401 —
    // era a diferença entre a cópia da página e a do Service Worker.
    it('sempre envia Accept: application/json', () => {
        expect(cabecalhos().Accept).toBe('application/json');
        expect(cabecalhos({ csrf: 'a' }).Accept).toBe('application/json');
        expect(cabecalhos({ xsrf: 'b', json: true }).Accept).toBe('application/json');
    });

    it('usa X-CSRF-TOKEN na página e X-XSRF-TOKEN no service worker', () => {
        expect(cabecalhos({ csrf: 'da-meta' })['X-CSRF-TOKEN']).toBe('da-meta');
        expect(cabecalhos({ csrf: 'da-meta' })['X-XSRF-TOKEN']).toBeUndefined();
        expect(cabecalhos({ xsrf: 'do-cookie' })['X-XSRF-TOKEN']).toBe('do-cookie');
        expect(cabecalhos({ xsrf: 'do-cookie' })['X-CSRF-TOKEN']).toBeUndefined();
    });

    it('só declara Content-Type quando o corpo é JSON (FormData define o seu)', () => {
        expect(cabecalhos()['Content-Type']).toBeUndefined();
        expect(cabecalhos({ json: true })['Content-Type']).toBe('application/json');
    });
});

describe('endpoint', () => {
    it('monta as rotas da fila a partir da base', () => {
        expect(endpoint('', 'vistoria')).toBe('/api/vistorias');
        expect(endpoint('https://x/sub', 'foto')).toBe('https://x/sub/api/vistorias/fotos');
        expect(endpoint('https://x/sub', 'acao', { vistoriaId: 7, acao: 'finalizar' }))
            .toBe('https://x/sub/api/vistorias/7/finalizar');
    });

    // O service worker deriva a base do scope, que termina em barra; a página
    // deriva da meta app-base, que não termina. As duas têm que dar no mesmo.
    it('ignora barra final da base, venha do scope ou da meta', () => {
        expect(endpoint('https://x/sub/', 'vistoria')).toBe(endpoint('https://x/sub', 'vistoria'));
    });

    it('recusa fluxo desconhecido em vez de montar uma URL errada', () => {
        expect(() => endpoint('', 'inexistente')).toThrow(/fluxo desconhecido/);
    });
});
