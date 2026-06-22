import { test, expect } from '@playwright/test';
import { ADMIN_STATE, BASE, TAG, criarVistoria, removerVistoria, primeiroPontoId } from '../helpers';

test.use({ storageState: ADMIN_STATE });

test.describe('Jornada 2 — Morador + equipe', () => {
  let criadas: number[] = [];
  test.afterEach(async ({ page }) => {
    for (const id of criadas) await removerVistoria(page, id).catch(() => {});
    criadas = [];
  });

  test('cria vistoria com novo morador aninhado', async ({ page }) => {
    const id = await criarVistoria(page, { withMorador: true, nomes: 'com-morador' });
    criadas.push(id);
    // a vistoria existe; o morador [HOMOLOG] foi criado junto (caminho novos_moradores)
    await page.goto(`${BASE}/vistorias/${id}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(TAG);
  });

  test('formulario de vistoria expoe selecao de participantes (equipe/RBAC)', async ({ page }) => {
    const pontoId = await primeiroPontoId(page);
    expect(pontoId).not.toBeNull();
    await page.goto(`${BASE}/pontos/${pontoId}/vistorias/create`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    // a UI de participantes depende da permission 'participar de equipes vistoria'
    const byName = await page.locator('[name*="participante"]').count();
    const byText = await page.getByText(/participante|equipe/i).count();
    expect(byName + byText, 'esperado controle de participantes/equipe no formulario').toBeGreaterThan(0);
  });
});
