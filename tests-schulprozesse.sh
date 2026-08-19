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
echo "Gruppierung"
if command -v node > /dev/null 2>&1; then
    if node tests/gruppierung.test.js > /tmp/gruppierung-test.$$.log 2>&1; then
        gruen "Phasen-Gruppierung ist robust gegen nicht zusammenhängende Zeilen"
    else
        rot "Phasen-Gruppierung erzeugt Dubletten (siehe tests/gruppierung.test.js)"
        sed 's/^/      /' /tmp/gruppierung-test.$$.log
    fi
    rm -f /tmp/gruppierung-test.$$.log
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
echo "Gerüst"
grep -q 'ci-huelle--kopf' "$HTML" \
    && gruen "Kopfleisten-Variante eingebunden" || rot "kein ci-huelle--kopf"
for D in ci-komponenten.css ci-shell.css ci-shell.js ci-icons.svg; do
    [ -f "backend/public/vendor/ci-css/$D" ] && gruen "$D vendored" || rot "$D fehlt"
done
grep -q 'ci-shell.js' "$HTML" \
    && gruen "ci-shell.js eingebunden" || rot "ci-shell.js fehlt"
grep -q "aria-current" "$JS" \
    && gruen "aktiver Punkt über aria-current" || rot "aktiver Punkt nur über Klasse"
grep -qE '^#app-shell|^\.shell-brand|^\.nav-tab' "$CSS" \
    && rot "eigene Shell-Regeln noch vorhanden" || gruen "keine doppelten Shell-Regeln"
# Entschieden: helle Leiste in allen fünf Anwendungen.
grep -q 'ci-leiste--farbig' "$HTML" \
    && rot "farbige Leiste – entschieden ist hell für alle" \
    || gruen "helle Leiste wie in der übrigen Reihe"
# Die Prozess-Tabs kennt kein anderes Projekt und bleiben hier.
grep -q '^\.prozess-leiste' "$CSS" \
    && gruen "Prozess-Tabs bleiben projekteigen" || rot "Prozess-Tabs verschwunden"
grep -q 'logoAnzeigen' "$JS" \
    && gruen "Logo wird nach dem Laden eingeblendet" || rot "kein Logo-Umschalter"

FEHLENDE=""
for N in $(grep -oE "ci-i-[a-z]+" "$JS" "$HTML" | sed 's/.*://' | sort -u); do
    grep -q "id=\"$N\"" backend/public/vendor/ci-css/ci-icons.svg || FEHLENDE="$FEHLENDE $N"
done
[ -z "$FEHLENDE" ] && gruen "alle benutzten Symbole existieren im Sprite" \
    || rot "im Sprite fehlen:$FEHLENDE"

# app.js setzt Klassen, die das Modul NICHT kennt (btn, admin-section …).
# Wer eine Regel dafür löscht, weil „das Modul das abdeckt", steht ohne
# Gestaltung da – genau das ist bei fachkonferenzen passiert.
#
# Die folgenden sieben Klassen waren schon vor dem Umbau (Stand
# 0e6f344) ohne Regel und werden auch nicht per querySelector gesucht.
# Vermutlich Überbleibsel. Sie hier zu melden hieße, Bestandszustand
# als Fehler auszugeben – deshalb ausgenommen und benannt, statt die
# Prüfung insgesamt weicher zu machen.
# prozess-tabs stand hier, bis auffiel, dass die fehlende Regel die
# Tab-Zeile der oeffentlichen Ansicht stapeln liess. Was auf dieser
# Liste steht, gehoert regelmaessig ueberprueft - „war schon immer
# ohne Regel" heisst nicht „braucht keine".
OHNE_REGEL_BEKANNT="dash-schuljahr gantt-schritt-tr hilfe-inhalt \
neuer-schritt-titel phasen-farb-btn zeitstrahl-inhalt"

NUTZT=$(grep -oE "className *= *'[a-z][a-z0-9 _-]*'" "$JS" | sed "s/.*'\(.*\)'/\1/" \
        | tr ' ' '\n' | sort -u | grep -vE '^(ci-|$)')
FEHLT=""
for K in $NUTZT; do
    case " $OHNE_REGEL_BEKANNT " in *" $K "*) continue ;; esac
    grep -qE "\\.$K[ ,.{:]" "$CSS" || FEHLT="$FEHLT $K"
done
[ -z "$FEHLT" ] && gruen "alle gestylten Klassen haben weiterhin ihre Regel" \
    || rot "Regel entfallen für:$FEHLT"

echo ""
echo "Behobene Mängel"
grep -q 'class="skip-link"' "$HTML" && gruen "Sprungmarke vorhanden" || rot "keine Sprungmarke"
grep -q 'id="app" tabindex="-1"' "$HTML" \
    && gruen "Hauptbereich ist Sprung- und Fokusziel" || rot "Hauptbereich nicht fokussierbar"
grep -q "fokusAufInhalt" "$JS" \
    && gruen "Fokus springt nach dem Ansichtswechsel" || rot "kein Fokussprung"
grep -q 'aria-label="Hauptnavigation"' "$HTML" \
    && gruen "Navigation ist benannt" || rot "Navigation ohne aria-label"
# Das <img> steht seit v2.4.0 über mehrere Zeilen; deshalb über die
# ganze Datei prüfen statt zeilenweise.
perl -0777 -ne 'exit(!(/id="shell-logo"[^>]*alt=""/s))' "$HTML" \
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
