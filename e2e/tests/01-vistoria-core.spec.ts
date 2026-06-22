import { test, expect } from '@playwright/test';
import { ADMIN_STATE, BASE, TAG, criarVistoria, finalizarVistoria, removerVistoria } from '../helpers';

test.use({ storageState: ADMIN_STATE });

test.describe('Jornada 1 — Nucleo da vistoria (criar -> ver -> editar -> finalizar)', () => {
  let criadas: number[] = [];
  test.afterEach(async ({ page }) => {
    for (const id of criadas) await removerVistoria(page, id).catch(() => {});
    criadas = [];
  });

  test('cria vistoria pelo caminho de escrita real', async ({ page }) => {
    const id = await criarVistoria(page, { nomes: 'nucleo' });
    criadas.push(id);
    await page.goto(`${BASE}/vistorias/${id}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(TAG);
  });

  test('renderiza o formulario de edicao com dados', async ({ page }) => {
    const id = await criarVistoria(page, { nomes: 'edicao' });
    criadas.push(id);
    const resp = await page.goto(`${BASE}/vistorias/${id}/edit`, { waitUntil: 'domcontentloaded' });
    expect(resp?.status()).toBeLessThan(400);
    const campos = await page.locator('input:not([type=hidden]), select, textarea').count();
    expect(campos, 'form de edicao deve renderizar campos editaveis').toBeGreaterThan(3);
  });

  test('finaliza a vistoria', async ({ page }) => {
    const id = await criarVistoria(page, { nomes: 'finalizar' });
    criadas.push(id);
    const status = await finalizarVistoria(page, id);
    expect(status, 'finalizar deveria redirecionar (302)').toBe(302);
  });
});
