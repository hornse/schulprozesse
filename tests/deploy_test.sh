#!/bin/bash
# ============================================================
# tests/deploy_test.sh – deploy.sh lässt vor dem Commit die Suite laufen
# (S74, koordination AUFTRAG-doku-hilfe-S74.md, A4; REIHENREGELN 6)
#
# AUSGEFÜHRT, nicht am Quelltext: deploy.sh läuft in einem Probe-Klon des
# Arbeitsbaums (verfolgte und neue, nicht ignorierte Dateien), dessen
# Gegenstellen „github“ und „uberspace“ zwei LEERE Bare-Repos unter
# mktemp sind. Nach außen geht nichts; das prüft die Voraussetzung.
#
#   rot:  eine Prüfung der Suite fällt → deploy.sh endet mit Fehler, kein
#         Commit des Stempels, keine Gegenstelle bekommt etwas.
#   grün: die Suite läuft (ihre Schlusszeile steht in der Ausgabe), dann
#         Stempel-Commit und Push an beide Gegenstellen.
#
# Die Suite im Probe-Klon ruft diese Prüfung nicht noch einmal auf
# (SCHULPROZESSE_DEPLOY_PROBE=1) – sonst liefe sie ohne Ende ineinander.
#
# Aufruf: tests/deploy_test.sh   → Exit 0 = grün, 3 = innerhalb der Probe
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
R="$(cd "$(dirname "$0")/.." && pwd)"

if [ "${SCHULPROZESSE_DEPLOY_PROBE:-0}" = "1" ]; then
    echo "  ÜBERSPRUNGEN: innerhalb der Probe von deploy.sh"
    exit 3
fi

ROT=0
gruen() { echo "  ✓ $1"; }
rot()   { echo "  ✗ $1"; ROT=$((ROT + 1)); }

T="$(mktemp -d)"
trap 'rm -rf "$T"' EXIT
K="$T/klon"

# ---- Probe-Klon -------------------------------------------------------
git clone -q "$R" "$K" 2> "$T/fehler.txt"
# Der Arbeitsbaum, wie er ist: verfolgte und neue, nicht ignorierte Dateien.
(cd "$R" && git ls-files -co --exclude-standard -z) \
    | (cd "$R" && rsync -a --from0 --files-from=- ./ "$K/") 2>> "$T/fehler.txt"
git -C "$K" config user.name "Probe"
git -C "$K" config user.email "probe@example.invalid"
git -C "$K" remote remove origin
# Im Probe-Klon ist alles Übernommene gewollt; namentlich zu addieren gäbe
# es hier nichts zu unterscheiden.
git -C "$K" add -A
git -C "$K" commit -q -m "Probe: Arbeitsbaum" --allow-empty
git init -q --bare "$T/github.git"
git init -q --bare "$T/uberspace.git"
git -C "$K" remote add github "$T/github.git"
git -C "$K" remote add uberspace "$T/uberspace.git"
START=$(git -C "$K" rev-parse HEAD)

leer() { [ -z "$(git -C "$1" for-each-ref refs/heads)" ]; }
nur_lokal=1
for r in $(git -C "$K" remote); do
    case "$(git -C "$K" remote get-url "$r")" in "$T"/*) ;; *) nur_lokal=0 ;; esac
done
if [ "$nur_lokal" = 1 ] && [ "$(git -C "$K" remote | sort | tr '\n' ' ')" = "github uberspace " ] \
   && leer "$T/github.git" && leer "$T/uberspace.git" && [ -z "$(git -C "$K" status --porcelain)" ] \
   && [ -x "$K/deploy.sh" ]; then
    gruen "Voraussetzung: Probe-Klon des Arbeitsbaums, Gegenstellen nur lokal und leer"
else
    rot "Voraussetzung: Probe-Klon nicht wie verlangt ($(tr '\n' ' ' < "$T/fehler.txt"))"
    exit 1
fi

lauf() { (cd "$K" && SCHULPROZESSE_DEPLOY_PROBE=1 ./deploy.sh "$1" < /dev/null > "$T/aus.txt" 2>&1); echo $?; }

# ---- rot: eine Prüfung fällt ------------------------------------------
# config.php nicht mehr ausgeschlossen – die Suite prüft das im Abschnitt
# „Repository“.
grep -v '^config/config.php$' "$K/.gitignore" > "$T/gi" && cp "$T/gi" "$K/.gitignore"
git -C "$K" commit -q -am "Probe: Suite rot"
ROT_KOPF=$(git -C "$K" rev-parse HEAD)
E=$(lauf "Probe rot")
if [ "$E" -ne 0 ] && [ "$(git -C "$K" rev-parse HEAD)" = "$ROT_KOPF" ] \
   && leer "$T/github.git" && leer "$T/uberspace.git" \
   && grep -q '✗ config.php nicht ausgeschlossen' "$T/aus.txt"; then
    gruen "deploy.sh bei roter Suite: Abbruch vor dem Commit, keine Gegenstelle bekommt etwas"
else
    rot "deploy.sh bei roter Suite: Exit $E, Kopf $( [ "$(git -C "$K" rev-parse HEAD)" = "$ROT_KOPF" ] && echo unverändert || echo VERÄNDERT ), github $(leer "$T/github.git" && echo leer || echo BEKAM ETWAS), uberspace $(leer "$T/uberspace.git" && echo leer || echo BEKAM ETWAS)"
fi

# ---- grün: die Suite läuft, dann Commit und Push ----------------------
git -C "$K" reset -q --hard "$START"
E=$(lauf "Probe grün")
KOPF=$(git -C "$K" rev-parse HEAD)
if [ "$E" -eq 0 ] && grep -q '^ALLES GRÜN' "$T/aus.txt" && [ "$KOPF" != "$START" ] \
   && [ "$(git -C "$T/github.git" rev-parse -q --verify refs/heads/main)" = "$KOPF" ] \
   && [ "$(git -C "$T/uberspace.git" rev-parse -q --verify refs/heads/main)" = "$KOPF" ]; then
    gruen "deploy.sh bei grüner Suite: Suite gelaufen, Stempel committet, beide Gegenstellen auf dem neuen Stand"
else
    rot "deploy.sh bei grüner Suite: Exit $E, Suite $(if grep -q '^ALLES GRÜN' "$T/aus.txt"; then echo grün; elif grep -q '^[0-9]* FEHLER' "$T/aus.txt"; then echo "ROT ($(grep '^[0-9]* FEHLER' "$T/aus.txt"))"; else echo 'NICHT GELAUFEN'; fi), Gegenstellen $(git -C "$T/github.git" rev-parse -q --verify refs/heads/main >/dev/null && echo bekamen || echo 'leer')"
fi

exit $([ "$ROT" -eq 0 ] && echo 0 || echo 1)
