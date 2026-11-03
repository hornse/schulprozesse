#!/bin/bash
# ============================================================
# tests/mutationen_sperrliste.sh – prüft die Prüfungen der Sperrliste
#
# Baut nacheinander bewusste Fehler in Sperrfunktion, Router und Kopie ein
# und belegt je Fehler, dass tests/sperrliste_test.php rot wird – an der
# erwarteten Zeile. Dazu:
#   E1  Ist die Suite rot, muss auch tests-schulprozesse.sh rot sein.
#   H1  Eine harmlose Änderung (ein Kommentar) darf NICHT anschlagen.
#
# Je Mutation: Vorprüfung, dass das Suchmuster GENAU EINMAL vorkommt →
# Sicherung → Eingriff → Nachweis, dass er angekommen ist (Prüfsumme anders,
# php -l heil) → Suite → Rücknahme aus der Sicherung (nie aus dem Index) →
# Prüfsumme gleich vorher. Am Ende: jede der drei Dateien gleich ihrem Stand
# vor der Serie (cmp).
#
# Übernommen aus projektstunden (Lauf 1, S61), dazu M8: der API-Zweig hinter
# die Sperre verschoben (Lauf 2, Entscheidung 2).
#
# Wird nicht von tests-schulprozesse.sh aufgerufen – sie verändert
# vorübergehend den Code. Aufruf im Projektordner:
#   ./tests/mutationen_sperrliste.sh
#
# SPDX-License-Identifier: GPL-3.0-or-later
# ============================================================
set -u
export LC_ALL=C
R="$(cd "$(dirname "$0")/.." && pwd)"
SICH="$(mktemp -d)"
OFFEN=""
FEHLT=0
GELAUFEN=0
summe() { shasum "$1" | cut -c1-12; }
zuruecknehmen() {
  if [ -n "$OFFEN" ]; then cp "$SICH/sicherung" "$OFFEN"; echo "Abbruch: offene Mutation in $OFFEN zurückgenommen."; fi
  rm -rf "$SICH"
}
trap zuruecknehmen EXIT

S=backend/src/sperrliste.php
RT=backend/public/dev-router.php
K=tests/daten/webroot.htaccess
for d in "$S" "$RT" "$K"; do cp "$R/$d" "$SICH/soll-$(basename "$d")"; done

# In der Hauptschale aufrufen, nie in $(…).
einbauen() {      # NAME DATEI SUCHMUSTER(perl) AUSDRUCK → 0 wenn angekommen und heil
  local n
  n=$(perl -0777 -ne "\$n = () = /$3/g; print \$n" "$2")
  if [ "$n" != "1" ]; then echo "$1: VORPRÜFUNG – Suchmuster kommt ${n}-mal vor, erwartet genau einmal"; return 1; fi
  VORHER=$(summe "$2"); cp "$2" "$SICH/sicherung"; OFFEN="$2"
  perl -0777 -i -pe "$4" "$2"
  if [ "$(summe "$2")" = "$VORHER" ]; then echo "$1: MUTATION NICHT ANGEKOMMEN"; return 1; fi
  case "$2" in *.php) php -l "$2" > /dev/null 2>&1 || { echo "$1: MUTATION KAPUTT – Syntaxfehler"; return 1; } ;; esac
}
ruecknahme() {    # NAME DATEI
  [ -n "$OFFEN" ] || return 0
  cp "$SICH/sicherung" "$2"; OFFEN=""
  [ "$(summe "$2")" = "$VORHER" ] || { echo "ABBRUCH: Rücknahme von $1 misslungen"; exit 2; }
}

# mut NAME DATEI SUCHMUSTER AUSDRUCK ERWARTETE-ROTE-ZEILE [ZWEITE-ROTE-ZEILE]
mut() {
  local name=$1 datei="$R/$2" muster=$3 ausdruck=$4 aus e t1 t2
  GELAUFEN=$((GELAUFEN + 1))
  if ! einbauen "$name" "$datei" "$muster" "$ausdruck"; then
    FEHLT=$((FEHLT + 1))
  else
    aus=$(php "$R/tests/sperrliste_test.php" 2>/dev/null); e=$?
    t1=$(printf '%s\n' "$aus" | grep -cF "✗ $5" || true)
    t2=1; [ -n "${6:-}" ] && t2=$(printf '%s\n' "$aus" | grep -cF "✗ $6" || true)
    if [ "$e" -ne 0 ] && [ "$t1" -ge 1 ] && [ "$t2" -ge 1 ]; then
      echo "$name: angeschlagen („$5“${6:+, „$6“}; rot gesamt: $(printf '%s\n' "$aus" | grep -c '✗'))"
    else
      echo "$name: NICHT ANGESCHLAGEN (exit=$e, „$5“: $t1${6:+, „$6“: $t2})"; FEHLT=$((FEHLT + 1))
    fi
  fi
  ruecknahme "$name" "$datei"
}

php "$R/tests/sperrliste_test.php" > /dev/null 2>&1 || { echo "ABBRUCH: Suite schon vor der Serie rot"; exit 2; }

# M1 – die Sperrfunktion gibt immer false
mut M1 $S 'function sp_pfad_gesperrt\(string \$pfad\): bool\n\{\n' \
  's/(function sp_pfad_gesperrt\(string \$pfad\): bool\n\{\n)/$1    return false;\n/' \
  "Sperrfunktion: .bak gesperrt" "Router: .bak → 404"
# M2 – die Endungsliste ohne .bak
mut M2 $S '\|bak\|' 's/\|bak\|/|/' \
  "Sperrfunktion: .bak gesperrt" "Router: .bak → 404"
# M3 – die Ausnahme .well-known entfernt
mut M3 $S '\(\?!well-known\(\/\|\$\)\)' 's/\(\?!well-known\(\/\|\$\)\)//' \
  "Punktregel wie Vorlage: /.well-known/probe.css frei" "Router: /.well-known/probe.css → 200"
# M4 – der Aufruf entfernt (der ganze Block)
mut M4 $RT '(?s)if \(sp_pfad_gesperrt\(\$uri\)\) \{\n.*?\n\}\n' \
  's/if \(sp_pfad_gesperrt\(\$uri\)\) \{\n.*?\n\}\n//s' \
  "dev-router.php ruft sp_pfad_gesperrt() genau einmal" "Router: .bak → 404"
# M5 – der Aufruf entwertet, nicht entfernt: Nur die Wirkung darf anschlagen,
#      die Quelltextprüfung bleibt grün – belegt, dass C mehr misst als E.
mut M5 $RT 'if \(sp_pfad_gesperrt\(\$uri\)\) \{' \
  's/if \(sp_pfad_gesperrt\(\$uri\)\) \{/if (false \&\& sp_pfad_gesperrt(\$uri)) {/' \
  "Router: .bak → 404"
# M6 – Code gelöscht, Kommentar bleibt: Die Quelltextprüfung darf ihn nicht zählen.
mut M6 $RT 'if \(sp_pfad_gesperrt\(\$uri\)\) \{' \
  's/if \(sp_pfad_gesperrt\(\$uri\)\) \{/if (false \/* sp_pfad_gesperrt(\$uri) *\/) {/' \
  "dev-router.php ruft sp_pfad_gesperrt() genau einmal" "Router: .bak → 404"
# M7 – die Sperre hinter die Dateiauslieferung (file_exists) verschoben
mut M7 $RT '\/\/ Sperrliste: ' \
  's/(\/\/ Sperrliste: .*?\n\}\n\n)(\/\/ Statische Dateien.*?\n\}\n\n)/$2$1/s' \
  "dev-router.php: die Sperre steht nach dem API-Zweig und vor dem ersten file_exists()" "Router: .bak → 404"
# M8 – der API-Zweig hinter die Sperre verschoben (Entscheidung 2, Lauf 2):
#      ein Routenwert mit gesperrter Endung würde 404.
mut M8 $RT '\/\/ API-Requests an Router' \
  's/(\/\/ API-Requests an Router.*?\n\}\n\n)(\/\/ Sperrliste: .*?\n\}\n\n)/$2$1/s' \
  "Router: /api/rollen/m.old → API-Router, nicht die Sperre" "dev-router.php: die Sperre steht nach dem API-Zweig"
# K1 – die KOPIE bekommt eine Endung, die die Funktion nicht kennt:
#      belegt, dass die Prüfung ihre Endungen aus der Kopie liest.
mut K1 $K '\|sh\)\|~\)' 's/\|sh\)\|~\)/|sh|xyz)|~)/' \
  "Sperrfunktion: .xyz gesperrt" "Router: .xyz → 404"

# E1 – die Einbindung: Ist die Suite rot, muss tests-schulprozesse.sh rot sein,
#      an der erwarteten Zeile.
GELAUFEN=$((GELAUFEN + 1))
if einbauen E1 "$R/$S" '\|bak\|' 's/\|bak\|/|/'; then
  GESAMT_AUS=$(cd "$R" && ./tests-schulprozesse.sh 2>&1); GESAMT_EXIT=$?
  if [ "$GESAMT_EXIT" -eq 0 ]; then
    echo "E1: NICHT ANGESCHLAGEN – tests-schulprozesse.sh übersieht die rote Suite"; FEHLT=$((FEHLT + 1))
  elif printf '%s\n' "$GESAMT_AUS" | grep -qF "✗ Sperrliste: Sperrfunktion: .bak gesperrt"; then
    echo "E1: angeschlagen (tests-schulprozesse.sh rot, an der erwarteten Zeile)"
  else
    echo "E1: NICHT AN DER ERWARTETEN ZEILE"; FEHLT=$((FEHLT + 1))
  fi
else
  FEHLT=$((FEHLT + 1))
fi
ruecknahme E1 "$R/$S"

# H1 – harmlos: ein Kommentar ändert sich. Die Suite muss grün bleiben.
GELAUFEN=$((GELAUFEN + 1))
if einbauen H1 "$R/$S" ' \* Ist der angefragte Pfad gesperrt\?' 's/ \* Ist der angefragte Pfad gesperrt\?/ * Ist der angefragte Pfad gesperrt? (harmlos geändert)/'; then
  if php "$R/tests/sperrliste_test.php" > /dev/null 2>&1; then
    echo "H1: richtig nicht angeschlagen (harmlose Änderung, Suite grün)"
  else
    echo "H1: FALSCH ANGESCHLAGEN"; FEHLT=$((FEHLT + 1))
  fi
else
  FEHLT=$((FEHLT + 1))
fi
ruecknahme H1 "$R/$S"

# Rücknahme der ganzen Serie: jede Datei gleich ihrem Stand vor der Serie.
for d in "$S" "$RT" "$K"; do
  cmp -s "$R/$d" "$SICH/soll-$(basename "$d")" || { echo "ABBRUCH: $d weicht nach der Serie vom Stand davor ab"; exit 2; }
done
echo "Rücknahme belegt: $S, $RT, $K gleich dem Stand vor der Serie"

echo ""
[ "$GELAUFEN" -ge 11 ] || { echo "FEHLER: nur $GELAUFEN Mutationen gelaufen, erwartet 11"; exit 1; }
if [ "$FEHLT" -eq 0 ]; then echo "ALLE $GELAUFEN MUTATIONEN BELEGT"; exit 0; fi
echo "$FEHLT VON $GELAUFEN MUTATION(EN) OHNE BELEG"; exit 1
