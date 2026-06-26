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

# Cache-Busting-Timestamp aktualisieren (YYYYMMDDHHMM)
TIMESTAMP=$(date +%Y%m%d%H%M)
python3 - << PYEOF
import re
with open('backend/public/index.html', 'r') as f:
    content = f.read()
content = re.sub(r'\?v=\d+', f'?v=${TIMESTAMP}', content)
with open('backend/public/index.html', 'w') as f:
    f.write(content)
print(f"Cache-Busting: ?v=${TIMESTAMP}")
PYEOF

# Committen und pushen
git add -A
git commit -m "$MSG"
git push github main && git push uberspace main

echo ""
echo "✓ Deploy abgeschlossen ($(date '+%d.%m.%Y %H:%M'))"
