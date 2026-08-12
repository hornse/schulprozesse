#!/usr/bin/env bash
# ============================================================
# tests-schulprozesse.sh – Prüfung des CI-Umbaus
#
# Aufruf im Projektordner:  ./tests-schulprozesse.sh
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -uo pipefail
export LC_ALL=C
cd "$(dirname "$0")"

FEHLER=0
gruen() { echo "  ✓ $1"; }
rot()   { echo "  ✗ $1"; FEHLER=$((FEHLER + 1)); }

CSS=backend/public/css/style.css
JS=backend/public/js/app.js
HTML=backend/public/index.html
TOK=backend/public/vendor/ci-css/ci-tokens.css

echo "Dateien"
for D in "$CSS" "$JS" "$HTML" "$TOK"; do
    [ -f "$D" ] && gruen "$D vorhanden" || rot "$D fehlt"
done

echo ""
echo "Aufbau"
AUF=$(tr -cd '{' < "$CSS" | wc -c | tr -d ' ')
ZU=$(tr -cd '}' < "$CSS" | wc -c | tr -d ' ')
[ "$AUF" -eq "$ZU" ] && gruen "Klammern ausgeglichen ($AUF)" \
    || rot "$AUF öffnende, $ZU schließende Klammern"
if command -v node > /dev/null 2>&1; then
    node --check "$JS" > /dev/null 2>&1 \
        && gruen "app.js ist syntaktisch fehlerfrei" || rot "app.js hat einen Syntaxfehler"
else
    echo "  –  node nicht vorhanden"
fi

echo ""
echo "Datenschutz"
# Kommentare vorher entfernen: Die Erklärung, warum der @import weg ist,
# darf nicht als Fund gelten.
OHNE=$(perl -0777 -pe 's{/\*.*?\*/}{}gs' "$CSS"; perl -0777 -pe 's{<!--.*?-->}{}gs' "$HTML")
printf '%s' "$OHNE" | grep -qi "googleapis\|gstatic\|fonts.google" \
    && rot "externe Schriften werden noch geladen" \
    || gruen "keine externen Schriften mehr"
printf '%s' "$OHNE" | grep -qE "https?://[a-z]" \
    && rot "externe Ressource in CSS oder HTML" || gruen "keine externen Ressourcen"

echo ""
echo "Keine Rohfarben außerhalb des :root-Blocks"
REST=$(perl -0777 -pe 's{/\*.*?\*/}{}gs' "$CSS" | perl -0777 -pe 's{^.*?\n\}\n}{}s')
TREFFER=$(printf '%s' "$REST" | grep -oE '#[0-9a-fA-F]{3,8}\b' | sort -u || true)
[ -z "$TREFFER" ] && gruen "keine Hexfarben" || rot "Hexfarben: $(echo "$TREFFER" | tr '\n' ' ')"
TREFFER=$(printf '%s' "$REST" | grep -oE 'rgba?\([^)]*\)' | sort -u || true)
[ -z "$TREFFER" ] && gruen "keine rgb/rgba-Angaben" || rot "rgba: $(echo "$TREFFER" | tr '\n' ' ')"

echo ""
echo "Tokens vollständig"
UNBEKANNT=""
for V in $(grep -ohE 'var\(--ci-[a-z0-9-]+' "$CSS" | sed 's/var(//' | sort -u); do
    grep -qE "^[[:space:]]*$V:" "$TOK" || UNBEKANNT="$UNBEKANNT $V"
done
[ -z "$UNBEKANNT" ] && gruen "alle benutzten ci-Tokens sind definiert" \
    || rot "nicht definiert:$UNBEKANNT"

echo ""
echo "Einbindung"
grep -q 'data-projekt="schulprozesse"' "$HTML" \
    && gruen "Projektfarbe gesetzt" || rot "data-projekt fehlt"
grep -q 'vendor/ci-css/ci-tokens.css' "$HTML" \
    && gruen "Tokens eingebunden" || rot "Tokens nicht eingebunden"

echo ""
echo "Behobene Mängel"
grep -q 'class="skip-link"' "$HTML" && gruen "Sprungmarke vorhanden" || rot "keine Sprungmarke"
grep -q 'id="app" tabindex="-1"' "$HTML" \
    && gruen "Hauptbereich ist Sprung- und Fokusziel" || rot "Hauptbereich nicht fokussierbar"
grep -q "fokusAufInhalt" "$JS" \
    && gruen "Fokus springt nach dem Ansichtswechsel" || rot "kein Fokussprung"
grep -q 'aria-label="Hauptnavigation"' "$HTML" \
    && gruen "Navigation ist benannt" || rot "Navigation ohne aria-label"
grep -q 'id="shell-logo".*alt=""' "$HTML" \
    && gruen "Logo ist als dekorativ ausgezeichnet" || rot "alt am Logo prüfen"
grep -q -- '--accent:var(--ci-akzent)' "$CSS" \
    && gruen "--accent nutzt das Token (6.93 statt 4.46)" || rot "--accent mit eigenem Wert"
grep -q -- '--error:var(--ci-fehler)' "$CSS" \
    && gruen "--error nutzt das Token (4.15 war zu wenig)" || rot "--error mit eigenem Wert"

echo ""
echo "Repository"
[ -f .gitignore ] && gruen ".gitignore vorhanden" || rot "keine .gitignore"
grep -q "DS_Store" .gitignore 2>/dev/null \
    && gruen ".DS_Store ausgeschlossen" || rot ".DS_Store nicht ausgeschlossen"
grep -q "config/config.php" .gitignore 2>/dev/null \
    && gruen "config.php ausgeschlossen" || rot "config.php nicht ausgeschlossen"

echo ""
if [ "$FEHLER" -eq 0 ]; then echo "ALLES GRÜN"; exit 0; fi
echo "$FEHLER FEHLER"; exit 1
