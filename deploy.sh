#!/bin/bash
# deploy.sh – Schulprozesse deployen
# Aktualisiert Cache-Busting-Timestamp und pusht auf GitHub + Uberspace.
#
# Verwendung:
#   ./deploy.sh "Commit-Nachricht"
#   ./deploy.sh  (ohne Nachricht: interaktiv fragen)

set -e

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

# Committen und pushen
git add -A
if ! git diff --cached --quiet; then
  git commit -m "$MSG"
else
  echo "  (nichts zu committen)"
fi
git push github main && git push uberspace main

echo ""
echo "✓ Deploy abgeschlossen ($(date '+%d.%m.%Y %H:%M'))"
