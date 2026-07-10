import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'br.gov.pbh.sizem',
  appName: 'SIZEM Campo',
  webDir: 'www',
  // Modo remoto: o WebView carrega o SIZEM hospedado. Navegando no próprio
  // domínio, a sessão por cookie e o CSRF continuam válidos — sem CORS nem token.
  server: {
    url: 'https://sufis.pbh.gov.br/ginfi/poprua-cras/public/',
    allowNavigation: ['sufis.pbh.gov.br'],
    androidScheme: 'https',
  },
};

export default config;
