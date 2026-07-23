# SIZEM Campo — Análise do projeto no contexto do Android Studio

> Gerada em 2026-07-22. Ponto de retomada: **abrir o Android Studio nesta pasta**
> (`/data/projects/poprua-cras/mobile/android`), e não na raiz do repositório.
> Evidências: `./gradlew assembleDebug` (exit 0) e `./gradlew :app:lintDebug`
> (2 erros, 26 avisos) executados nesta máquina.
>
> **Continuação (2026-07-22, seções 7–14):** análise do shell além do lint —
> o buraco de partida a frio sem rede, a perda silenciosa de fotos na expiração
> de sessão, a superfície nativa exposta ao conteúdo remoto e o caminho para o
> Capacitor 8. Evidências novas: `apkanalyzer`, leitura do fonte do
> `@capacitor/android` 7.6.7, `public/sw.js` e `resources/js/offline-*.js`.

## 1. O que existe de Android neste repositório

O repositório é um monolito Laravel 12 com **um projeto Android nativo isolado em `mobile/`** —
um shell Capacitor 7 em *modo remoto*: o WebView carrega
`https://sufis.pbh.gov.br/ginfi/poprua-cras/public/bem-vindo` e o PWA
(Service Worker + IndexedDB) cuida da camada offline.

| Item | Valor |
|---|---|
| Projeto Gradle | `mobile/android` (`:app`, `:capacitor-cordova-android-plugins`, `:capacitor-android`, `:capacitor-splash-screen`) |
| appId / versão | `br.gov.pbh.sizem` · versionCode 6 / versionName 1.5 |
| AGP / Gradle / JDK | 8.13.2 / 8.13 / Java 21 (commitado: 8.7.2 / 8.11.1 — ver §7) |
| minSdk / target / compile | 23 / 35 / 35 |
| Código nativo | 1 arquivo: `app/src/main/java/br/gov/pbh/sizem/MainActivity.java` (splash adaptativo + limpeza de cache do WebView por versão) |
| Distribuição atual | APK **debug** por sideload (`adb install -r`), sem Play Store |

Ambiente verificado nesta máquina: Android Studio 2026.1.2 (snap), JDK 21,
SDK em `/home/murtafilho/Android/Sdk` (platforms 35/36/36.1; build-tools 34/35/36),
`local.properties` já apontando para o SDK (arquivo gitignored, específico da máquina).

## 2. Abrir a pasta certa

Em 2026-07-22 o Android Studio foi aberto na **raiz do repositório**, o que criou
`/data/projects/poprua-cras/.idea/poprua-cras.iml` como um `JAVA_MODULE` genérico —
o que o Studio gera quando a pasta aberta **não é um projeto Gradle**. Nesse estado
não há Gradle sync, nem Build ▸ Build APK, nem Logcat vinculado ao módulo, nem
editor de manifest/recursos.

```bash
# abrir sempre:
/data/projects/poprua-cras/mobile/android

# opcional, limpar o projeto genérico criado na raiz (já é gitignored):
rm -rf /data/projects/poprua-cras/.idea
```

**Pegadinha do Capacitor:** `app/src/main/assets/public`, `assets/capacitor.config.json`
e `assets/capacitor.plugins.json` são gitignored. Num clone novo, dar Run direto no
Studio produz um app **sem o `server.url`**. Antes do primeiro build:

```bash
cd /data/projects/poprua-cras/mobile
npm install
npx cap sync android
```

## 3. Achados do Android Lint (`:app:lintDebug` → 2 erros, 26 avisos)

### P0 — crash em Android 6.0–8.0 (`MainActivity.java:45`)

`getLongVersionCode()` exige API 28, mas o `minSdk` é 23. O trecho está dentro de
`catch (Exception e)`, e o que estoura em API < 28 é `NoSuchMethodError` — um `Error`,
que **não** é capturado. O app fecha no `onCreate`. O aparelho validado em campo
(Galaxy A16 5G / Android 14) não expõe o problema.

Correção: `androidx.core.content.pm.PackageInfoCompat.getLongVersionCode(info)`
(androidx.core já está no classpath) ou subir o `minSdk` para 28.

Esse erro faz o `lintDebug` **falhar o build**, então "Analyze ▸ Inspect Code" e as
variantes de release ficam barradas até ser corrigido.

### P0 — não existe signing config de release

Só há `assembleDebug`. O APK distribuído por sideload é assinado com a chave de debug
e sai com `debuggable=true` — WebView inspecionável via `chrome://inspect` em qualquer
aparelho. Para um app que carrega sessão autenticada com dados de população em situação
de rua, criar keystore próprio + `signingConfigs.release` e passar a distribuir
`assembleRelease`.

### P1 — `android:allowBackup="true"`

O WebView guarda cookies de sessão e a fila offline (IndexedDB). Com backup habilitado,
esses dados saem do aparelho. Recomendado `allowBackup="false"` ou definir
`dataExtractionRules`.

### P1 — `res/xml/file_paths.xml` amplo demais

`<external-path name="my_images" path="." />` expõe a raiz inteira do armazenamento
externo via FileProvider (default do Capacitor). Restringir ao diretório de fotos.

### P2 — demais avisos

- Manifest sem `<uses-feature android:required="false">` para câmera e GPS (erro de lint; relevante só para filtragem na Play Store).
- `<uses-permission>` declarado depois de `<application>` (`ManifestOrder`).
- `ExampleInstrumentedTest` ainda asserta `com.getcapacitor.app` — falha se rodado pelo Studio; ajustar para `br.gov.pbh.sizem` ou remover.
- Ícone round não circular (`IconLauncherShape`) e sem tag `monochrome` (ícone temático Android 13+).
- `splash.png` em `drawable/` sem qualificador de densidade; tamanhos dip divergentes entre densidades.
- `READ_MEDIA_IMAGES` sem tratar Selected Photos Access (Android 14+).
- Recursos não usados: `activity_main.xml`, `config.xml`, `ic_launcher_background`, `AppTheme.NoActionBar`, `package_name`, `custom_url_scheme`.
- Dependências androidx com versões novas: appcompat 1.7.0 → 1.7.1, coordinatorlayout 1.2.0 → 1.3.0, core-splashscreen 1.0.1 → 1.2.0.

Relatório completo: `app/build/reports/lint-results-debug.txt` (regenerar com
`./gradlew :app:lintDebug`; **precisa de rede** — em `--offline` a resolução das
dependências de teste falha).

## 4. Versões e prazos

- **Capacitor 7.6.7** instalado; a linha atual publicada é **8.4.2**.
- **targetSdk 35**: a partir de **31/08/2026** o Google Play exige 35 para apps existentes
  e **36 para apps novos e atualizações** (extensão possível até 01/11/2026). Como hoje a
  distribuição é sideload, nada bloqueia — mas se o SIZEM Campo for para Play/MDM, o
  SDK 36 já está instalado na máquina.
  Fontes: <https://support.google.com/googleplay/android-developer/answer/11926878> ·
  <https://developer.android.com/google/play/requirements/target-sdk>
- Não há CI de APK: `.github/workflows/deploy-production.yml` só publica o Laravel;
  o `versionCode` é incrementado à mão em `app/build.gradle`.

## 5. Fluxo de trabalho dentro do Studio

1. Abrir `mobile/android`; Gradle JDK = JBR 21 (embutido no Studio).
2. Ao mudar `capacitor.config.ts` ou dependências: rodar `npx cap sync android` **fora**
   do Studio e depois Sync Project with Gradle Files.
3. Nunca editar arquivos gerados: `capacitor.settings.gradle`, `app/capacitor.build.gradle`,
   `assets/capacitor.config.json`, `assets/capacitor.plugins.json`, `res/xml/config.xml`.
4. Logcat com filtro `Capacitor|chromium|Console`; para depurar a camada web, usar
   `chrome://inspect` com o build debug.
5. Build por linha de comando (equivalente ao que o Studio faz):

   ```bash
   export ANDROID_HOME=/home/murtafilho/Android/Sdk
   cd /data/projects/poprua-cras/mobile/android
   ./gradlew assembleDebug
   adb install -r app/build/outputs/apk/debug/app-debug.apk
   ```

## 6. Fila de correções do lint (não aplicada — consolidada na §14)

| # | Item | Arquivo | Prioridade |
|---|---|---|---|
| 1 | `PackageInfoCompat.getLongVersionCode` | `MainActivity.java` | P0 |
| 2 | Keystore + `signingConfigs.release` e passar a distribuir release | `app/build.gradle` | P0 |
| 3 | `allowBackup="false"` / `dataExtractionRules` | `AndroidManifest.xml` | P1 |
| 4 | Restringir `external-path` do FileProvider | `res/xml/file_paths.xml` | P1 |
| 5 | `<uses-feature required="false">` câmera/GPS + ordem do manifest | `AndroidManifest.xml` | P2 |
| 6 | Corrigir/remover `ExampleInstrumentedTest` | `app/src/androidTest/...` | P2 |
| 7 | Ícone `monochrome` + round circular | `res/mipmap-anydpi-v26/`, `res/mipmap-*` | P2 |
| 8 | Atualizar androidx e avaliar Capacitor 8 | `variables.gradle`, `mobile/package.json` | P2 |

Ao publicar uma nova versão, lembrar de subir `versionCode`/`versionName` em
`app/build.gradle` (a `MainActivity` limpa o cache HTTP do WebView uma vez por
`versionCode`) e atualizar o status em `mobile/README.md`.

---

# Continuação da análise (2026-07-22)

## 7. O working tree já não é o que está commitado

Abrir o Studio na pasta certa e rodar o AGP Upgrade Assistant deixou o toolchain
**atualizado mas não commitado**:

```
 M mobile/android/build.gradle                        AGP 8.7.2  → 8.13.2
 M mobile/android/gradle/wrapper/gradle-wrapper.properties  Gradle 8.11.1 → 8.13
 M mobile/android/settings.gradle                     + plugin foojay-resolver-convention 0.10.0
 ?? mobile/android/gradle/gradle-daemon-jvm.properties  (gerado por updateDaemonJvm)
```

**O build foi verificado nesse estado:** `./gradlew assembleDebug` → exit 0,
`app-debug.apk` de 4,7 MB gerado em 2026-07-22 22:57. Ou seja, o upgrade não
quebrou nada e vale commitar — é pré-requisito do Capacitor 8 (§12).

Duas ressalvas antes de commitar:

- `gradle-daemon-jvm.properties` fixa `toolchainVendor=JETBRAINS` +
  `toolchainVersion=21` e traz URLs da **foojay.io** para 10 pares SO/arquitetura.
  Numa máquina sem a JBR 21, o Gradle passa a **baixar** o JDK pela rede — e
  `--offline` quebra. Como só existe uma máquina de build hoje, o mais seguro é
  **não versionar** esse arquivo (adicionar ao `.gitignore` do módulo) e manter o
  JDK escolhido pela configuração do Studio.
- `settings.gradle` ganhou o plugin `foojay-resolver-convention`, que serve
  justamente para resolver toolchains pela rede. Ele é inofensivo enquanto
  nenhum `toolchain {}` for declarado, mas é a mesma dependência de rede.

## 8. O buraco do modo remoto: partida a frio sem rede (P0)

O app é vendido como ferramenta de campo offline, mas a **abertura** do app
depende de rede. Cadeia de evidências:

1. `assets/capacitor.config.json` (empacotado no APK) manda o WebView carregar
   `https://sufis.pbh.gov.br/…/bem-vindo` a cada partida a frio.
2. `public/sw.js` — o handler `install` **não faz precache de nada**
   (`self.skipWaiting()` e mais nada, linhas 7–9).
3. O catch-all do `fetch` (linhas 121–127) é network-first com fallback
   `caches.match(request)` — mas **nunca popula o cache**. Só quatro coisas são
   gravadas: assets Vite com hash, tiles de mapa, `/api/geo/*` e o documento de
   `/vistorias/create` (linhas 94–119).
4. Logo, `/bem-vindo` nunca está no cache. Sem rede, o `fetch` rejeita,
   `caches.match` devolve `undefined`, o `respondWith` vira erro de rede e o
   WebView mostra a página de erro do Chromium — depois de 8 s de splash, porque
   `onPageLoaded` não dispara e só o failsafe do `launchShowDuration` esconde o
   loader.

Resultado prático: **abrir o app sem sinal não dá acesso à fila offline**, mesmo
com as vistorias e fotos intactas no IndexedDB. A fatia 3 offline só funciona se
o app já estiver aberto (ou tiver sido aberto com rede na mesma sessão de WebView).

Duas correções, complementares:

- **(a) Precache do shell no `install` do SW** — a correta. Gravar `/bem-vindo`
  (ou um `/offline` dedicado, com o link para as pendentes) no `CACHE_NAME`
  durante o `install`, e revalidar no `activate`. Como a resposta vem da **mesma
  origem** da produção, a página cacheada continua enxergando cookies, IndexedDB
  e a fila. Custo: uma dezena de linhas em `public/sw.js` + bump do
  `CACHE_VERSION`.
- **(b) `server.errorPath` no Capacitor** — a rede de segurança nativa.
  Verificado no fonte: `BridgeWebViewClient.java:61-63` e `:91-93` carregam
  `bridge.getErrorUrl()` quando o **main frame** falha, e `Bridge.java:552-560`
  monta essa URL a partir do `server.errorPath` mesmo em modo remoto. Basta
  `server: { …, errorPath: 'offline.html' }` e um `mobile/www/offline.html`.
  Limite importante: esse arquivo é servido de `https://localhost`, **outra
  origem** — ele não lê o IndexedDB da produção. Serve para explicar a situação e
  oferecer "tentar novamente", não para operar offline.

De quebra, o `mobile/www/index.html` atual é código morto: com `server.url`
definido, o Capacitor nunca carrega o bundle local. Ele pode virar o `offline.html`.

## 9. Perda silenciosa de fotos quando a sessão expira (P0)

`public/sw.js:222-230` (`syncPendingPhotos`) faz o POST de foto **sem**
`Accept: application/json`:

```js
var headers = {};
if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
var resp = await fetch(endpoint, { method: 'POST', body: form, headers: headers, credentials: 'same-origin' });
if (resp.ok) await idbDelete(db, foto.id);
```

`POST /api/vistorias/fotos` está sob `['web', 'auth']` (`routes/api.php:53`).
Sem `Accept: application/json`, `expectsJson()` é falso e o middleware
`Authenticate` **redireciona para `/login` (302)**. O `fetch` segue o redirect,
recebe **200** com o HTML da tela de login, `resp.ok` é verdadeiro — e a foto é
**apagada do IndexedDB sem nunca ter sido enviada**.

**Medido no navegador** (mesma semântica de `fetch` do Service Worker, sessão
válida mas não autenticada):

```
SEM Accept:  {"status":200,"ok":true,"redirected":true,"url":"/login"}    -> foto apagada
COM Accept:  {"status":401,"ok":false,"redirected":false,"url":"/api/vistorias/fotos"}
```

A condição exata é **sessão válida porém não autenticada** — não basta a sessão
ter expirado (aí o token XSRF também expira e vem 419, que é tratado como
transiente). O estado perigoso é justamente o mais comum na volta do campo: o
agente reabre o app, o Laravel emite sessão e cookie XSRF novos na tela de
login, e o Background Sync dispara **antes** dele digitar a senha — CSRF passa,
`auth` falha, vem o redirect, e a fila é esvaziada. O mesmo vale logo após um
logout. Com `SESSION_LIFETIME=120` e `SESSION_EXPIRE_ON_CLOSE=false`, chegar
deslogado depois de meio turno é rotina.

O mesmo código no contexto da página **está correto**: `offline-upload.js:255-257`
manda `Accept: application/json`. Também estão corretos `syncPendingVistorias`
(sw.js:323) e `syncPendingAcoes` (sw.js:378), ambos com `Accept`. É uma
assimetria de uma linha só no caminho das fotos.

Correção mínima: `headers['Accept'] = 'application/json'` — com isso, sessão
expirada vira **401**, que não está em `PERMANENT_REJECTION_STATUSES`
(`[422, 409, 403]`), então o registro fica `pending` para nova tentativa.
Correção completa: tratar 401/419 explicitamente, sinalizando à UI que é preciso
refazer login para a fila drenar (hoje ficaria retentando em silêncio).

## 10. Superfície nativa exposta ao conteúdo remoto

Em modo remoto o Capacitor injeta o `native-bridge.js` (53 KB no APK) **na página
de produção**. Consequências que valem registrar:

- Qualquer XSS no SIZEM passa a ter, no app, acesso ao bridge nativo e aos
  plugins registrados. Mitigação já existente e correta: `allowNavigation`
  restrito a `sufis.pbh.gov.br` — navegação para fora abre no navegador externo.
- `apkanalyzer manifest debuggable` no APK gerado: **true**. Somado à ausência de
  `signingConfigs.release` (§3), o app distribuído por sideload é inspecionável
  via `chrome://inspect` em qualquer aparelho — incluindo cookies de sessão e o
  IndexedDB com fotos de pessoas em situação de rua.
- `res/xml/file_paths.xml` expõe `<external-path path="." />` **e**
  `<cache-path path="." />`. É o default do Capacitor, usado pelo
  `onShowFileChooser` (`BridgeWebChromeClient.java:275+`) para a captura de foto;
  restringir ao subdiretório de captura não quebra o fluxo.

Ponto positivo verificado: a permissão de GPS é pedida em runtime pelo próprio
Capacitor (`BridgeWebChromeClient.onGeolocationPermissionsShowPrompt`,
linhas 246-272), sem precisar do plugin `@capacitor/geolocation`. O mesmo vale
para a câmera no `onShowFileChooser`. As permissões do manifest estão coerentes
com o que é efetivamente usado.

## 11. Botão Voltar fecha o app

O Capacitor 7 **não trata o botão Voltar no core**: `BridgeActivity.java` não
tem `onBackPressed`, e não há nenhuma ocorrência de `canGoBack`/`KEYCODE_BACK`
em todo o `@capacitor/android` 7.6.7. Sem o plugin `@capacitor/app` instalado
(e ele não está — só `@capacitor/splash-screen`), vale o comportamento padrão da
Activity: **Voltar encerra o app**, em vez de voltar uma página no WebView.

Em campo isso significa sair do app no meio de um formulário. O rascunho de
vistoria (`/api/vistorias/rascunho`) reduz o estrago, mas o gesto mais natural do
Android hoje é destrutivo. Correção: instalar `@capacitor/app` e tratar
`backButton` (voltar no histórico; na raiz, confirmar antes de sair).

## 12. URL de produção fixa no APK

`server.url` é literal em `capacitor.config.ts` e vai congelado para o
`assets/capacitor.config.json`. Não existe forma de apontar o mesmo APK para
homologação ou para um servidor local — é preciso editar o arquivo, refazer o
`cap sync` e reconstruir, o que também derruba a rastreabilidade de qual build
aponta para onde.

Se aparecer necessidade de testar contra homologação, o caminho barato é gerar
dois `capacitor.config.*.ts` e escolher via variável de ambiente no `cap sync`,
com `applicationIdSuffix`/`versionNameSuffix` no flavor de homologação para os
dois apps coexistirem no mesmo aparelho.

## 13. Caminho para o Capacitor 8 (e para o targetSdk 36)

Requisitos oficiais do upgrade 7 → 8 (docs do Capacitor, via Context7):

| Item | Hoje | Capacitor 8 |
|---|---|---|
| minSdkVersion | 23 | **24** |
| compileSdk / targetSdk | 35 / 35 | **36 / 36** |
| AGP | 8.13.2 ✅ | 8.13.0+ |
| Gradle | 8.13 | **8.14.3** |
| Android Studio | 2026.1.2 ✅ | Otter 2025.2.1+ |
| androidx (variables.gradle) | appcompat 1.7.0, coordinatorlayout 1.2.0, core-splashscreen 1.0.1, core 1.15.0, webkit 1.12.1, cordova 10.1.1 | 1.7.1, 1.3.0, 1.2.0, 1.17.0, 1.14.0, 14.0.1 |

Ou seja: **metade do upgrade já está feita** (AGP e Studio), falta subir o wrapper
para 8.14.3, mexer no `variables.gradle` e rodar `npm i @capacitor/…@8`.

O ganho não é cosmético — subir para `targetSdk 36` resolve de uma vez o prazo do
Google Play descrito na §4 (35 obrigatório a partir de 31/08/2026; 36 para apps
novos e atualizações), e o SDK 36 já está instalado nesta máquina. Enquanto a
distribuição for sideload nada bloqueia, mas o custo de fazer agora é baixo.

`minSdk 24` continua abaixo do 28 que o `getLongVersionCode` exige — a correção
com `PackageInfoCompat` (§3) segue necessária de qualquer forma.

## 14. Fila consolidada (lint + análise do shell)

| # | Item | Onde | Prioridade |
|---|---|---|---|
| 1 | ✅ **feito** — `Accept: application/json` no sync de fotos do SW | `public/sw.js:283` | **P0** |
| 2 | ✅ **feito** — precache do shell no `install` do SW + fallback de navegação | `public/sw.js` | **P0** |
| 3 | ✅ **feito** — `PackageInfoCompat.getLongVersionCode` | `MainActivity.java` | **P0** |
| 4 | Keystore + `signingConfigs.release`; parar de distribuir APK `debuggable=true` | `app/build.gradle` | **P0** |
| 5 | `server.errorPath` + `www/offline.html` (reaproveitar o `index.html` morto) | `capacitor.config.ts`, `mobile/www/` | P1 |
| 6 | `allowBackup="false"` / `dataExtractionRules` | `AndroidManifest.xml` | P1 |
| 7 | Restringir `external-path`/`cache-path` do FileProvider | `res/xml/file_paths.xml` | P1 |
| 8 | `@capacitor/app` + tratamento do botão Voltar | `mobile/package.json`, `capacitor.config.ts` | P1 |
| 9 | Commitar o upgrade de toolchain; **não** versionar `gradle-daemon-jvm.properties` | `mobile/android/` | P1 |
| 10 | ✅ **feito** — Capacitor 8 + `targetSdk 36` (prazo da Play Store resolvido) | `variables.gradle`, `mobile/package.json` | P1 |
| 11 | ✅ **feito** — `<uses-feature required="false">` câmera/GPS + ordem do manifest | `AndroidManifest.xml` | P2 |
| 12 | Corrigir/remover `ExampleInstrumentedTest` (ainda asserta `com.getcapacitor.app`) | `app/src/androidTest/…` | P2 |
| 13 | Ícone `monochrome` + round circular | `res/mipmap-*` | P2 |
| 14 | ✅ **feito** — alvo do WebView por ambiente (`SIZEM_ALVO`/`SIZEM_URL`) + `network_security_config` | `capacitor.config.ts` | P2 |

**Lacunas operacionais** (sem item de código, mas decidem o risco em campo):
não há CI de APK — `versionCode` é incrementado à mão e o build sai de uma
máquina só; não há crash reporting, então um crash em campo (§3, Android 6–8)
só aparece se o agente relatar; e não há canal de atualização — cada versão
depende de sideload manual em cada aparelho.

Itens 1 e 2 são no repositório Laravel (`public/sw.js`), não no projeto Android:
saem no deploy normal e valem para o PWA no navegador também, sem exigir novo APK.

## 15. O que foi aplicado nesta rodada (com verificação)

**Item 2 — shell offline** (`public/sw.js`, `CACHE_VERSION` 39 → 40): `install`
passou a precachear `/bem-vindo` e `/vistorias` (resolvidos contra
`self.registration.scope`, o que preserva o subdiretório de produção), navegação
na própria origem virou network-first com gravação do shell atualizado — já
autenticado — e, offline, fallback em três degraus (URL exata → rota sem query →
shell). Respostas redirecionadas nunca entram no cache, para não guardar a tela
de login como shell nem quebrar o replay de navegação.

Verificado com Playwright contra `localhost:8088` (login real, `setOffline(true)`,
reload):

```
antes (sw.js do HEAD):  reload offline -> net::ERR_FAILED     [pagina de erro do Chromium]
depois:                 /bem-vindo  -> "SIZEM — Home"         renderizou offline
                        /vistorias  -> "SIZEM — Zeladorias"   renderizou offline
                        cache poprua-v40 contem /bem-vindo e /vistorias
```

**Item 1 — fotos** (`public/sw.js`): `Accept: application/json` no POST de
`syncPendingPhotos`, mais a guarda `!resp.redirected` antes de apagar da fila.
Evidência medida na §9: o mesmo POST que devolvia `200 ok=true` (login seguido
por redirect) passa a devolver `401 ok=false`, mantendo a foto pendente.

**Item 3 — crash em Android 6–8** (`MainActivity.java`): troca por
`PackageInfoCompat.getLongVersionCode(info)`, com o comentário explicando por que
o `catch (Exception)` não protegia (o `NoSuchMethodError` é um `Error`).

**Item 11 — manifest**: `<uses-feature required="false">` para câmera,
autofocus, location e GPS; blocos `<uses-permission>` movidos para antes do
`<application>`.

Estado do lint depois das correções — `./gradlew :app:lintDebug --rerun-tasks`:

```
0 errors, 26 warnings      (antes: 2 errors, 26 warnings)
```

Com zero erros, `lintDebug` deixou de barrar o build, o que desbloqueia
"Analyze ▸ Inspect Code" e as variantes de release (item 4).

APK regerado como **v1.6 (`versionCode 7`)** — `assembleDebug` exit 0, 4,7 MB.
Precisa de sideload para o item 3 chegar ao aparelho; os itens 1 e 2 chegam
sozinhos pelo deploy do Laravel, inclusive nos APKs antigos.

Fora de escopo por decisão: o item 4 (keystore de release) exige gerar e guardar
uma chave e sua senha — decisão do dono do app, não automatizável aqui.

## 16. Não quebrar quem está na v1.5

O risco não está no `sw.js` — está no APK. Dois cuidados e uma ordem de publicação.

**A assinatura precisa ser a mesma.** A v1.5 foi construída nesta máquina com
`~/.android/debug.keystore`, e a v1.6 saiu da mesma chave:

```
Signer #1 certificate DN: C=US, O=Android, CN=Android Debug
SHA-256: 9663e71ddf0f47c0b585426b2f8e381c4f9b47106f6b2290ac94a89afb0dff50
```

Enquanto a impressão digital for essa, `adb install -r` atualiza por cima e
**preserva os dados do WebView** — cookies de sessão, IndexedDB e a fila offline.
Se alguém construir o APK em outra máquina, o `debug.keystore` será outro, a
instalação falha com `INSTALL_FAILED_UPDATE_INCOMPATIBLE` e o único caminho vira
desinstalar — **o que apaga a fila de fotos e vistorias pendentes**. Conferir
antes de distribuir:

```bash
$ANDROID_HOME/build-tools/36.0.0/apksigner verify --print-certs \
  app/build/outputs/apk/debug/app-debug.apk | grep SHA-256
```

Corolário: guardar o `debug.keystore` num lugar seguro até existir keystore de
release (item 4) — hoje ele é o que garante a continuidade dos dados em campo.

**A troca de `CACHE_VERSION` não pode deixar o aparelho sem shell.** O `activate`
apagava o cache da versão anterior de imediato; se a rede caísse no meio da
atualização, o agente ficaria sem cache nenhum. Agora a limpeza só roda depois
de confirmar que o shell novo está gravado, e `vistorias/create` entrou no
precache (era a única página que o v39 guardava — quem atualizasse a perderia).

Simulação do aparelho em uso recebendo o deploy (SW v39 com `/vistorias/create`
em cache → deploy do `sw.js` novo → perda de sinal logo em seguida):

```
1. Antes do deploy:  {"poprua-v39":["/vistorias/create"]}
2. Depois do deploy: {"poprua-v40":["/bem-vindo","/vistorias","/vistorias/create"]}
   OK  SW novo assumiu sem reinstalar o APK
   OK  cache da versao anterior removido so apos o shell novo estar gravado
   OK  /bem-vindo, /vistorias e /vistorias/create abriram offline
```

**Ordem de publicação recomendada:**

1. **Deploy do Laravel primeiro.** Os itens 1 e 2 chegam a todo mundo pelo
   `sw.js`, inclusive a quem ficar na v1.5, e é o que protege a fila de fotos.
   Nenhum sideload envolvido.
2. **Deixar rodar um turno** e confirmar em campo que o app abre sem rede.
3. **Só então o sideload da v1.6**, com a fila sincronizada antes por garantia
   (Wi-Fi, esperar as pendências zerarem) — não porque o `-r` apague algo, mas
   porque é a hora em que um imprevisto sairia caro.
4. Nunca desinstalar para atualizar. `adb install -r` ou a instalação por cima
   pelo gerenciador de arquivos.

O `versionCode` 7 > 6 mantém a atualização válida (downgrade seria recusado), e o
`clearCache(true)` da `MainActivity` limpa só o cache HTTP do WebView — Cache
Storage e IndexedDB não são tocados. O que apaga tudo é "Limpar dados do app"
nas configurações do Android, ou desinstalar.
