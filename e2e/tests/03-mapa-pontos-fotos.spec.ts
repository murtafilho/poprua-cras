import { test, expect } from '@playwright/test';
import { ADMIN_STATE, BASE, primeiroPontoId } from '../helpers';

test.use({ storageState: ADMIN_STATE });

test.describe('Jornada 3 — Mapa, pontos e fotos (read-only)', () => {
  test('listagem de pontos carrega', async ({ page }) => {
    const resp = await page.goto(`${BASE}/pontos`, { waitUntil: 'domcontentloaded' });
    expect(resp?.status()).toBeLessThan(400);
    await expect(page.locator('a[href*="/pontos/"]').first()).toBeVisible();
  });

  test('listagem de vistorias em tabela', async ({ page }) => {
    const resp = await page.goto(`${BASE}/vistorias`, { waitUntil: 'domcontentloaded' });
    expect(resp?.status()).toBeLessThan(400);
    await expect(page.locator('table.vistorias-table')).toBeVisible();
    const headers = await page.locator('table.vistorias-table thead th').allInnerTexts();
    expect(headers).toContain('Endereço');
    expect(headers).toContain('Situação');
    expect(await page.locator('table.vistorias-table tbody tr').count()).toBeGreaterThan(0);
  });

  test('detalhe de um ponto abre', async ({ page }) => {
    const id = await primeiroPontoId(page);
    test.skip(id === null, 'sem pontos no ambiente');
    const resp = await page.goto(`${BASE}/pontos/${id}`, { waitUntil: 'domcontentloaded' });
    expect(resp?.status()).toBeLessThan(400);
    expect((await page.locator('body').innerText()).length).toBeGreaterThan(200);
  });

  test('mapa renderiza o Leaflet (e markers se houver dados)', async ({ page }) => {
    await page.goto(`${BASE}/mapa`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(4000);
    await expect(page.locator('.leaflet-container')).toBeVisible();
    const tiles = await page.locator('.leaflet-tile-loaded').count();
    expect(tiles, 'tiles do mapa deveriam carregar').toBeGreaterThan(0);
    const markers = await page.locator('.leaflet-marker-icon, .leaflet-interactive').count();
    console.log(`mapa: ${tiles} tiles, ${markers} markers`);
  });

  test('foto de acervo serve via HTTP (200, nao 404)', async ({ page }) => {
    // procura uma <img> apontando para /storage em alguma tela
    await page.goto(`${BASE}/mapa`, { waitUntil: 'domcontentloaded' });
    let src = await page.locator('img[src*="/storage/"]').first().getAttribute('src').catch(() => null);
    if (!src) {
      const id = await primeiroPontoId(page);
      if (id) {
        await page.goto(`${BASE}/pontos/${id}`, { waitUntil: 'domcontentloaded' });
        src = await page.locator('img[src*="/storage/"]').first().getAttribute('src').catch(() => null);
      }
    }
    test.skip(!src, 'nenhuma imagem de /storage exposta nas telas amostradas');
    const resp = await page.request.get(src!);
    expect(resp.status(), `foto ${src} deveria servir 200`).toBe(200);
  });
});
