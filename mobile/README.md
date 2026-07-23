# SIZEM Campo (app Android)

Shell Capacitor que embrulha o SIZEM de produção
(`https://sufis.pbh.gov.br/ginfi/poprua-cras/public/`) num app Android.
Modo remoto: o WebView carrega a URL de produção; a sessão por cookie e a
camada offline (Service Worker + IndexedDB) do PWA continuam operando.

Durante a carga da página remota, um loader (splash SIZEM + spinner) é
exibido e escondido de forma adaptativa em `MainActivity.onPageLoaded`
(dura o tempo real da carga; failsafe de 8s), evitando a tela branca.

## Pré-requisitos (verificados nesta máquina)

- Node 22, JDK 21
- Android SDK em `/home/murtafilho/Android/Sdk`
  (platform `android-36`, build-tools `36.0.0`, `cmdline-tools/latest`)
- `adb` em `/usr/bin/adb`

Antes de qualquer comando Gradle, exporte o caminho do SDK:

```bash
export ANDROID_HOME=/home/murtafilho/Android/Sdk
export PATH="$ANDROID_HOME/platform-tools:$PATH"
```

## Construir o APK de teste

```bash
cd mobile
npm install
npm run apk:producao        # sync + assembleDebug apontando para a produção
# APK: mobile/android/app/build/outputs/apk/debug/app-debug.apk
```

## Apontar o app para o servidor local

O modo remoto faz o WebView carregar a produção, então mudança de front não
aparece no app sem publicar. Para trabalhar sem deploy:

```bash
php artisan serve --port=8088        # na raiz do projeto Laravel
cd mobile && npm run apk:local       # APK apontando para http://10.0.2.2:8088
adb install -r android/app/build/outputs/apk/debug/app-debug.apk
```

`10.0.2.2` é como o emulador enxerga o host. Para aparelho físico na mesma rede,
use o IP da máquina e suba o servidor aberto:

```bash
php artisan serve --host=0.0.0.0 --port=8088
SIZEM_URL=http://192.168.0.10:8088/bem-vindo npm run apk:local
```

O `cap sync` imprime para onde o APK vai apontar — confira antes de distribuir:

```
[SIZEM Campo] alvo: producao -> https://sufis.pbh.gov.br/ginfi/poprua-cras/public/bem-vindo
```

**Nunca distribua um APK construído com `apk:local`**: em campo ele não abre
nada. Antes de gerar a versão de campo, rode `npm run apk:producao`.

O HTTP em claro fica restrito a `10.0.2.2`, `localhost` e `127.0.0.1` por
`res/xml/network_security_config.xml` — o resto do app continua exigindo HTTPS.

## Instalar por sideload

```bash
adb install -r mobile/android/app/build/outputs/apk/debug/app-debug.apk
```

## Status

- APK debug **v1.9** (`versionCode 10`) — gerado em 2026-07-23, **aguardando sideload**
  - Capacitor 8.4.2, `targetSdk 36`, `minSdk 24` (Android 7.0+) — resolve o prazo do Google Play de 31/08/2026
  - Alvo do WebView selecionável por ambiente (`SIZEM_ALVO` / `SIZEM_URL`) + `network_security_config` liberando HTTP só para os endereços de desenvolvimento
  - `MainActivity` publica a versão do APK para a página (`window.__sizemAppVersao`), que a tela inicial usa no rótulo de versão
  - Corrige o crash na abertura em Android 6.0–8.0 (`getLongVersionCode` exigia API 28 com `minSdk` 23)
  - `<uses-feature required="false">` para câmera e GPS; permissões antes do `<application>`
  - `./gradlew :app:lintDebug`: **0 erros** (antes eram 2), o que destrava as variantes de release
- v1.6, v1.7 e v1.8 não chegaram a ser distribuídas — a v1.9 as substitui
- APK debug v1.5 (`versionCode 6`) — a versão que está no aparelho de campo
- A camada offline vive no `public/sw.js` (deploy do Laravel) e vale também para os APKs antigos, inclusive a v1.5: o app abre sem rede (shell `/bem-vindo`, `/vistorias` e `/vistorias/create` em cache) e o sync não descarta mais a fila quando a sessão não está autenticada. Ver `ANALISE-ANDROID-STUDIO.md` §8, §9 e §15
- Limpa o cache HTTP do WebView a cada nova versão
- Validado em campo (Samsung Galaxy A16 5G / Android 14, 2026-07-10):
  - Instala e abre em tela cheia, sem barra de navegador
  - Modo remoto carrega a produção; loader adaptativo cobre a carga (sem tela branca)
  - Ícone e splash com a identidade SIZEM (v2 icon-a2)
  - Sessão persiste (abre logado no /mapa); mapa carrega com marcadores
  - GPS centraliza; câmera anexa foto na vistoria
  - Sincronização offline da fila de fotos funcionando
  - Checklist de aceitação: **aprovado**
