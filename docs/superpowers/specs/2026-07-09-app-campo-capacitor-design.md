# Spec — SIZEM Campo: empacotamento Android (Capacitor, modo remoto)

- **Data:** 2026-07-09
- **Status:** Implementado (APK v1.4, 2026-07-15)
- **Fase do roadmap:** Fase 1 (empacotar) — ver `docs/` do plano do app de campo
- **Eixo:** ação em campo (o eixo administrativo permanece no web)

## 1. Contexto

O SIZEM já é um PWA offline-first funcional: `public/manifest.json` standalone, Service
Worker `public/sw.js` (v34) com cache de tiles do Leaflet e de API, fila de fotos em
IndexedDB (`poprua_fotos`, via `resources/js/offline-upload.js`) e uma API de campo
completa em `routes/api.php` (pontos, moradores, vistorias/fotos, geo). A autenticação é
por **sessão/cookie** (rotas sob `web/auth`); `app/Models/User.php` usa `HasRoles` mas
**não** `HasApiTokens`.

Consequência: as cinco funcionalidades de campo previstas (mapa+GPS, vistorias, fotos
offline, moradores, sincronização) **já rodam no navegador**. O caminho de menor esforço
para levá-las ao celular como app não é reescrever, e sim **empacotar o PWA de produção**
num shell nativo. Uma abordagem em WebView reaproveita a sessão por cookie sem migração;
uma abordagem nativa (Flutter/React Native) exigiria introduzir autenticação por token e
reescrever a camada offline.

## 2. Objetivo e critério de "pronto"

Gerar um **APK Android instalável por sideload** que embrulha o SIZEM de produção,
permitindo aos agentes usá-lo como app (ícone, tela cheia, sem barra do navegador) e
testá-lo em campo real.

**Pronto quando:**

1. O APK instala num aparelho Android (via `adb install` ou transferência do arquivo).
2. Ao abrir, o app carrega autenticado e cai no `/mapa` (login por cookie persiste entre
   aberturas).
3. O GPS centraliza o mapa na posição do agente.
4. É possível anexar uma foto a uma vistoria; ao perder e retomar a rede, a foto pendente
   sincroniza (comportamento atual da fila IndexedDB).

## 3. Decisões travadas

| Decisão | Escolha | Motivo |
|---|---|---|
| Abordagem | Capacitor (wrap do PWA), **modo remoto** | Reaproveita ~100% do front + API; mantém auth por cookie |
| Escopo do ciclo | Fase 1 — empacotar | Valor mais rápido; endurecimento vem guiado por teste em campo |
| Distribuição | APK sideload | Não depende de conta Play Console da PBH |
| Plataforma | Android apenas | iOS fica para quando houver demanda |
| URL remota | Produção (pública, confirmado) | Celular em dados móveis alcança a URL sem VPN |
| Estrutura | Subpasta `mobile/` isolada | Não toca o build web nem o deploy de produção |
| Identidade | `appId: br.gov.pbh.sizem`, nome "SIZEM Campo" | Padrão institucional PBH |

## 4. Arquitetura

O app é um shell nativo que carrega o SIZEM hospedado. Como o WebView navega no próprio
domínio de produção, o cookie de sessão e o CSRF continuam valendo — sem CORS nem token.

```
App (Capacitor WebView)
   │  HTTPS · cookie de sessão · mesmas rotas /api
   ▼
https://sufis.pbh.gov.br/ginfi/poprua-cras/public   (Laravel 12, rotas web+auth)
   ▼
PostgreSQL+PostGIS · Spatie MediaLibrary · Redis (filas)

Camada offline (dentro do WebView, permanece intacta):
  Service Worker v34 (tiles + API cache) + IndexedDB poprua_fotos (fila de fotos)
```

Nenhum arquivo do backend Laravel é alterado neste ciclo.

## 5. Estrutura no repositório

```
mobile/
  package.json          # @capacitor/core, @capacitor/cli, @capacitor/android
  capacitor.config.ts   # appId, appName, server.url = produção
  android/              # projeto nativo gerado pelo Capacitor (commitado)
  resources/            # ícone e splash de origem (para @capacitor/assets)
```

`.gitignore` (dentro de `mobile/`) ignora apenas artefatos de build: `android/build/`,
`android/app/build/`, `android/.gradle/`, `android/local.properties`, `node_modules/`.

*Alternativas rejeitadas:* projeto na raiz do repo (mexeria no `package.json` que o deploy
de produção usa — risco de quebrar o build web); repositório separado (isola demais e
desalinha do fluxo de deploy atual).

## 6. Unidades de trabalho

Cada unidade tem propósito único e pode ser verificada isoladamente.

### 6.1 Projeto Capacitor (`mobile/`)
- **O que faz:** inicializa o projeto Capacitor com `package.json` próprio.
- **Como usar:** `npm i @capacitor/core && npm i -D @capacitor/cli` na pasta `mobile/`,
  depois `npx cap init "SIZEM Campo" br.gov.pbh.sizem --web-dir=www` (um `www/` mínimo só
  para satisfazer o CLI; o conteúdo real vem do `server.url`).
- **Depende de:** Node 22 (já instalado).

### 6.2 `capacitor.config.ts` (modo remoto)
- **O que faz:** aponta o WebView para a URL de produção e restringe a navegação ao
  domínio.
- **Conteúdo:** `appId`, `appName`, `webDir: 'www'`, e bloco `server` com
  `url: 'https://sufis.pbh.gov.br/ginfi/poprua-cras/public'`,
  `allowNavigation: ['sufis.pbh.gov.br']`, `androidScheme: 'https'`.
- **Depende de:** 6.1.

### 6.3 Plataforma Android + permissões
- **O que faz:** gera o projeto Android nativo e declara as permissões necessárias.
- **Como usar:** `npm i @capacitor/android && npx cap add android`; editar
  `android/app/src/main/AndroidManifest.xml` para incluir `ACCESS_FINE_LOCATION`,
  `ACCESS_COARSE_LOCATION`, `CAMERA` e leitura de mídia (`INTERNET` já vem por padrão).
- **Depende de:** 6.2.

### 6.4 Ícone e splash
- **O que faz:** gera mipmaps e splash a partir do tema PBH (`#184186`) e dos ícones em
  `public/icons/`.
- **Como usar:** `@capacitor/assets` a partir de um ícone-fonte em `mobile/resources/`.
- **Depende de:** 6.3.

### 6.5 Build do APK (debug)
- **O que faz:** produz o `.apk` instalável.
- **Como usar:** `npx cap sync android` e build via Gradle
  (`android/gradlew assembleDebug`); saída em
  `android/app/build/outputs/apk/debug/app-debug.apk`.
- **Depende de:** 6.3, 6.4 e do ambiente de build (seção 8).

### 6.6 Validação em aparelho
- **O que faz:** confirma o critério de "pronto" (seção 2) num Android real.
- **Como usar:** `adb install app-debug.apk` (ou transferência) + checklist de campo.
- **Depende de:** 6.5.

## 7. Permissões nativas (Android)

Mesmo em WebView, o Android exige declaração explícita para GPS e câmera:

- `ACCESS_FINE_LOCATION` / `ACCESS_COARSE_LOCATION` — geolocalização do mapa.
- `CAMERA` + leitura de mídia — captura/anexo de fotos.
- `INTERNET` — padrão do Capacitor.

O Capacitor gerencia os prompts de permissão em runtime; o WebView concede
`geolocation`/`getUserMedia` sob HTTPS com as permissões declaradas.

## 8. Dependência de ambiente de build

Gerar o APK exige, na máquina de build:

- **JDK 17.**
- **Android SDK** (command-line tools ou Android Studio) + **Gradle** (o wrapper vem no
  projeto gerado).
- Aceite das licenças do SDK (`sdkmanager --licenses`).

Estes componentes podem não estar instalados nesta máquina. O plano de implementação deve
**verificar a presença** desses itens e, se faltarem, **instalá-los ou documentar** os
passos. Este é o ponto de maior probabilidade de exigir ação manual (instalação/licenças).

## 9. Comportamento offline

Em modo remoto, a **primeira carga exige rede**. Depois, o Service Worker v34 serve shell,
tiles e respostas de API do cache, e a fila de fotos (IndexedDB) sincroniza ao reconectar
— comportamento **idêntico ao PWA atual, sem regressão**. Endurecer as vistorias para
funcionarem 100% offline (criar/finalizar sem rede) é trabalho da Fase 0, fora deste ciclo.

## 10. Fora de escopo (deste ciclo)

- Publicação na Play Store (fase posterior; exige conta PBH no Play Console).
- Plugins nativos Camera/Geolocation/Network (Fase 2 — primeiro usamos as Web APIs do
  WebView).
- iOS.
- Offline total das vistorias (Fase 0).
- Qualquer alteração no backend Laravel.

## 11. Assunções e riscos

| Item | Estado | Mitigação |
|---|---|---|
| URL de produção pública (dados móveis) | ✅ confirmado | — |
| JDK 17 + Android SDK na máquina | ⚠️ a verificar | Passo de verificação/instalação no plano |
| WebView concede `geolocation`/`getUserMedia` | ⚠️ padrão, validar | Checklist em aparelho (6.6) |
| Cookie de sessão persiste no WebView | ⚠️ validar | Testar login persistente entre aberturas |
| Domínio em subpasta (`/ginfi/poprua-cras/public`) e rotas relativas | ⚠️ validar | Confirmar que `allowNavigation` e caminhos relativos do PWA funcionam no WebView |

## 12. Critérios de aceitação (checklist de campo)

- [x] APK instala e abre sem barra de navegador (tela cheia).
- [x] Abre autenticado no `/mapa`; login persiste ao fechar e reabrir.
- [x] GPS centraliza o mapa na posição do agente.
- [x] Anexar foto a uma vistoria funciona.
- [x] Perder rede e reconectar: a foto pendente sincroniza.
- [x] Nenhuma regressão em relação ao PWA no navegador.
