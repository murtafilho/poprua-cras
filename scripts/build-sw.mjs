#!/usr/bin/env node
/**
 * Gera public/sw.js a partir de resources/js/sw/index.js.
 *
 * O Service Worker precisa compartilhar a política de sincronização com as
 * páginas (resources/js/offline/politica.js) — enquanto eram dois arquivos
 * mantidos à mão, divergiram e a divergência apagou fotos da fila.
 *
 * Por que empacotar em vez de `importScripts`: o navegador decide atualizar um
 * Service Worker comparando os bytes do próprio arquivo. Com a política
 * embutida, mudar a política muda o sw.js e a atualização acontece sozinha.
 * Com importScripts, a detecção dependeria do comportamento do navegador para
 * scripts importados — e uma falha aí deixaria o aparelho em campo com a
 * versão antiga da lógica que grava dados.
 *
 * Saída em formato IIFE clássico: Service Worker de módulo (type: 'module')
 * exigiria WebView recente, e o minSdk do app de campo é 23.
 */
import { build } from 'esbuild';
import { readFileSync, writeFileSync } from 'node:fs';

const ENTRADA = 'resources/js/sw/index.js';
const SAIDA = 'public/sw.js';

await build({
    entryPoints: [ENTRADA],
    outfile: SAIDA,
    bundle: true,
    format: 'iife',
    target: ['chrome80'],
    minify: false,
    legalComments: 'inline',
    banner: {
        js: `// ARQUIVO GERADO — não edite.\n// Fonte: ${ENTRADA} (política compartilhada em resources/js/offline/politica.js).\n// Regenerar: npm run build:sw\n`,
    },
});

// O CACHE_VERSION é lido de public/sw.js pela tela administrativa
// (App\Support\AppVersao::pwaCacheVersion). Falhar aqui é melhor do que
// descobrir depois que a versão do cache sumiu do arquivo publicado.
const gerado = readFileSync(SAIDA, 'utf8');
const versao = gerado.match(/(?:const|var|let)\s+CACHE_VERSION\s*=\s*(\d+)/);
if (!versao) {
    throw new Error('CACHE_VERSION não encontrado no sw.js gerado — AppVersao::pwaCacheVersion vai quebrar.');
}

// Normaliza a quebra de linha final para o arquivo ficar estável no git.
if (!gerado.endsWith('\n')) {
    writeFileSync(SAIDA, gerado + '\n');
}

console.log(`[sw] ${SAIDA} gerado a partir de ${ENTRADA} (CACHE_VERSION ${versao[1]})`);
