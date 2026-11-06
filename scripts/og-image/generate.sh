#!/usr/bin/env bash
# Regenera front-presencard/public/og-image.png (1200x630) a partir de og-image.html con un navegador Chromium en modo headless.
# Uso: ./scripts/og-image/generate.sh   (CHROME_BIN opcional; por defecto usa Brave/Chrome si están instalados)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BROWSER="${CHROME_BIN:-}"
for candidate in "/Applications/Brave Browser.app/Contents/MacOS/Brave Browser" "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" "/Applications/Chromium.app/Contents/MacOS/Chromium"; do
  [ -n "$BROWSER" ] && break
  [ -x "$candidate" ] && BROWSER="$candidate"
done
[ -n "$BROWSER" ] || { echo "No se encontró un navegador Chromium (define CHROME_BIN)" >&2; exit 1; }
OUT="$ROOT/front-presencard/public/og-image.png"
"$BROWSER" --headless --disable-gpu --hide-scrollbars --force-device-scale-factor=1 --window-size=1200,630 \
  --screenshot="$OUT" "file://$ROOT/scripts/og-image/og-image.html" >/dev/null 2>&1
echo "Generada: $OUT"
