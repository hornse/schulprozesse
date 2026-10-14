#!/bin/bash
# deploy.sh – Schulprozesse deployen
# Aktualisiert Cache-Busting-Timestamp und pusht auf GitHub + Uberspace.
#
# Verwendung:
#   ./deploy.sh "Commit-Nachricht"
#   ./deploy.sh  (ohne Nachricht: interaktiv fragen)

set -e
# Der Halt unten sieht mit "git ls-files -o" nur unterhalb des aktuellen
# Verzeichnisses - deshalb zuerst an die Wurzel.
cd "$(dirname "$0")"

# AUSGELIEFERT WIRD, WAS COMMITTET IST (REIHENREGELN 6). Bis zum 14.10.2026
# stand hier "git add -A": Eine Datei, die unverfolgt im Baum lag, waere
# ungesehen committet, nach GitHub gepusht und ausgeliefert worden. Deshalb
# ZUERST anhalten und nennen, was dazukaeme - vor Commit und Push. Wer es
# sieht, entscheidet: namentlich committen, verschieben oder in .gitignore.
# -z: Namen mit Umlauten erscheinen, wie sie heissen, nicht als Oktalfolge.
UNGETRACKT=$(git ls-files -o --exclude-standard -z | tr '\0' '\n')
GEAENDERT=$(git status --porcelain -z --untracked-files=no | tr '\0' '\n')
if [ -n "$UNGETRACKT" ] || [ -n "$GEAENDERT" ]; then
  echo "!! Ausgeliefert wird, was committet ist. Vorher entscheiden:" >&2
  if [ -n "$UNGETRACKT" ]; then
    echo "   UNVERFOLGT – namentlich committen, verschieben oder in .gitignore:" >&2
    printf '%s\n' "$UNGETRACKT" | sed 's/^/     /' >&2
  fi
  if [ -n "$GEAENDERT" ]; then
    echo "   GEAENDERT oder gemerkt, nicht committet – namentlich committen oder zuruecknehmen:" >&2
    printf '%s\n' "$GEAENDERT" | sed 's/^/     /' >&2
  fi
  echo "Kein Deploy." >&2
  exit 1
fi
echo "== Vor der Auslieferung: nichts Unverfolgtes, nichts ungeprueft Geaendertes =="

MSG="${1}"
if [ -z "$MSG" ]; then
  read -p "Commit-Nachricht: " MSG
fi
if [ -z "$MSG" ]; then
  echo "Fehler: Keine Commit-Nachricht angegeben." >&2
  exit 1
fi

# Cache-Busting-Timestamp aktualisieren
# Ausdruck über beliebige Zeichen, nicht nur Ziffern: "\?v=\d+" traf das
# "?v=DEV" im Auslieferungszustand nicht, das sed lief ins Leere, es gab
# nichts zu committen, und unter "set -e" brach der Commit-Schritt vor dem
# Push ab. Gefunden am 17.08.2026. Kein sed -i: BSD (macOS) und GNU
# erwarten dort Verschiedenes.
STEMPEL=$(date -u +%Y%m%d%H%M%S)
sed "s/?v=[^\"']*/?v=$STEMPEL/g" backend/public/index.html > backend/public/index.html.neu
mv backend/public/index.html.neu backend/public/index.html
echo "Cache-Busting: ?v=$STEMPEL"

# Committen und pushen. NUR, was dieses Skript selbst aendert: den
# ?v=-Stempel. Alles andere ist vorher namentlich committet - sonst haette
# der Halt oben angehalten.
git add backend/public/index.html
if ! git diff --cached --quiet; then
  git commit -m "$MSG"
else
  echo "  (nichts zu committen)"
fi
git push github main && git push uberspace main

echo ""
echo "✓ Deploy abgeschlossen ($(date '+%d.%m.%Y %H:%M'))"
