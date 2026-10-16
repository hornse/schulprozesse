#!/bin/bash
# ============================================================
# tests/mutationen_anmeldebremse.sh – prüft die Prüfungen der Anmeldebremse
#
# Baut nacheinander bewusste Fehler in die Bremse ein und belegt je Fehler,
# dass tests/anmeldebremse_test.php rot wird – an der erwarteten Zeile.
# Zum Schluss: Wird die Suite rot, muss auch tests-schulprozesse.sh rot
# werden (die Einbindung, nicht nur die Suite).
#
# Je Mutation: Prüfsumme vorher → Sicherung → Eingriff → Nachweis, dass er
# angekommen ist (Prüfsumme anders, php -l heil) → Suite → Rücknahme aus der
# Sicherung (nie aus dem Index) → Prüfsumme gleich vorher.
#
# Wird nicht von tests-schulprozesse.sh aufgerufen – sie verändert
# vorübergehend den Code. Aufruf im Projektordner:
#   ./tests/mutationen_anmeldebremse.sh
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

# In der Hauptschale aufrufen, nie in $(…): VORHER und OFFEN gingen sonst
# verloren, und die Rücknahme hätte keinen Sollstand (so beim ersten Lauf).
einbauen() {      # NAME DATEI AUSDRUCK → 0 wenn angekommen und heil
  VORHER=$(summe "$2"); cp "$2" "$SICH/sicherung"; OFFEN="$2"
  perl -0777 -i -pe "$3" "$2"
  if [ "$(summe "$2")" = "$VORHER" ]; then echo "$1: MUTATION NICHT ANGEKOMMEN – Suchmuster prüfen"; return 1; fi
  php -l "$2" > /dev/null 2>&1 || { echo "$1: MUTATION KAPUTT – Syntaxfehler nach dem Einbau"; return 1; }
}
ruecknahme() {    # NAME DATEI
  cp "$SICH/sicherung" "$2"; OFFEN=""
  [ "$(summe "$2")" = "$VORHER" ] || { echo "ABBRUCH: Rücknahme von $1 misslungen"; exit 2; }
}

# mut NAME DATEI PERL-AUSDRUCK ERWARTETE-ZEILE
mut() {
  local name=$1 datei="$R/$2" ausdruck=$3 erwartet=$4 aus e treffer
  GELAUFEN=$((GELAUFEN + 1))
  if ! einbauen "$name" "$datei" "$ausdruck"; then
    FEHLT=$((FEHLT + 1))
  else
    aus=$(php "$R/tests/anmeldebremse_test.php" 2>/dev/null); e=$?
    treffer=$(printf '%s\n' "$aus" | grep -cF "✗ $erwartet" || true)
    if [ "$e" -ne 0 ] && [ "$treffer" -ge 1 ]; then echo "$name: angeschlagen („$erwartet“)"
    else echo "$name: NICHT ANGESCHLAGEN (exit=$e, „$erwartet“ rot: $treffer)"; FEHLT=$((FEHLT + 1)); fi
  fi
  ruecknahme "$name" "$datei"
}

php "$R/tests/anmeldebremse_test.php" > /dev/null 2>&1 || { echo "ABBRUCH: Suite schon vor der Serie rot"; exit 2; }

B=backend/src/Auth/Anmeldebremse.php
A=backend/api/auth.php
mut S1 $B 's/\$seit < self::WARTEN_SEKUNDEN\)/\$seit < 0)/' "an der Schwelle (5), gerade eben"
mut S2 $B 's/\$seit < self::WARTEN_SEKUNDEN\)/\$seit < 900)/' "der letzte von 5 vor 60 s: durch"
mut S3 $B 's/\$seit < self::WARTEN_SEKUNDEN\)/\$seit <= self::WARTEN_SEKUNDEN)/' "auf der Grenze"
mut S4 $B 's/        if \(\$lage === null\) \{/        if (false) {/' "Zählung gescheitert"
mut S5 $B 's/        \} catch \(Exception \$e\) \{\n            \/\/ Der Grund ins Server-Log/        } catch (\\DomainException \$e) {\n            \/\/ Der Grund ins Server-Log/' "ohne Tabelle"
mut S6 $B 's/                \$db->prepare\(\x27DELETE FROM login_log WHERE id = \?\x27\)->execute\(\[\$id\]\);/                null;/' \
  "der abgewiesene Versuch zählt nicht"
mut S7 $B 's/VALUES \(\?, 0, \?, \?\)\x27\)/VALUES (?, 2, ?, ?)\x27)/' "gleichzeitig"
mut S8 $B 's/            \$zurueck = \(int\) \$st->fetchColumn\(\);/            \$zurueck = 0;/' "Rücksetzen: 5 Fehlversuche"
mut S9 $B 's/AND id > \? AND zeitpunkt >= \?/AND zeitpunkt > (SELECT MAX(z2.zeitpunkt) FROM login_log z2 WHERE z2.id = ?) AND zeitpunkt >= ?/' \
  "nach der Nummer"
mut S10 $B 's/WHERE webuntis_user = \? AND erfolgreich = 1\x27/WHERE (webuntis_user = ? OR 1 = 1) AND erfolgreich = 1\x27/' \
  "je Benutzer: die Anmeldung eines anderen"
mut S11 $B 's/WHERE webuntis_user = \? AND erfolgreich = 0 AND/WHERE (webuntis_user = ? OR 1 = 1) AND erfolgreich = 0 AND/' \
  "je Benutzer: 6 fremde"
mut S12 $B 's/max\(1, \$fensterMinuten\)/(100 * max(1, \$fensterMinuten))/' "Fenster: 4 Fehlversuche vor 17 Minuten"
mut S13 $B 's/        \$db->prepare\(\x27DELETE FROM login_log WHERE id = \? AND grund = \?\x27\)->execute\(\[\$id, self::LAEUFT\]\);/        return;/' \
  "ohne Entscheid: der laufende Eintrag verschwindet"
mut S14 backend/src/Auth/WebUntisAuth.php 's/\$this->grund = \x27ohne_entscheid\x27;/\$this->grund = \x27falsches_passwort\x27;/' \
  "WebUntisAuth: WebUntis antwortet nicht"
mut S15 $A 's/        \$lokalFalsch = true;/        \$lokalFalsch = false;/' "Notfallpasswort falsch, WebUntis tot"
mut S16 $A 's/    \$versuch = bremseBeginnen\(\$db, \$config, trim\(\$username\), \$ip\);/    \$versuch = 0;/' \
  "fünf falsche Notfallpasswörter"
mut S17 $A 's/\} elseif \(\$auth->grund\(\) === \x27ohne_entscheid\x27\) \{/} elseif (false) {/' \
  "ohne Notfallpasswort, WebUntis tot"
mut S18 $B 's/\x27Zu viele Fehlversuche\. Erneut versuchen in noch \x27 \. \$rest/\x27Zu viele Fehlversuche. Bitte später.\x27 . \x27\x27/' \
  "fünf Fehlversuche im Abstand von 15 s"
mut S19 $A 's/            Anmeldebremse::abschliessen\(\$db, \$versuch, true, \x27lokal\x27\);/            null;/' \
  "Notfallpasswort richtig"

# Die Einbindung: Ist die Suite rot, muss tests-schulprozesse.sh rot sein.
GELAUFEN=$((GELAUFEN + 1))
if einbauen E1 "$R/$B" 's/\$seit < self::WARTEN_SEKUNDEN\)/\$seit < 0)/'; then
  if (cd "$R" && ./tests-schulprozesse.sh 2>&1) | grep -qF "✗ Anmeldebremse: Suite rot"; then
    echo "E1: angeschlagen (tests-schulprozesse.sh meldet die rote Suite)"
  else
    echo "E1: NICHT ANGESCHLAGEN – tests-schulprozesse.sh übersieht die rote Suite"; FEHLT=$((FEHLT + 1))
  fi
else
  FEHLT=$((FEHLT + 1))
fi
ruecknahme E1 "$R/$B"

echo ""
[ "$GELAUFEN" -gt 0 ] || { echo "FEHLER: keine Mutation gelaufen"; exit 1; }
if [ "$FEHLT" -eq 0 ]; then echo "ALLE $GELAUFEN MUTATIONEN ANGESCHLAGEN"; exit 0; fi
echo "$FEHLT VON $GELAUFEN MUTATION(EN) OHNE BELEG"; exit 1
