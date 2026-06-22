import { test, expect } from '@playwright/test';
import { ADMIN_STATE, BASE, criarVistoria, removerVistoria } from '../helpers';

test.use({ storageState: ADMIN_STATE });

test.describe('Jornada 5 — Upload de foto na vistoria', () => {
  let criadas: number[] = [];
  test.afterEach(async ({ page }) => {
    for (const id of criadas) await removerVistoria(page, id).catch(() => {});
    criadas = [];
  });

  test('cria vistoria com foto (multipart) e a foto fica anexada', async ({ page }) => {
    const id = await criarVistoria(page, { comFoto: true, nomes: 'com-foto' });
    criadas.push(id);
    await page.goto(`${BASE}/vistorias/${id}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    const fotos = await page.locator('img[src*="/storage/"]').count();
    expect(fotos, 'a vistoria criada com foto deveria exibir ao menos 1 imagem de /storage').toBeGreaterThan(0);
  });
});
