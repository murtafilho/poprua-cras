import { test as setup } from '@playwright/test';
import fs from 'fs';
import { AUTH_DIR, ADMIN_STATE, AGENTE_STATE, CREDS, login } from '../helpers';

setup.beforeAll(() => { fs.mkdirSync(AUTH_DIR, { recursive: true }); });

setup('autentica admin', async ({ page }) => {
  await login(page, CREDS.admin.email, CREDS.admin.pass);
  await page.context().storageState({ path: ADMIN_STATE });
});

setup('autentica agente de campo', async ({ page }) => {
  await login(page, CREDS.agente.email, CREDS.agente.pass);
  await page.context().storageState({ path: AGENTE_STATE });
});
