---
name: layout-mobile
description: Diagnosticar e corrigir layout de tela do SIZEM no celular e no app de campo (Capacitor/WebView) — vão sobrando entre elementos, conteúdo colado ou escondido sob a barra de status, menu inferior sobrepondo, altura errada, "no app fica diferente do navegador". Mede as caixas antes de editar CSS, prototipa a correção no WebView vivo sem deploy e confere os dois alvos. Use SEMPRE que o usuário disser que a tela está desfigurada, quebrada, estranha ou feia; que algo "colou no topo", "sobrou um espaço", "não encosta", "sobrepõe", "não cabe", "ficou pequeno", "cortou"; ou pedir ajuste de layout, CSS, responsivo, safe-area, barra de status, menu inferior, header — inclusive quando mandar só um print e disser "olha como está" ou "vê no emulador".
---

# Layout no celular e no app de campo

## Por que este fluxo existe

O SIZEM Campo é um shell Capacitor em **modo remoto**: o WebView carrega
`https://sufis.pbh.gov.br/…`. Duas consequências definem todo o resto:

1. **Mudança local não aparece no emulador.** Editar CSS e recarregar o app não
   mostra nada — ele está lendo a produção. Por isso o protótipo é injetado no
   WebView vivo, e não construído e publicado a cada tentativa.
2. **O WebView tem safe-area real e o navegador não.** No Android 15+ com
   `targetSdk 35` o WebView desenha de borda a borda:
   `env(safe-area-inset-top)` e `-bottom` valem **24px**. No Chrome do desktop
   valem 0. É a origem da maioria dos "no app está diferente".

## Regra número um: medir antes de editar

Um print não distingue um vão de 24px de inset duplicado de um `margin` de 8px
sobrando. Uma medição resolve em segundos o que tentativa e erro não resolve em
meia hora — e ainda diz se a correção funcionou.

```bash
node tools/ui-inspect.cjs /mapa                 # navegador 360x640 — rápido, insets = 0
node tools/ui-inspect.cjs /mapa --alvo=webview  # WebView do app — insets reais
```

A saída traz `top`/`bottom`/`height`/`position` de cada seletor, os insets
vigentes, e os **vãos** entre elementos consecutivos — que é normalmente o
número que interessa:

```
_insets         {"top":"24px","bottom":"24px"}
.mobile-header  top=     0  bottom=    56  h=    56  fixed
#map            top=    56  bottom=   544  h=   488  relative
.bottom-nav     top=   568  bottom=   640  h=    72  fixed
# vao #map -> .bottom-nav: 24px          <- o defeito, quantificado
```

Opções úteis: `--sel=".mobile-header,#map,.bottom-nav"` para escolher o que
medir, `--png=/tmp/x.png` para o screenshot, `--css=<arquivo>` para injetar um
CSS compilado antes de medir.

## Roteiro

**1. Tente reproduzir no navegador.** É o ciclo mais rápido: editar → `npm run
build` → medir. Se o defeito aparece lá, resolva lá e pule para o passo 5.

**2. Não reproduziu? Então é do WebView.** Quase sempre safe-area. Conecte ao
WebView do aparelho/emulador (o APK debug já permite):

```bash
bash .claude/skills/layout-mobile/scripts/webview-conectar.sh
```

O script encontra o socket de devtools, expõe em `localhost:9222` e mostra a URL
aberta. A partir daí `--alvo=webview` funciona.

**3. Prototipe no WebView vivo.** Injete o CSS candidato e meça — sem build, sem
deploy, resposta em segundos:

```js
const { chromium } = require('playwright');
const b = await chromium.connectOverCDP('http://localhost:9222');
const page = b.contexts()[0].pages().find(p => p.url().includes('/mapa'));
await page.addStyleTag({ content: '.has-bottom-nav { padding-bottom: var(--footer-height); }' });
```

Itere aqui até os números fecharem. A injeção some na próxima recarga — ela é
laboratório, não entrega.

**4. Escreva no fonte** (`resources/css/app.css`) e `npm run build`.

**5. Meça de novo nos dois alvos.** Esta é a parte que evita conserto que quebra
outra coisa:

- **WebView**: o defeito sumiu (injete o CSS recém-compilado com `--css=` para
  conferir sem precisar publicar).
- **Navegador**: os números devem ficar **idênticos aos de antes** da mudança.
  Se mudaram, você consertou o app e quebrou o desktop.

Correção de safe-area passa nesse teste por construção: `env()` vale 0 no
navegador, então nada muda lá.

## Armadilhas desta base de código

**O inset é aplicado uma vez só, pela peça que encosta na borda.** Se o header
tem `padding-top: env(safe-area-inset-top)` e o container também soma o inset no
`padding-top`, o conteúdo desce 48px em vez de 24. Quando a faixa de homologação
está ligada é *ela* que encosta no topo — o header abaixo dela some o inset zero
vezes.

**`box-sizing: border-box` é global aqui.** Um `padding-bottom: env(…)` dentro de
`height: var(--footer-height)` **não** aumenta o elemento: ele come o conteúdo
por dentro. Foi exatamente isso que gerou o vão de 24px — a `.bottom-nav` ocupava
72px (com o inset dentro) enquanto `.has-bottom-nav` reservava `72 + 24`.

**Shorthand depois de longhand apaga.** Escrever `padding-top: env(…)` e mais
abaixo, na mesma regra, `padding: 0 var(--space-4)` zera o que você acabou de
pôr. Prefira colocar o inset direto no shorthand:
`padding: env(safe-area-inset-top, 0px) var(--space-4) 0`.

**`100vh` no WebView inclui a área da barra de status**, porque a janela é
edge-to-edge. Contar com `100vh` para "altura visível" erra por 24px.

**Sempre com fallback**: `env(safe-area-inset-top, 0px)`. Sem o `0px`, navegador
antigo devolve valor vazio e o `calc()` inteiro morre.

## Emulador

Detalhes que fazem perder tempo se não estiverem escritos:

- **A janela abre no display errado.** Se você está por Chrome Remote Desktop, a
  sessão gráfica é a `:20`, e um emulador lançado daqui vai para o `:0` (monitor
  físico) — parece que não abriu. Suba com
  `DISPLAY=:20 XAUTHORITY=$HOME/.Xauthority`. Conferir qual display tem a janela:
  `DISPLAY=:20 xwininfo -root -children | grep -i emulator`.
- **`FATAL | Running multiple emulators with the same AVD`** é lock órfão de uma
  instância morta: `rm -rf ~/.android/avd/<AVD>.avd/*.lock`.
- **Encerrar limpo**: `adb emu kill` (salva snapshot; o app instalado e a fila
  offline sobrevivem).
- O emulador mostra **produção**. Ver a correção lá de forma permanente exige
  deploy — o que é decisão do usuário, não passo automático deste roteiro.

## Quando a correção é do app, não do CSS

Se a medição mostra que o problema é a própria janela (viewport com altura
inesperada, teclado cobrindo campo, orientação), aí é o shell Android —
`mobile/android/`. Nesse caso a análise em
`mobile/android/ANALISE-ANDROID-STUDIO.md` tem o mapa do projeto, e o ciclo passa
a exigir `assembleDebug` + `adb install -r`, muito mais lento. Vale confirmar
pela medição que não dá para resolver no CSS antes de ir por esse caminho.
