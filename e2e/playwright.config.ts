import { defineConfig, devices } from '@playwright/test';

/**
 * Suite E2E do POPRUA CRAS.
 *
 * Alvo configuravel por env (E2E_BASE_URL). Padrao = dev local (recomendado).
 * Para rodar contra producao (com guarda de escrita [HOMOLOG]+cleanup):
 *   E2E_BASE_URL=https://sufis.pbh.gov.br/ginfi/poprua-cras/public npx playwright test
 *
 * Usuarios de teste vem do TestUsersSeeder (senha Cras@2026):
 *   admin@teste.local (admin) · agente.campo@teste.local (agentes-campo) · ...
 */
const BASE_URL = process.env.E2E_BASE_URL || 'http://localhost:8088';

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,           // escrita compartilha estado de dados; serial e mais previsivel
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  timeout: 60_000,
  expect: { timeout: 15_000 },
  use: {
    baseURL: BASE_URL,
    ignoreHTTPSErrors: true,
    actionTimeout: 20_000,
    navigationTimeout: 30_000,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],
});
