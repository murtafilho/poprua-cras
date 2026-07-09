# SIZEM Campo — Empacotamento Android (Capacitor) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gerar um APK Android de teste (sideload) que embrulha o SIZEM de produção num shell nativo Capacitor em modo remoto, sem alterar o backend.

**Architecture:** Um projeto Capacitor isolado em `mobile/` cria um app Android cujo WebView carrega a URL de produção. Como o WebView navega no próprio domínio, a sessão por cookie e a camada offline (Service Worker + IndexedDB) do PWA continuam operando sem mudança no Laravel.

**Tech Stack:** Capacitor 7 · Android SDK (platform 35 / build-tools 35) · JDK 21 · Gradle wrapper · Node 22 · `@capacitor/assets`.

## Global Constraints

- `appId`: `br.gov.pbh.sizem`
- `appName`: `SIZEM Campo`
- `server.url`: `https://sufis.pbh.gov.br/ginfi/poprua-cras/public`
- `allowNavigation`: `sufis.pbh.gov.br`
- `androidScheme`: `https`
- Distribuição: **APK debug por sideload** — sem Play Store neste ciclo.
- Plataforma: **Android apenas** (sem iOS).
- **Não alterar** nenhum arquivo do backend Laravel nem o `package.json`/build web da raiz.
- Todo o trabalho fica isolado em `mobile/`.
- Branch de trabalho: `feat/app-campo-capacitor` (já criada; não fazer push sem pedido — push dispara deploy de produção).
- Ambiente confirmado nesta máquina: JDK 21 em `/usr/bin/java`; Android SDK em `/home/murtafilho/Android/Sdk` (platforms `android-35`, `android-36`; build-tools `35.0.0`, `36.0.0`; `cmdline-tools/latest`); `adb` em `/usr/bin/adb`.
- Textos e comentários em português brasileiro com acentuação correta.

---

## Estrutura de arquivos

| Caminho | Responsabilidade |
|---|---|
| `mobile/README.md` | Pré-requisitos verificados + como construir o APK |
| `mobile/package.json` | Dependências Capacitor do projeto mobile (isolado do web) |
| `mobile/.gitignore` | Ignora artefatos de build e `node_modules` |
| `mobile/www/index.html` | Placeholder mínimo exigido por `webDir` (o conteúdo real vem do `server.url`) |
| `mobile/capacitor.config.ts` | Config do app: `appId`, `appName`, `webDir`, bloco `server` (modo remoto) |
| `mobile/resources/icon.png` | Ícone-fonte 1024×1024 para `@capacitor/assets` |
| `mobile/android/` | Projeto Android nativo gerado (commitado, exceto artefatos de build) |
| `mobile/android/app/src/main/AndroidManifest.xml` | Permissões de GPS e câmera |
| `mobile/android/local.properties` | Caminho do SDK (gitignored — específico da máquina) |

---

## Task 1: Verificar ambiente e documentar pré-requisitos

**Files:**
- Create: `mobile/README.md`

**Interfaces:**
- Consumes: nada.
- Produces: `mobile/README.md` documentando o ambiente; a variável `ANDROID_HOME=/home/murtafilho/Android/Sdk` usada pelas tarefas seguintes.

- [ ] **Step 1: Verificar a versão do Java**

Run: `java -version`
Expected: linha contendo `openjdk version "21` (JDK 17+ satisfaz o Capacitor 7).

- [ ] **Step 2: Verificar o Android SDK**

Run: `ls /home/murtafilho/Android/Sdk/platforms /home/murtafilho/Android/Sdk/build-tools`
Expected: `platforms` contém `android-35`; `build-tools` contém `35.0.0`.

- [ ] **Step 3: Verificar o adb**

Run: `adb version`
Expected: `Android Debug Bridge version 1.0.41`.

- [ ] **Step 4: Criar o README com os pré-requisitos**

Create `mobile/README.md`:

````markdown
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
````

- [ ] **Step 5: Commit**

```bash
git add mobile/README.md
git commit -m "docs(app-campo): README com pre-requisitos de build Android verificados"
```

---

## Task 2: Scaffold do projeto Capacitor em `mobile/`

**Files:**
- Create: `mobile/package.json`
- Create: `mobile/.gitignore`
- Create: `mobile/www/index.html`
- Create: `mobile/capacitor.config.ts`

**Interfaces:**
- Consumes: nada de tarefas anteriores.
- Produces: `mobile/capacitor.config.ts` com `appId`/`appName`/`server` que a Task 3 usa ao adicionar a plataforma Android; `mobile/node_modules` com `@capacitor/core`, `@capacitor/cli`.

- [ ] **Step 1: Criar o `package.json` do projeto mobile**

Create `mobile/package.json`:

```json
{
  "name": "sizem-campo",
  "version": "0.1.0",
  "private": true,
  "description": "Shell Android (Capacitor) do SIZEM — ação em campo",
  "scripts": {
    "sync": "cap sync android",
    "apk": "cd android && ./gradlew assembleDebug"
  }
}
```

- [ ] **Step 2: Instalar o núcleo e a CLI do Capacitor**

Run: `cd mobile && npm install @capacitor/core && npm install -D @capacitor/cli`
Expected: instala sem erros; `mobile/node_modules/@capacitor/cli` existe.

- [ ] **Step 3: Verificar a versão instalada do Capacitor**

Run: `cd mobile && npx cap --version`
Expected: imprime uma versão `7.x` (Capacitor 7).

- [ ] **Step 4: Criar o placeholder de `webDir`**

Create `mobile/www/index.html`:

```html
<!doctype html>
<html lang="pt-br">
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="refresh" content="0; url=https://sufis.pbh.gov.br/ginfi/poprua-cras/public" />
    <title>SIZEM Campo</title>
  </head>
  <body>
    <p>Carregando o SIZEM…</p>
  </body>
</html>
```

- [ ] **Step 5: Criar a configuração do Capacitor (modo remoto)**

Create `mobile/capacitor.config.ts`:

```ts
import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'br.gov.pbh.sizem',
  appName: 'SIZEM Campo',
  webDir: 'www',
  // Modo remoto: o WebView carrega o SIZEM hospedado. Navegando no próprio
  // domínio, a sessão por cookie e o CSRF continuam válidos — sem CORS nem token.
  server: {
    url: 'https://sufis.pbh.gov.br/ginfi/poprua-cras/public',
    allowNavigation: ['sufis.pbh.gov.br'],
    androidScheme: 'https',
  },
};

export default config;
```

- [ ] **Step 6: Criar o `.gitignore` do mobile**

Create `mobile/.gitignore`:

```gitignore
node_modules/
www/dist/
android/build/
android/app/build/
android/.gradle/
android/.idea/
android/local.properties
android/app/release/
*.apk
*.aab
```

- [ ] **Step 7: Validar que a CLI enxerga a config**

Run: `cd mobile && npx cap config --json`
Expected: JSON contendo `"appId": "br.gov.pbh.sizem"` e o bloco `server` com a `url` de produção.

- [ ] **Step 8: Commit**

```bash
git add mobile/package.json mobile/package-lock.json mobile/.gitignore mobile/www/index.html mobile/capacitor.config.ts
git commit -m "feat(app-campo): scaffold do projeto Capacitor em modo remoto"
```

---

## Task 3: Adicionar a plataforma Android e as permissões

**Files:**
- Create: `mobile/android/` (gerado por `cap add android`)
- Create: `mobile/android/local.properties`
- Modify: `mobile/android/app/src/main/AndroidManifest.xml`

**Interfaces:**
- Consumes: `mobile/capacitor.config.ts` (Task 2).
- Produces: projeto Android compilável em `mobile/android/`, com permissões de GPS e câmera declaradas.

- [ ] **Step 1: Instalar o pacote da plataforma Android**

Run: `cd mobile && npm install @capacitor/android`
Expected: instala sem erros.

- [ ] **Step 2: Adicionar a plataforma Android**

Run: `cd mobile && export ANDROID_HOME=/home/murtafilho/Android/Sdk && npx cap add android`
Expected: cria `mobile/android/` e imprime `[success] android added!`.

- [ ] **Step 3: Apontar o SDK para o Gradle**

Create `mobile/android/local.properties`:

```properties
sdk.dir=/home/murtafilho/Android/Sdk
```

- [ ] **Step 4: Escrever o teste de permissões (verificação do manifesto)**

Este é o "teste" desta tarefa: um grep que deve FALHAR antes da edição.

Run: `grep -c "ACCESS_FINE_LOCATION" mobile/android/app/src/main/AndroidManifest.xml`
Expected (antes da edição): `0` (permissão ainda ausente).

- [ ] **Step 5: Adicionar as permissões ao AndroidManifest**

Modify `mobile/android/app/src/main/AndroidManifest.xml`: logo após a linha
`<uses-permission android:name="android.permission.INTERNET" />` (já presente),
inserir:

```xml
    <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
    <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
    <uses-permission android:name="android.permission.CAMERA" />
    <uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />
```

- [ ] **Step 6: Rodar o teste de permissões (agora deve passar)**

Run: `grep -c -E "ACCESS_FINE_LOCATION|ACCESS_COARSE_LOCATION|CAMERA|READ_MEDIA_IMAGES" mobile/android/app/src/main/AndroidManifest.xml`
Expected: `4`.

- [ ] **Step 7: Sincronizar a config nativa**

Run: `cd mobile && export ANDROID_HOME=/home/murtafilho/Android/Sdk && npx cap sync android`
Expected: `[success] Sync finished`.

- [ ] **Step 8: Commit**

```bash
git add mobile/android mobile/package.json mobile/package-lock.json
git commit -m "feat(app-campo): plataforma Android + permissoes de GPS e camera"
```

---

## Task 4: Ícone e splash a partir da identidade PBH

**Files:**
- Create: `mobile/resources/icon.png`
- Modify: `mobile/android/app/src/main/res/**` (gerado por `@capacitor/assets`)

**Interfaces:**
- Consumes: projeto Android (Task 3); ícone existente `public/icons/icon-512x512.png`.
- Produces: mipmaps e splash do app com o tema `#184186`.

- [ ] **Step 1: Verificar disponibilidade do ImageMagick**

Run: `which convert magick`
Expected: pelo menos um caminho. Se ambos vazios, instalar antes: `sudo apt-get install -y imagemagick`.

- [ ] **Step 2: Gerar o ícone-fonte 1024×1024**

`@capacitor/assets` exige um ícone-fonte de no mínimo 1024×1024; o maior ícone
atual é 512×512, então geramos um 1024 a partir dele.

Run:
```bash
mkdir -p mobile/resources
convert public/icons/icon-512x512.png -resize 1024x1024 mobile/resources/icon.png
```
Expected: cria `mobile/resources/icon.png`.

- [ ] **Step 3: Verificar as dimensões do ícone-fonte**

Run: `identify -format "%wx%h" mobile/resources/icon.png`
Expected: `1024x1024`.

- [ ] **Step 4: Gerar os assets Android**

Run:
```bash
cd mobile && npx @capacitor/assets generate --android \
  --iconBackgroundColor '#184186' \
  --splashBackgroundColor '#184186'
```
Expected: `Generated Android assets` e novos arquivos em `mobile/android/app/src/main/res/`.

- [ ] **Step 5: Verificar que os mipmaps foram gerados**

Run: `ls mobile/android/app/src/main/res/mipmap-xxxhdpi/`
Expected: contém `ic_launcher.png` (e variantes foreground/round).

- [ ] **Step 6: Commit**

```bash
git add mobile/resources mobile/android/app/src/main/res
git commit -m "feat(app-campo): icone e splash com tema PBH #184186"
```

---

## Task 5: Construir o APK de teste (debug)

**Files:**
- Nenhum novo arquivo versionado (o `.apk` é gitignored).

**Interfaces:**
- Consumes: `mobile/android/` configurado (Tasks 3–4).
- Produces: `mobile/android/app/build/outputs/apk/debug/app-debug.apk`.

- [ ] **Step 1: Sincronizar antes de construir**

Run: `cd mobile && export ANDROID_HOME=/home/murtafilho/Android/Sdk && npx cap sync android`
Expected: `[success] Sync finished`.

- [ ] **Step 2: Construir o APK debug via Gradle**

Run: `cd mobile/android && export ANDROID_HOME=/home/murtafilho/Android/Sdk && ./gradlew assembleDebug`
Expected: termina com `BUILD SUCCESSFUL`. (A primeira execução baixa dependências do Gradle e pode demorar.)

- [ ] **Step 3: Verificar que o APK existe**

Run: `ls -lh mobile/android/app/build/outputs/apk/debug/app-debug.apk`
Expected: o arquivo existe (tipicamente 3–6 MB).

- [ ] **Step 4: Registrar o resultado no README**

Modify `mobile/README.md`: acrescentar ao final uma seção `## Status` com a linha
`- APK debug gerado com sucesso em app/build/outputs/apk/debug/app-debug.apk`.

- [ ] **Step 5: Commit**

```bash
git add mobile/README.md
git commit -m "chore(app-campo): registrar geracao do APK debug de teste"
```

---

## Task 6: Validação em aparelho Android (checklist de campo)

**Files:**
- Modify: `mobile/README.md` (checklist preenchido)

**Interfaces:**
- Consumes: `app-debug.apk` (Task 5).
- Produces: confirmação dos critérios de aceitação do spec.

> **Requer um aparelho Android físico** com Depuração USB ativada (ou um dado
> móvel real para validar o modo remoto). Se nenhum aparelho estiver disponível
> nesta sessão, marcar esta tarefa como pendente de validação manual pelo time.

- [ ] **Step 1: Conectar o aparelho e confirmar o adb**

Run: `adb devices`
Expected: lista um dispositivo com estado `device`.

- [ ] **Step 2: Instalar o APK**

Run: `adb install -r mobile/android/app/build/outputs/apk/debug/app-debug.apk`
Expected: `Success`.

- [ ] **Step 3: Executar o checklist de aceitação (manual)**

Abrir o app "SIZEM Campo" no aparelho e confirmar:

- [ ] Abre em tela cheia, sem barra de navegador.
- [ ] Carrega autenticado no `/mapa` (fazer login se pedir).
- [ ] Fechar e reabrir o app: o login **persiste** (cookie de sessão mantido).
- [ ] Conceder permissão de localização; o GPS centraliza o mapa.
- [ ] Abrir uma vistoria e anexar uma foto (conceder permissão de câmera).
- [ ] Ativar o modo avião, anexar outra foto, desativar o modo avião: a foto
      pendente **sincroniza** (fila IndexedDB).
- [ ] Nenhuma regressão em relação ao PWA no navegador.

- [ ] **Step 4: Registrar o resultado**

Modify `mobile/README.md`: na seção `## Status`, acrescentar a data e o resultado
do checklist (aprovado / itens com ressalva).

- [ ] **Step 5: Commit**

```bash
git add mobile/README.md
git commit -m "test(app-campo): checklist de aceitacao em aparelho registrado"
```

---

## Notas de execução

- **Não fazer `git push`** sem pedido explícito: `main` e branches podem disparar
  o deploy de produção; o trabalho deve permanecer local na branch
  `feat/app-campo-capacitor` até liberação.
- Toda tarefa que roda Gradle precisa de `export ANDROID_HOME=/home/murtafilho/Android/Sdk`
  no mesmo shell.
- Se o build do Gradle acusar incompatibilidade de JDK, confirmar que o
  `JAVA_HOME` aponta para o JDK 21 do sistema (`/usr/lib/jvm/...`).
- O backend Laravel **não** é tocado em nenhuma tarefa. Se surgir necessidade de
  mudança no backend (ex.: cabeçalho de sessão para WebView), isso é sinal de sair
  do escopo — parar e reavaliar o spec.
