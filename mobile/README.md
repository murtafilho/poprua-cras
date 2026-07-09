# SIZEM Campo (app Android)

Shell Capacitor que embrulha o SIZEM de produção
(`https://sufis.pbh.gov.br/ginfi/poprua-cras/public`) num app Android.
Modo remoto: o WebView carrega a URL de produção; a sessão por cookie e a
camada offline (Service Worker + IndexedDB) do PWA continuam operando.

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
