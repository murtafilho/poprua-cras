import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'br.gov.pbh.sizem',
  appName: 'SIZEM Campo',
  webDir: 'www',
  // Modo remoto: o WebView carrega o SIZEM hospedado. Navegando no próprio
  // domínio, a sessão por cookie e o CSRF continuam válidos — sem CORS nem token.
  server: {
    url: 'https://sufis.pbh.gov.br/ginfi/poprua-cras/public/bem-vindo',
    allowNavigation: ['sufis.pbh.gov.br'],
    androidScheme: 'https',
  },
  plugins: {
    // Loader durante a carga da página remota: mantém o splash SIZEM + um
    // spinner visível enquanto o WebView busca a produção pela rede, evitando
    // a tela branca. Duração cobre a latência típica em dados móveis.
    SplashScreen: {
      // Failsafe: se a página nunca carregar (offline/erro), o splash some em 8s.
      // No fluxo normal, o MainActivity o esconde antes, ao terminar a carga.
      launchShowDuration: 8000,
      launchAutoHide: true,
      backgroundColor: '#ffffff',
      androidScaleType: 'CENTER_INSIDE',
      showSpinner: true,
      androidSpinnerStyle: 'large',
      spinnerColor: '#184186',
      splashFullScreen: false,
      splashImmersive: false,
    },
  },
};

export default config;
