#!/bin/bash
# ============================================================
# tests/mutationen_phasen.sh – prüft die Prüfungen aus Z4 (Phasen löschen)
# (koordination, Bericht 2026-11-04 schulprozesse-export)
#
# Jede Mutation wird an einer KOPIE des Arbeitsbaums angebracht (git-Klon,
# darüber der Arbeitsbaum wie er ist), nicht im Repo. Je Mutation:
#   - Nachweis, dass sie angekommen ist (Prüfsumme vorher ≠ nachher),
#   - tests/phasen_loeschen_test.php läuft IN DER KOPIE,
#   - erwartet: GENAU die genannten Zeilen sind rot (Zahl und Wortlaut).
# Vor der Serie: unveränderter Lauf, grün, jede Zeile genau einmal.
# Zum Schluss: das Repo ist unverändert (git diff und unverfolgte Dateien).
#
# Wird nicht von tests-schulprozesse.sh aufgerufen. Aufruf im Projektordner:
#   ./tests/mutationen_phasen.sh
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
TEST=tests/phasen_loeschen_test.php

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

P=backend/api/phasen.php
RA='    Guard::requireAdmin($db);'$'\n''
    $id = (int) $params['"'"'id'"'"'];'
ALT='    $user = Guard::requireLogin($db);
    if ($user['"'"'rolle'"'"'] !== '"'"'admin'"'"') {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM prozess_teilnehmer
             WHERE webuntis_user = :u AND rolle = '"'"'verantwortlich'"'"'"
        );
        $stmt->execute([":u" => $user["webuntis_user"]]);
        if (!(int) $stmt->fetchColumn()) {
            Response::error('"'"'Nur Admins und Verantwortliche können Phasen löschen.'"'"', 403);
        }
    }

    $id = (int) $params['"'"'id'"'"'];'
V="Verantwortlicher von P41"

mutation "N1 alte Regel zurück (verantwortlich irgendwo genügt)" "$P" 1 "$RA" "$ALT" \
    "$V, Phase mit Schritten nur in P42
$V, Phase mit Schritten nur in P41
$V, Standardphase
Mitarbeitender: 403"
# requireAdmin steht in phasen.php viermal; der Anker ist die Kommentarzeile
# davor, die es nur in handleDeletePhase gibt. (Mit „1. Vorkommen“ landete die
# Mutation einmal in handleCreatePhase – angekommen, aber am falschen Ort.)
ANKER='    // ändern die Phasen ihres Prozesses über /instanz-phasen.'$'\n'
mutation "N2 Prüfung entfernt" "$P" 1 "${ANKER}"'    Guard::requireAdmin($db);'$'\n' "${ANKER}" \
    "$V, Phase mit Schritten nur in P42
$V, Phase mit Schritten nur in P41
$V, Standardphase
Mitarbeitender: 403
ohne Anmeldung: 401"
mutation "N3 Prüfung entwertet (requireLogin statt requireAdmin)" "$P" 1 "${ANKER}"'    Guard::requireAdmin($db);' "${ANKER}"'    Guard::requireLogin($db);' \
    "$V, Phase mit Schritten nur in P42
$V, Phase mit Schritten nur in P41
$V, Standardphase
Mitarbeitender: 403"
mutation "N4 Löschen der Schritte entfällt (Admin-Weg)" "$P" 1 \
    "            \$db->prepare('DELETE FROM schritt_instanzen WHERE vorlage_id = :vid')" \
    "            \$db->prepare('SELECT 1 WHERE :vid IS NULL')" \
    "Admin löscht Phase 71"

REPO_NACHHER=$(stand)
if [ "$REPO_VORHER" = "$REPO_NACHHER" ]; then
    echo "  ✓ Repo unverändert ($REPO_VORHER)"
else
    echo "  ✗ Repo verändert: $REPO_VORHER → $REPO_NACHHER"; FEHLT=$((FEHLT + 1))
fi
echo ""
echo "$GELAUFEN Mutationen angekommen, $FEHLT Abweichungen"
[ "$FEHLT" -eq 0 ]
