import { test, expect } from '@playwright/test';
import { ADMIN_STATE, AGENTE_STATE, BASE } from '../helpers';

test.describe('Jornada 4 — Admin + RBAC', () => {
  test.describe('admin acessa area administrativa', () => {
    test.use({ storageState: ADMIN_STATE });

    test('admin abre /admin/users', async ({ page }) => {
      const resp = await page.goto(`${BASE}/admin/users`, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBeLessThan(400);
      await expect(page).not.toHaveURL(/\/login/);
    });

    test('admin abre /admin/parametros', async ({ page }) => {
      const resp = await page.goto(`${BASE}/admin/parametros`, { waitUntil: 'domcontentloaded' });
      expect(resp?.status()).toBeLessThan(400);
      await expect(page.locator('body')).toContainText(/par[aâ]metro/i);
    });
  });

  test.describe('agente de campo e barrado na area admin', () => {
    test.use({ storageState: AGENTE_STATE });

    test('agente NAO acessa /admin/users (403 ou redirect)', async ({ page }) => {
      const resp = await page.goto(`${BASE}/admin/users`, { waitUntil: 'domcontentloaded' });
      const status = resp?.status() ?? 0;
      const url = page.url();
      const bloqueado = status === 403 || !/\/admin\/users/.test(url);
      expect(bloqueado, `agente nao deveria ver /admin/users (status=${status}, url=${url})`).toBeTruthy();
    });
  });
});
