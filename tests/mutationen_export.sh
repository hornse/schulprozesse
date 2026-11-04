#!/bin/bash
# ============================================================
# tests/mutationen_export.sh – prüft die Prüfungen aus Z3 und zu fputcsv (PHP 8.4)
# (koordination AUFTRAG-schulprozesse-export.md)
#
# Jede Mutation wird an einer KOPIE des Arbeitsbaums angebracht (git-Klon,
# darüber der Arbeitsbaum wie er ist), nicht im Repo. Je Mutation:
#   - Nachweis, dass sie angekommen ist (Prüfsumme vorher ≠ nachher),
#   - tests/export_zugriff_test.php läuft IN DER KOPIE,
#   - erwartet: GENAU die genannten Zeilen sind rot (Zahl und Wortlaut).
# Vor der Serie: unveränderter Lauf, grün, jede Zeile genau einmal.
# Zum Schluss: das Repo ist unverändert (git diff und unverfolgte Dateien).
#
# Wird nicht von tests-schulprozesse.sh aufgerufen. Aufruf im Projektordner:
#   ./tests/mutationen_export.sh
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
R="$(cd "$(dirname "$0")/.." && pwd)"
T="$(mktemp -d)"
trap 'rm -rf "${T:?}"' EXIT

stand() { (cd "$R" && { git diff; git ls-files -o --exclude-standard; } | shasum | cut -c1-12); }
REPO_VORHER=$(stand)

git clone -q "$R" "$T/basis"
(cd "$R" && git ls-files -co --exclude-standard -z) | (cd "$R" && rsync -a --from0 --files-from=- ./ "$T/basis/")
K="$T/k"

FEHLT=0; GELAUFEN=0
TEST=tests/export_zugriff_test.php

# Vorprüfung am unveränderten Stand
AUS=$(cd "$T/basis" && php "$TEST" 2>&1); EX=$?
GRUEN=$(printf '%s\n' "$AUS" | grep -c '^  ✓ ')
DOPPELT=$(printf '%s\n' "$AUS" | grep -E '^  [✓✗] ' | sort | uniq -d | wc -l | tr -d ' ')
if [ "$EX" -ne 0 ] || [ "$GRUEN" -eq 0 ] || [ "$DOPPELT" -ne 0 ]; then
    echo "  ✗ Vorprüfung: Exit $EX, $GRUEN grün, $DOPPELT doppelte Zeilen – Serie nicht gelaufen"
    exit 1
fi
echo "  ✓ Vorprüfung: unverändert grün, $GRUEN Zeilen, keine doppelt"

# mutation <name> <datei> <n-tes Vorkommen> <alt> <neu> <erwartete Zeilen, je eine pro Zeile>
mutation() {
    local name=$1 datei=$2 n=$3 alt=$4 neu=$5 erwartet=$6
    rm -rf "${K:?}"; cp -R "$T/basis" "$K"
    local vorher nachher
    vorher=$(shasum < "$K/$datei")
    python3 - "$K/$datei" "$n" "$alt" "$neu" <<'PY' || { echo "  ✗ $name: Muster nicht gefunden – nicht angekommen"; FEHLT=$((FEHLT + 1)); return; }
import sys
p, n, alt, neu = sys.argv[1], int(sys.argv[2]), sys.argv[3], sys.argv[4]
s = open(p, encoding='utf-8').read()
t = s.split(alt)
if len(t) - 1 < n: sys.exit(1)
open(p, 'w', encoding='utf-8').write(alt.join(t[:n]) + neu + alt.join(t[n:]))
PY
    nachher=$(shasum < "$K/$datei")
    if [ "$vorher" = "$nachher" ]; then
        echo "  ✗ $name: Datei unverändert – nicht angekommen"; FEHLT=$((FEHLT + 1)); return
    fi
    GELAUFEN=$((GELAUFEN + 1))
    local rot soll ist z ok=1
    rot=$(cd "$K" && php "$TEST" 2>&1 | grep '^  ✗ ')
    soll=$(printf '%s\n' "$erwartet" | grep -c .)
    ist=$(printf '%s' "$rot" | grep -c . || true)
    [ "$ist" -eq "$soll" ] || ok=0
    while IFS= read -r z; do
        [ -z "$z" ] && continue
        printf '%s\n' "$rot" | grep -qF -- "$z" || ok=0
    done <<< "$erwartet"
    if [ "$ok" -eq 1 ]; then
        echo "  ✓ $name: $ist rot, wie erwartet"
    else
        echo "  ✗ $name: $ist rot, erwartet $soll"; printf '%s\n' "$rot" | sed 's/^/        /'
        FEHLT=$((FEHLT + 1))
    fi
}

E=backend/api/export.php
ZG='    Guard::requireProzessZugriff($db, $prozessId);'
H=("GET /api/aktivitaeten:" "GET /api/export/aktivitaeten:" "GET /api/export/csv:")
F=(handleListAktivitaeten handleExportAktivitaeten handleExportCsv)

for i in 0 1 2; do
    mutation "M$((i + 1)) Prüfung entfernt (${F[$i]})" "$E" $((i + 1)) "$ZG"$'\n' "" \
        "${H[$i]} Mitglied ohne Teilnahme an P41
${H[$i]} Mitglied ohne Teilnahme am öffentlichen P43
OHNE: ${F[$i]}"
done
# Entwertet trifft jeden Aufruf eines Teilnehmers – bei den beiden CSV-Endpunkten
# also auch die Zeile „ohne Hinweis“, die als Teilnehmer exportiert.
mutation "M4 Prüfung entwertet, ID 0 (${F[0]})" "$E" 1 "$ZG" '    Guard::requireProzessZugriff($db, 0);' \
    "${H[0]} Teilnehmer an P41"
for i in 1 2; do
    mutation "M$((i + 4)) Prüfung entwertet, ID 0 (${F[$i]})" "$E" $((i + 1)) "$ZG" '    Guard::requireProzessZugriff($db, 0);' \
        "${H[$i]} Teilnehmer an P41
${H[$i]} Teilnehmer bekommt 200 mit"
done
mutation "M7 Rückfall auf den jüngsten Prozess statt 400 (${F[0]})" "$E" 1 \
    "        Response::error('prozess_id fehlt.', 400);" \
    "        \$prozessId = (int) \$db->query('SELECT id FROM prozesse ORDER BY erstellt_am DESC LIMIT 1')->fetchColumn();" \
    "${H[0]} fehlend, „null“ und 0"
mutation "M8 Grenze verschoben, <= 0 zu < 0 (${F[1]})" "$E" 2 'if ($prozessId <= 0) {' 'if ($prozessId < 0) {' \
    "${H[1]} fehlend, „null“ und 0"
mutation "M9 Code gelöscht, Kommentar bleibt (${F[2]})" "$E" 3 "$ZG" '    // Guard::requireProzessZugriff($db, $prozessId);' \
    "${H[2]} Mitglied ohne Teilnahme an P41
${H[2]} Mitglied ohne Teilnahme am öffentlichen P43
OHNE: ${F[2]}"

# fputcsv unter PHP 8.4: $escape an einem Aufruf weglassen → Hinweis im Export
# und die Prüfung am Quelltext. Erster Aufruf = Kopf des Aktivitäten-Exports,
# dritter = Kopf des Schritt-Exports.
FE="'\"', '\\\\');"
mutation "M10 \$escape fehlt (fputcsv 1, Aktivitäten-Export)" "$E" 1 "';', $FE" "';');" \
    "${H[1]} Teilnehmer bekommt 200 mit 3 Datensätzen und keinem Hinweis
nennen \$escape (am Quelltext) – OHNE: export.php:82"
mutation "M11 \$escape fehlt (fputcsv 3, Schritt-Export)" "$E" 3 "';', $FE" "';');" \
    "${H[2]} Teilnehmer bekommt 200 mit 2 Datensätzen und keinem Hinweis
nennen \$escape (am Quelltext) – OHNE: export.php:137"

REPO_NACHHER=$(stand)
if [ "$REPO_VORHER" = "$REPO_NACHHER" ]; then
    echo "  ✓ Repo unverändert ($REPO_VORHER)"
else
    echo "  ✗ Repo verändert: $REPO_VORHER → $REPO_NACHHER"; FEHLT=$((FEHLT + 1))
fi
echo ""
echo "$GELAUFEN Mutationen angekommen, $FEHLT Abweichungen"
[ "$FEHLT" -eq 0 ]
