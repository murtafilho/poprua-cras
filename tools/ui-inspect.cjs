#!/usr/bin/env node
/**
 * ui-inspect — mede as caixas de uma tela e tira screenshot, sem precisar
 * escrever script novo a cada investigação de layout.
 *
 * Dois alvos:
 *   browser  navegador headless num viewport de celular (rápido, mas env(safe-area-*) = 0)
 *   webview  o WebView do app no emulador/aparelho, com os insets reais do Android
 *
 * Uso:
 *   node tools/ui-inspect.cjs /mapa
 *   node tools/ui-inspect.cjs /mapa --alvo=webview
 *   node tools/ui-inspect.cjs /vistorias --sel=".mobile-header,#map,.bottom-nav" --png=/tmp/x.png
 *   node tools/ui-inspect.cjs /mapa --css=public/build/assets/app-XXXX.css   # injeta CSS local no WebView
 *
 * Para o alvo webview, exponha o devtools antes (o APK debug já permite):
 *   adb forward tcp:9222 localabstract:$(adb shell cat /proc/net/unix \
 *     | grep -o 'webview_devtools_remote_[0-9]*' | head -1)
 */
const { chromium } = require('playwright');
const fs = require('fs');

const args = process.argv.slice(2);
const rota = args.find((a) => !a.startsWith('--')) || '/mapa';
const opt = (nome, padrao) => {
    const a = args.find((x) => x.startsWith(`--${nome}=`));
    return a ? a.slice(nome.length + 3) : padrao;
};

const ALVO = opt('alvo', 'browser');
const APP = opt('app', process.env.APP_URL || 'http://localhost:8088');
const SELS = opt('sel', '.mobile-header,main.page,#map,.bottom-nav,.sidebar').split(',');
const PNG = opt('png', `/tmp/ui-${rota.replace(/\W+/g, '-')}-${ALVO}.png`);
const CSS = opt('css', null);
const EMAIL = process.env.EMAIL || 'murtafilho@gmail.com';
const SENHA = process.env.SENHA || 'xman74102';

const medir = (sels) => {
    const caixa = (sel) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        const r = el.getBoundingClientRect();
        const cs = getComputedStyle(el);
        return {
            top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), h: +r.height.toFixed(1),
            pos: cs.position, z: cs.zIndex,
        };
    };
    const sonda = (prop) => {
        const d = document.createElement('div');
        d.style.cssText = `position:fixed;visibility:hidden;padding-top:env(${prop})`;
        document.body.appendChild(d);
        const v = getComputedStyle(d).paddingTop;
        d.remove();
        return v;
    };
    const out = {
        _viewport: { w: innerWidth, h: innerHeight, dpr: devicePixelRatio },
        _insets: { top: sonda('safe-area-inset-top'), bottom: sonda('safe-area-inset-bottom') },
        _bodyClass: document.body.className,
    };
    sels.forEach((s) => { out[s] = caixa(s.trim()); });
    return out;
};

(async () => {
    let browser, page, fecharContexto = false;

    if (ALVO === 'webview') {
        browser = await chromium.connectOverCDP('http://localhost:9222');
        const paginas = browser.contexts()[0].pages();
        page = paginas.find((p) => p.url().includes(rota)) || paginas[0];
        console.log('# webview:', page.url());
    } else {
        browser = await chromium.launch();
        const ctx = await browser.newContext({
            viewport: { width: 360, height: 640 },
            deviceScaleFactor: 2,
            isMobile: true,
            hasTouch: true,
            userAgent: 'Mozilla/5.0 (Linux; Android 14; SM-A166M) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Mobile Safari/537.36',
        });
        fecharContexto = true;
        page = await ctx.newPage();
        await page.goto(`${APP}/login`);
        if (page.url().includes('login')) {
            await page.fill('input[name="email"]', EMAIL);
            await page.fill('input[name="password"]', SENHA);
            await page.click('button[type="submit"]');
            await page.waitForLoadState('networkidle');
        }
        await page.goto(`${APP}${rota}`);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        console.log('# browser 360x640:', page.url());
    }

    if (CSS) {
        await page.addStyleTag({ content: fs.readFileSync(CSS, 'utf8') });
        await page.waitForTimeout(600);
        console.log('# css injetado:', CSS);
    }

    const m = await page.evaluate(medir, SELS);
    const larg = Math.max(...Object.keys(m).map((k) => k.length));
    for (const [k, v] of Object.entries(m)) {
        if (k.startsWith('_')) { console.log(`${k.padEnd(larg)}  ${JSON.stringify(v)}`); continue; }
        console.log(v
            ? `${k.padEnd(larg)}  top=${String(v.top).padStart(6)}  bottom=${String(v.bottom).padStart(6)}  h=${String(v.h).padStart(6)}  ${v.pos}`
            : `${k.padEnd(larg)}  (ausente)`);
    }

    // Vãos entre elementos consecutivos que existem, na ordem informada.
    const presentes = SELS.map((s) => [s, m[s]]).filter(([, v]) => v).sort((a, b) => a[1].top - b[1].top);
    for (let i = 0; i < presentes.length - 1; i++) {
        const [sa, a] = presentes[i], [sb, b] = presentes[i + 1];
        const vao = +(b.top - a.bottom).toFixed(1);
        if (vao !== 0 && a.pos !== 'static' && b.pos !== 'static') {
            console.log(`# vao ${sa} -> ${sb}: ${vao}px`);
        }
    }

    await page.screenshot({ path: PNG });
    console.log('# screenshot:', PNG);
    if (fecharContexto) await browser.close(); else await browser.close();
})();
