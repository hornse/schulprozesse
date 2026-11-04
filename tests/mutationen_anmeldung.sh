#!/bin/bash
# ============================================================
# tests/mutationen_anmeldung.sh – prüft die Prüfungen aus S74
# (koordination AUFTRAG-doku-hilfe-S74.md)
#
# Jede Mutation wird an einer KOPIE des Arbeitsbaums angebracht (ein
# git-Klon, darüber der Arbeitsbaum wie er ist), nicht im Repo. Je Mutation:
#   - Nachweis, dass sie angekommen ist (Prüfsumme der Datei vorher ≠ nachher),
#   - die zuständige Prüfung läuft IN DER KOPIE,
#   - erwartet: die genannte Zeile wird rot – oder, bei „harmlos“, nichts.
# Zum Schluss: das Repo ist unverändert (Prüfsumme über git diff und die
# Liste der unverfolgten Dateien, vorher und nachher).
#
#   A…  tests/anmeldung_test.php    (handleLogin, Sätze, Schwelle)
#   B…  tests/browser_test.mjs      (Anmeldung und Hilfe im Browser)
#   H…  tests/handbuch_test.php
#   D…  tests/deploy_test.sh        (deploy.sh im Probe-Klon)
#   E…  Einbindung: Ist ein Teil rot, ist tests-schulprozesse.sh rot, an der
#       erwarteten Zeile.
#
# Wird nicht von tests-schulprozesse.sh aufgerufen. Aufruf im Projektordner:
#   ./tests/mutationen_anmeldung.sh
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
R="$(cd "$(dirname "$0")/.." && pwd)"
T="$(mktemp -d)"
trap 'rm -rf "$T"' EXIT

stand() { (cd "$R" && { git diff; git ls-files -o --exclude-standard; } | shasum | cut -c1-12); }
REPO_VORHER=$(stand)

# Grundkopie: Klon plus Arbeitsbaum.
git clone -q "$R" "$T/basis"
(cd "$R" && git ls-files -co --exclude-standard -z) | (cd "$R" && rsync -a --from0 --files-from=- ./ "$T/basis/")
K="$T/k"

FEHLT=0; GELAUFEN=0
# mut NAME DATEI PERL-AUSDRUCK WOMIT ERWARTETE-ZEILE   (ERWARTETE-ZEILE „-“ = harmlos)
mut() {
  local name=$1 datei=$2 ausdruck=$3 womit=$4 erwartet=$5 vor nach aus e treffer rote
  GELAUFEN=$((GELAUFEN + 1))
  rsync -a --delete "$T/basis/" "$K/"
  vor=$(shasum "$K/$datei" | cut -c1-12)
  perl -0777 -i -pe "$ausdruck" "$K/$datei"
  nach=$(shasum "$K/$datei" | cut -c1-12)
  if [ "$vor" = "$nach" ]; then echo "$name: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; FEHLT=$((FEHLT + 1)); return; fi
  case "$womit" in
    anmeldung) aus=$(php "$K/tests/anmeldung_test.php" 2>/dev/null); e=$? ;;
    handbuch)  aus=$(php "$K/tests/handbuch_test.php" 2>/dev/null); e=$? ;;
    browser)   aus=$(node "$K/tests/browser_test.mjs" "$K/backend/public" "$K" 2>/dev/null); e=$? ;;
    deploy)    aus=$("$K/tests/deploy_test.sh" 2>/dev/null); e=$? ;;
    suite)     aus=$(cd "$K" && ./tests-schulprozesse.sh 2>/dev/null); e=$? ;;
  esac
  rote=$(printf '%s\n' "$aus" | grep -c '✗' || true)
  if [ "$erwartet" = "-" ]; then
    if [ "$e" -eq 0 ] && [ "$rote" -eq 0 ]; then echo "$name: richtig nicht angeschlagen (harmlos, grün)"
    else echo "$name: FALSCH ANGESCHLAGEN (exit=$e)"; printf '%s\n' "$aus" | grep '✗' | sed 's/^/      /'; FEHLT=$((FEHLT + 1)); fi
    return
  fi
  treffer=$(printf '%s\n' "$aus" | grep -cF "✗ $erwartet" || true)
  if [ "$e" -ne 0 ] && [ "$treffer" -ge 1 ]; then echo "$name: angeschlagen („$erwartet“)"
  else echo "$name: NICHT ANGESCHLAGEN (exit=$e, „$erwartet“ rot: $treffer)"; printf '%s\n' "$aus" | grep '✗' | sed 's/^/      /'; FEHLT=$((FEHLT + 1)); fi
}

A=backend/api/auth.php
B=backend/src/Auth/Anmeldebremse.php
J=backend/public/js/app.js
HB=docs/BENUTZERHANDBUCH.md

# ---- A: Antworten der Anmeldung -----------------------------------------
mut A1 $A 's/(\x27nicht_freigegeben\x27\);\n        Response::error\(Anmeldebremse::SATZ_ABGELEHNT, )401/${1}403/' anmeldung \
  "Passwort richtig, nicht freigegeben"
mut A2 $A 's/(        \} else \{\n            Anmeldebremse::abschliessen\(\$db, \$versuch, false, \$auth->grund\(\)\);\n        \}\n        Response::error\()Anmeldebremse::SATZ_ABGELEHNT/${1}\$auth->grund() === \x27falsche_rolle\x27 ? \x27Kontotyp nicht zugelassen.\x27 : Anmeldebremse::SATZ_ABGELEHNT/' anmeldung \
  "Passwort richtig, Kontotyp nicht zugelassen (Eltern)"
mut A3 $A 's/(if \(trim\(\$username\) === \x27\x27 \|\| \$password === \x27\x27\) \{\n        Response::error\(Anmeldebremse::SATZ_ABGELEHNT, )401/${1}400/' anmeldung \
  "leere Eingabe"
mut A4 $A 's/Response::error\(Anmeldebremse::SATZ_AUSFALL, 503\)/Response::error(\x27WebUntis ist gerade nicht erreichbar. Bitte in ein paar Minuten erneut versuchen.\x27, 503)/' anmeldung \
  "Ablehnungs- und Ausfallsatz sind Konstanten"
mut A5 $A 's/        Anmeldebremse::SCHWELLE,\n/        (int) (\$config[\x27security\x27][\x27max_failed_logins\x27] ?? 5),\n/' anmeldung \
  "Konfiguration sagt max_failed_logins 5"
mut A6 $B 's/public const SCHWELLE = 8;/public const SCHWELLE = 9;/' anmeldung "Anmeldebremse::SCHWELLE ist 8"
mut A7 config/config.example.php 's/(\x27security\x27 => \[\n)/${1}        \x27max_failed_logins\x27 => 8,\n/' anmeldung \
  "config.example.php führt keinen Schlüssel max_failed_logins"
mut A8 $B 's/Bitte in ein paar Minuten erneut versuchen\.\x27;/Bitte gleich noch einmal versuchen.\x27;/' anmeldung \
  "Ablehnungs- und Ausfallsatz sind Konstanten"
# harmlos: der Schlüssel als Kommentar in der Vorlage
mut A9 config/config.example.php 's/(\x27security\x27 => \[\n)/${1}        \/\/ früher: \x27max_failed_logins\x27 => 5,\n/' anmeldung "-"

# ---- B: im Browser ------------------------------------------------------
mut B1 $J 's/body: \{ username, password \}, anmeldeweg: true \}/body: { username, password } }/' browser \
  "falsches Passwort (401): der Satz des Servers"
mut B2 $J 's/api\(\x27\/api\/me\x27, \{ anmeldeweg: true \}\)/api(\x27\/api\/me\x27)/' browser \
  "Start ohne Anmeldung"
mut B3 $J 's/if \(res\.status === 401 && !anmeldeweg\)/if (res.status === 401 \&\& false)/' browser \
  "Sitzung abgelaufen"
mut B4 $J 's/(    \$fehler\.innerHTML = \x27\x27;\n)/${1}    wrapper.querySelector(\x27#login-user\x27).value = \x27\x27;\n/' browser \
  "falsches Passwort (401): der Benutzername bleibt stehen"
mut B5 $J 's/<button class="btn" type="submit">Anmelden<\/button>/<button class="btn" type="button">Anmelden<\/button>/' browser \
  "Enter im Passwortfeld"
mut B6 $J 's/Nach acht Fehlversuchen heißt es/Nach fünf Fehlversuchen heißt es/' browser "Hilfe nennt die Schwelle"
mut B7 $J 's/(Eine gelungene Anmeldung setzt die Zählung zurück\.`)/Wenn das Passwort stimmte, fehlt dir die Freigabe. $1/' browser \
  "Hilfe: kein Satz, den es nach FALLSTRICKE 8"
mut B8 $J 's/  navTab\(\x27hilfe\x27, \x27hilfe\x27, \x27Hilfe\x27\);/  if (STATE.user) navTab(\x27hilfe\x27, \x27hilfe\x27, \x27Hilfe\x27);/' browser \
  "Hilfe ohne Anmeldung, Desktop"
mut B9 $B 's/Bitte in ein paar Minuten erneut versuchen\.\x27;/Bitte gleich noch einmal versuchen.\x27;/' browser \
  "Hilfe zitiert den Ausfallsatz"
# harmlos: ein Kommentar
mut B10 $J 's/(async function doLogin\()/\/\/ Anmelden (harmlos geändert)\n$1/' browser "-"

# ---- H: Handbuch --------------------------------------------------------
mut H1 $HB 's/Nach acht Fehlversuchen heißt es/Nach fünf Fehlversuchen heißt es/' handbuch "Handbuch nennt die Schwelle"
mut H2 $HB 's/- \*\*Erste Schritte\*\* – Karten zu den Grundlagen/- **Erste Schritte** – 6 Karten zu den Grundlagen/' handbuch \
  "Handbuch zählt Reiter, Karten und Fragen"
mut H3 $B 's/Bitte Zugangsdaten prüfen\.\x27;/Bitte die Zugangsdaten prüfen.\x27;/' handbuch "Handbuch zitiert den Ablehnungssatz"
# harmlos: die falsche Fassung in einem HTML-Kommentar
mut H4 $HB 's/(## Anmeldung: was die Meldungen heißen\n)/$1\n<!-- früher: Nach fünf Fehlversuchen; zwei Bereiche, 10 Fragen -->\n/' handbuch "-"

# ---- D: deploy.sh -------------------------------------------------------
mut D1 deploy.sh 's/if ! \.\/tests-schulprozesse\.sh; then\n  echo "!! Pruefungen rot – keine Auslieferung\." >&2\n  exit 1\nfi\n//' deploy \
  "deploy.sh bei grüner Suite"
mut D2 deploy.sh 's/if ! \.\/tests-schulprozesse\.sh; then/if ! .\/tests-schulprozesse.sh \&\& false; then/' deploy \
  "deploy.sh bei roter Suite"
mut D3 deploy.sh 's/  exit 1\nfi\n\nMSG=/  :\nfi\n\nMSG=/' deploy "deploy.sh bei roter Suite"

# ---- E: Einbindung in tests-schulprozesse.sh ----------------------------
mut E1 $B 's/public const SCHWELLE = 8;/public const SCHWELLE = 9;/' suite "Anmeldung: Anmeldebremse::SCHWELLE ist 8"
mut E2 $J 's/Nach acht Fehlversuchen heißt es/Nach fünf Fehlversuchen heißt es/' suite "Browser: Hilfe nennt die Schwelle"
mut E3 $HB 's/Nach acht Fehlversuchen heißt es/Nach fünf Fehlversuchen heißt es/' suite "Handbuch: Handbuch nennt die Schwelle"
mut E4 deploy.sh 's/if ! \.\/tests-schulprozesse\.sh; then/if ! .\/tests-schulprozesse.sh \&\& false; then/' suite \
  "deploy.sh: deploy.sh bei roter Suite"
mut E5 docs/INSTALL.md 's/\x27allowed_person_types\x27 => \[2, 16\]/\x27allowed_person_types\x27 => [2]/' suite "personType"

echo ""
REPO_NACHHER=$(stand)
echo "Repo vorher $REPO_VORHER, nachher $REPO_NACHHER $([ "$REPO_VORHER" = "$REPO_NACHHER" ] && echo '– unverändert' || echo '– VERÄNDERT')"
[ "$REPO_VORHER" = "$REPO_NACHHER" ] || FEHLT=$((FEHLT + 1))
if [ "$FEHLT" -eq 0 ]; then echo "ALLE $GELAUFEN MUTATIONEN BELEGT"; exit 0; fi
echo "$FEHLT VON $GELAUFEN MUTATION(EN) OHNE BELEG"; exit 1
