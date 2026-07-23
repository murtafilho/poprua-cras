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
  (platform `android-35`, build-tools `35.0.0`, `cmdline-tools/latest`)
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
npx cap sync android
cd android && ./gradlew assembleDebug
# APK: mobile/android/app/build/outputs/apk/debug/app-debug.apk
```

## Instalar por sideload

```bash
adb install -r mobile/android/app/build/outputs/apk/debug/app-debug.apk
```

## Status

- APK debug **v1.7** (`versionCode 8`) — gerado em 2026-07-23, **aguardando sideload**
  - `MainActivity` publica a versão do APK para a página (`window.__sizemAppVersao`), que a tela inicial usa no rótulo de versão
- APK debug v1.6 (`versionCode 7`) — 2026-07-22, não distribuída
  - Corrige crash na abertura em Android 6.0–8.0 (`getLongVersionCode` exigia API 28 com `minSdk` 23) — o aparelho de campo (Android 14) não era afetado
  - `<uses-feature required="false">` para câmera e GPS; permissões antes do `<application>`
  - `./gradlew :app:lintDebug` passou de 2 erros para **0 erros** (26 avisos), destravando as variantes de release
- APK debug v1.5 (`versionCode 6`) — abre em `/bem-vindo`; incorpora a fatia 3 offline (listar pendentes e criar vistoria sem rede)
- A camada offline vive no `public/sw.js` (deploy do Laravel) e vale também para os APKs antigos: desde o `CACHE_VERSION 40`, o app abre sem rede (shell `/bem-vindo` e `/vistorias` no cache) e o sync de fotos não descarta mais a fila quando a sessão não está autenticada. Ver `mobile/android/ANALISE-ANDROID-STUDIO.md` §8, §9 e §15
- Limpa o cache HTTP do WebView a cada nova versão
- Validado em campo (Samsung Galaxy A16 5G / Android 14, 2026-07-10):
  - Instala e abre em tela cheia, sem barra de navegador
  - Modo remoto carrega a produção; loader adaptativo cobre a carga (sem tela branca)
  - Ícone e splash com a identidade SIZEM (v2 icon-a2)
  - Sessão persiste (abre logado no /mapa); mapa carrega com marcadores
  - GPS centraliza; câmera anexa foto na vistoria
  - Sincronização offline da fila de fotos funcionando
  - Checklist de aceitação: **aprovado**
