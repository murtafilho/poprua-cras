#!/usr/bin/env node
/**
 * Captura telas do POPRUA CRAS para o guia visual de homologação.
 * Uso: node scripts/capture-homologacao-screenshots.mjs
 */
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'fs/promises';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, '..');
const OUT_DIR = join(ROOT, 'docs', 'homologacao', 'img');
const BASE = process.env.HOMOLOG_BASE_URL || 'http://127.0.0.1:8088';
const EMAIL = process.env.HOMOLOG_EMAIL || 'murtafilho@gmail.com';
const PASSWORD = process.env.HOMOLOG_PASSWORD || 'xman74102';

const pages = [
    { id: '01-login', path: '/login', auth: false, title: 'Tela de login', wait: 500 },
    { id: '02-mapa', path: '/mapa', auth: true, title: 'Mapa de campo', wait: 3000 },
    { id: '03-vistorias', path: '/vistorias', auth: true, title: 'Listagem de zeladorias', wait: 1500 },
    { id: '04-minhas-vistorias', path: '/minhas-vistorias', auth: true, title: 'Minhas zeladorias', wait: 1500 },
    {
        id: '05-nova-zeladoria',
        path: '/vistorias/create?lat=-19.9135&lng=-43.9514',
        auth: true,
        title: 'Nova zeladoria (formulário)',
        wait: 2500,
    },
    { id: '06-moradores', path: '/moradores', auth: true, title: 'Listagem de moradores', wait: 1500 },
    { id: '07-pontos', path: '/pontos', auth: true, title: 'Listagem de pontos', wait: 1500 },
    { id: '08-minha-equipe', path: '/minha-equipe', auth: true, title: 'Minha equipe', wait: 1000 },
    { id: '09-dashboard', path: '/dashboard', auth: true, title: 'Dashboard de gestão', wait: 2000 },
];

async function login(page) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
}

async function capture() {
    await mkdir(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        viewport: { width: 390, height: 844 },
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
    });
    const page = await context.newPage();

    const captured = [];

    for (const spec of pages) {
        if (spec.auth) {
            const url = page.url();
            if (!url || url.includes('/login')) {
                await login(page);
            }
        }

        await page.goto(`${BASE}${spec.path}`, { waitUntil: 'networkidle' });
        await page.waitForTimeout(spec.wait);

        const filename = `${spec.id}.png`;
        const filepath = join(OUT_DIR, filename);
        await page.screenshot({ path: filepath, fullPage: false });

        captured.push({
            id: spec.id,
            file: `img/${filename}`,
            title: spec.title,
            path: spec.path,
        });

        console.log(`OK ${filename}`);
    }

    // Detalhe de zeladoria (primeira da listagem)
    await page.goto(`${BASE}/vistorias`, { waitUntil: 'networkidle' });
    const link = page.locator('a[href*="/vistorias/"]').first();
    if (await link.count()) {
        await link.click();
        await page.waitForTimeout(2000);
        const filename = '10-detalhe-zeladoria.png';
        await page.screenshot({ path: join(OUT_DIR, filename), fullPage: false });
        captured.push({
            id: '10-detalhe-zeladoria',
            file: `img/${filename}`,
            title: 'Detalhe da zeladoria',
            path: '(primeira da listagem)',
        });
        console.log(`OK ${filename}`);
    }

    await browser.close();
    return captured;
}

function buildHtml(shots) {
    const generated = new Date().toLocaleString('pt-BR', { timeZone: 'America/Sao_Paulo' });
    const cards = shots
        .map(
            (s) => `
    <section class="card" id="${s.id}">
      <div class="card-header">
        <span class="badge">${s.id.replace(/^\d+-/, '').replace(/-/g, ' ')}</span>
        <h2>${s.title}</h2>
        <p class="route">${s.path}</p>
      </div>
      <figure>
        <img src="${s.file}" alt="${s.title}" loading="lazy" />
        <figcaption>${s.title}</figcaption>
      </figure>
    </section>`,
        )
        .join('\n');

    return `<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>POPRUA CRAS — Guia visual de homologação</title>
  <style>
    :root {
      --bg: #0f1419;
      --surface: #1a2332;
      --border: #2d3a4f;
      --text: #e8edf4;
      --muted: #8b9cb3;
      --accent: #3d9aed;
      --accent-dim: #2563a8;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: "Segoe UI", system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      padding: 1.5rem;
    }
    header {
      max-width: 1200px;
      margin: 0 auto 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
    header p { color: var(--muted); max-width: 60ch; }
    .meta { margin-top: 1rem; font-size: 0.85rem; color: var(--muted); }
    nav.toc {
      max-width: 1200px;
      margin: 0 auto 2rem;
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    nav.toc a {
      color: var(--accent);
      text-decoration: none;
      font-size: 0.8rem;
      padding: 0.35rem 0.65rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      background: var(--surface);
    }
    nav.toc a:hover { border-color: var(--accent); }
    main {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }
    .card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
    }
    .card-header { padding: 1rem 1rem 0.5rem; }
    .badge {
      display: inline-block;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--accent);
      margin-bottom: 0.35rem;
    }
    .card h2 { font-size: 1.1rem; font-weight: 600; }
    .route { font-size: 0.75rem; color: var(--muted); font-family: monospace; margin-top: 0.25rem; }
    figure { margin: 0; }
    figure img {
      width: 100%;
      display: block;
      border-top: 1px solid var(--border);
      background: #000;
    }
    figcaption {
      padding: 0.65rem 1rem;
      font-size: 0.8rem;
      color: var(--muted);
      text-align: center;
    }
    footer {
      max-width: 1200px;
      margin: 3rem auto 1rem;
      padding-top: 1rem;
      border-top: 1px solid var(--border);
      font-size: 0.8rem;
      color: var(--muted);
      text-align: center;
    }
    @media print {
      body { background: #fff; color: #111; }
      .card { break-inside: avoid; border-color: #ccc; }
      nav.toc { display: none; }
    }
  </style>
</head>
<body>
  <header>
    <h1>POPRUA CRAS — Guia visual</h1>
    <p>Capturas de tela do sistema para apoio à homologação pelos usuários. Complementa o
       <em>Manual de Homologação</em> (<code>docs/MANUAL_HOMOLOGACAO.md</code>).</p>
    <p class="meta">Gerado em ${generated} · Viewport mobile 390×844 · Ambiente: ${BASE}</p>
  </header>
  <nav class="toc" aria-label="Índice">
    ${shots.map((s) => `<a href="#${s.id}">${s.title}</a>`).join('\n    ')}
  </nav>
  <main>
${cards}
  </main>
  <footer>
  PBH · Secretaria Municipal de Política Urbana — POPRUA CRAS
  </footer>
</body>
</html>`;
}

const shots = await capture();
const htmlPath = join(ROOT, 'docs', 'homologacao', 'guia-visual.html');
await writeFile(htmlPath, buildHtml(shots), 'utf8');
console.log(`\nHTML: ${htmlPath}`);
console.log(`Imagens: ${OUT_DIR}`);
