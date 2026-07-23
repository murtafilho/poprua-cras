#!/usr/bin/env bash
# Expõe o WebView do SIZEM Campo (emulador ou aparelho) em localhost:9222,
# para inspecionar e prototipar CSS com DevTools/Playwright.
#
#   bash .claude/skills/layout-mobile/scripts/webview-conectar.sh
#
# Depois disso funcionam:
#   node tools/ui-inspect.cjs /mapa --alvo=webview
#   chrome://inspect no Chrome do desktop
#
# Só funciona com APK debug (debuggable=true) — que é o que se distribui hoje.
set -uo pipefail

PORTA="${PORTA:-9222}"
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Android/Sdk}"
export PATH="$ANDROID_HOME/platform-tools:$PATH"

if ! command -v adb >/dev/null 2>&1; then
  echo "adb não encontrado. Defina ANDROID_HOME ou instale o platform-tools." >&2
  exit 1
fi

if [ -z "$(adb devices | sed '1d' | grep -w device)" ]; then
  echo "Nenhum aparelho/emulador conectado (adb devices vazio)." >&2
  echo "Emulador: DISPLAY=:20 XAUTHORITY=\$HOME/.Xauthority \$ANDROID_HOME/emulator/emulator -avd <AVD> &" >&2
  exit 1
fi

SOCKET=$(adb shell cat /proc/net/unix 2>/dev/null \
  | grep -o 'webview_devtools_remote_[0-9]*' | sort -u | head -1)

if [ -z "$SOCKET" ]; then
  echo "Nenhum WebView depurável encontrado." >&2
  echo "Abra o app primeiro: adb shell am start -n br.gov.pbh.sizem/.MainActivity" >&2
  exit 1
fi

adb forward "tcp:$PORTA" "localabstract:$SOCKET" >/dev/null

echo "WebView exposto em http://localhost:$PORTA  (socket: $SOCKET)"
if command -v python3 >/dev/null 2>&1; then
  curl -s "http://localhost:$PORTA/json/list" \
    | python3 -c "import sys,json;[print('  página:', t.get('url','')) for t in json.load(sys.stdin) if t.get('type')=='page']" \
    2>/dev/null || true
fi
echo "Encerrar depois: adb forward --remove tcp:$PORTA"
