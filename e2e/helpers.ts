import { Page, expect } from '@playwright/test';
import path from 'path';

/** Base completa do app (inclui o subpath /ginfi/.../public). Usar SEMPRE URLs
 *  completas — paths com '/' inicial descartariam o subpath ao resolver contra baseURL. */
export const BASE = (process.env.E2E_BASE_URL || 'http://localhost:8088').replace(/\/+$/, '');

export const AUTH_DIR = path.join(__dirname, '.auth');
export const ADMIN_STATE = path.join(AUTH_DIR, 'admin.json');
export const AGENTE_STATE = path.join(AUTH_DIR, 'agente.json');

export const CREDS = {
  admin: {
    email: process.env.E2E_ADMIN_EMAIL || 'admin@teste.local',
    pass: process.env.E2E_ADMIN_PASS || 'Cras@2026',
  },
  agente: {
    email: process.env.E2E_AGENTE_EMAIL || 'agente.campo@teste.local',
    pass: process.env.E2E_AGENTE_PASS || 'Cras@2026',
  },
};

export const TAG = '[HOMOLOG-E2E]';

/** PNG 1x1 valido (gerado em memoria — sem fixture binaria no repo). */
const PNG_1X1_B64 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';
export function fotoPayload(name = 'e2e-foto.png') {
  return { name, mimeType: 'image/png', buffer: Buffer.from(PNG_1X1_B64, 'base64') };
}

/** Faz login pelo formulario Breeze e confirma que saiu de /login. */
export async function login(page: Page, email: string, pass: string): Promise<void> {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', pass);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('button[type=submit]'),
  ]);
  await expect(page, 'login deveria redirecionar para fora de /login').not.toHaveURL(/\/login(\?|$)/);
}

/** Extrai um token CSRF de qualquer pagina autenticada (meta ou input _token). */
export async function csrf(page: Page): Promise<string> {
  const meta = await page.locator('meta[name=csrf-token]').first().getAttribute('content').catch(() => null);
  if (meta) return meta;
  const input = await page.locator('input[name=_token]').first().inputValue().catch(() => '');
  return input || '';
}

/** Pega o id do primeiro ponto da listagem (portavel entre ambientes com dados). */
export async function primeiroPontoId(page: Page): Promise<number | null> {
  await page.goto(`${BASE}/pontos`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  const href = await page.locator('a[href*="/pontos/"]').first().getAttribute('href').catch(() => null);
  const m = href && href.match(/\/pontos\/(\d+)/);
  return m ? Number(m[1]) : null;
}

type CreateOpts = { withMorador?: boolean; comFoto?: boolean; nomes?: string };

/**
 * Cria uma vistoria de teste pelo caminho de escrita real (POST autenticado),
 * colhendo action/token/selects do formulario de create de um ponto existente.
 * Retorna o id criado. Marca com TAG para limpeza posterior.
 */
export async function criarVistoria(page: Page, opts: CreateOpts = {}): Promise<number> {
  const pontoId = await primeiroPontoId(page);
  expect(pontoId, 'precisa de ao menos 1 ponto para o create de vistoria').not.toBeNull();

  await page.goto(`${BASE}/pontos/${pontoId}/vistorias/create`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);

  const harvested = await page.evaluate(() => {
    const forms = Array.from(document.querySelectorAll('form'));
    const f = forms.find(x => /\/vistorias(\b|$)/.test(x.action) && (x.method || '').toLowerCase() === 'post')
      || forms.find(x => x.querySelector('[name=tipo_abordagem_id],[name=data_abordagem]'));
    if (!f) return null;
    const firstOpt = (name: string) => {
      const sel = f!.querySelector<HTMLSelectElement>(`select[name="${name}"]`);
      if (!sel) return '';
      const opt = Array.from(sel.options).find(o => o.value && o.value !== '');
      return opt ? opt.value : '';
    };
    const val = (name: string) => (f!.querySelector<HTMLInputElement>(`[name="${name}"]`)?.value) || '';
    return {
      action: f.action,
      token: f.querySelector<HTMLInputElement>('input[name=_token]')?.value || '',
      tipo: firstOpt('tipo_abordagem_id'),
      resultado: firstOpt('resultado_acao_id'),
      lat: val('lat'),
      lng: val('lng'),
    };
  });
  expect(harvested, 'formulario de create de vistoria nao encontrado').not.toBeNull();

  const body: Record<string, string> = {
    _token: harvested!.token,
    ponto_id: String(pontoId),
    lat: harvested!.lat || '-19.9227',
    lng: harvested!.lng || '-43.9451',
    data_abordagem: nowLocal(),
    tipo_abordagem_id: harvested!.tipo || '1',
    resultado_acao_id: harvested!.resultado || '1',
    quantidade_pessoas: '1',
    nomes_pessoas: `${TAG} ${opts.nomes || 'vistoria e2e'}`,
  };
  if (opts.withMorador) body['novos_moradores[0][nome_social]'] = `${TAG} morador e2e`;

  const reqOpts = opts.comFoto
    ? { multipart: { ...body, 'fotos[0]': fotoPayload() }, maxRedirects: 0, failOnStatusCode: false }
    : { form: body, maxRedirects: 0, failOnStatusCode: false };
  const resp = await page.request.post(harvested!.action, reqOpts);
  expect(resp.status(), `create deveria redirecionar (302). corpo: ${(await safeText(resp)).slice(0, 200)}`).toBe(302);
  const loc = resp.headers()['location'] || '';
  const m = loc.match(/\/vistorias\/(\d+)/);
  expect(m, `Location inesperado: ${loc}`).not.toBeNull();
  return Number(m![1]);
}

export async function finalizarVistoria(page: Page, id: number): Promise<number> {
  await page.goto(`${BASE}/vistorias/${id}/edit`, { waitUntil: 'domcontentloaded' });
  const token = await csrf(page);
  const resp = await page.request.post(`${BASE}/vistorias/${id}/finalizar`, {
    form: { _token: token }, maxRedirects: 0, failOnStatusCode: false,
  });
  return resp.status();
}

/** Remove a vistoria de teste (resource destroy via _method=DELETE). Idempotente o suficiente. */
export async function removerVistoria(page: Page, id: number): Promise<void> {
  const token = await csrf(page);
  await page.request.post(`${BASE}/vistorias/${id}`, {
    form: { _token: token, _method: 'DELETE' }, maxRedirects: 0, failOnStatusCode: false,
  });
}

function nowLocal(): string {
  const d = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

async function safeText(resp: { text: () => Promise<string> }): Promise<string> {
  try { return await resp.text(); } catch { return ''; }
}
