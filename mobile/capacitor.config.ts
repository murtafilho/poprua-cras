import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Alvo do WebView.
 *
 * Sem variável de ambiente aponta para produção — o comportamento do APK que vai
 * para campo. `SIZEM_ALVO=local` aponta para o servidor de desenvolvimento desta
 * máquina, o que permite ver mudança de front dentro do app sem publicar nada:
 * era a única forma antes, e obrigava um deploy a cada tentativa.
 *
 * 10.0.2.2 é como o emulador enxerga o host (mapeia para o 127.0.0.1 da máquina),
 * então `php artisan serve --port=8088` basta. Para aparelho físico na mesma
 * rede, use SIZEM_URL com o IP da máquina e suba o servidor com --host=0.0.0.0.
 */
const ALVOS: Record<string, string> = {
  producao: 'https://sufis.pbh.gov.br/ginfi/poprua-cras/public/bem-vindo',
  local: 'http://10.0.2.2:8088/bem-vindo',
};

const alvo = process.env.SIZEM_ALVO === 'local' ? 'local' : 'producao';
const url = process.env.SIZEM_URL ?? ALVOS[alvo];
const host = new URL(url).hostname;

// O sync é silencioso demais para uma escolha que muda para onde o app aponta —
// e um APK apontando para o local que chegue ao campo não abre nada.
console.log(`[SIZEM Campo] alvo: ${alvo} -> ${url}`);

const config: CapacitorConfig = {
  appId: 'br.gov.pbh.sizem',
  appName: 'SIZEM Campo',
  webDir: 'www',
  // Modo remoto: o WebView carrega o SIZEM hospedado. Navegando no próprio
  // domínio, a sessão por cookie e o CSRF continuam válidos — sem CORS nem token.
  server: {
    url,
    allowNavigation: [...new Set(['sufis.pbh.gov.br', host])],
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
