# Uberspace-Konfiguration

Diese Datei dokumentiert alles was auf dem Uberspace-Server konfiguriert ist
aber **nicht** durch `git push` deployed wird. Bei einer Neuinstallation müssen
diese Schritte manuell ausgeführt werden.

Server: `halimede.uberspace.de`
Account: `hornse`
Domain: `prozesse.hornse.de`

---

## PHP Built-in Server (supervisord)

Die App läuft über einen PHP built-in server statt direkt über Apache.
Das ist nötig damit URL-Routing korrekt funktioniert.

**Config-Datei:** `~/etc/services.d/schulprozesse.ini`

```ini
[program:schulprozesse]
command=php -S 0.0.0.0:8083 /var/www/virtual/hornse/schulprozesse-src/backend/public/dev-router.php
directory=/var/www/virtual/hornse/schulprozesse-src/backend/public
autostart=yes
autorestart=yes
```

**Service starten:**
```bash
supervisorctl reread
supervisorctl update
supervisorctl status schulprozesse
```

**Service neu starten** (nach Deploy):
```bash
supervisorctl restart schulprozesse
```

---

## Web Backend

Uberspace leitet `prozesse.hornse.de` an Port 8083 weiter:

```bash
uberspace web backend set prozesse.hornse.de/ --http --port 8083
```

Prüfen:
```bash
uberspace web backend list | grep prozesse
```

---

## Domain-Symlink

```bash
ln -s schulprozesse-src/backend/public \
  /var/www/virtual/hornse/prozesse.hornse.de
```

---

## Git Bare Repository (post-receive Hook)

```bash
mkdir -p ~/repos/schulprozesse.git
cd ~/repos/schulprozesse.git && git init --bare

cat > hooks/post-receive << 'EOF'
#!/bin/bash
GIT_WORK_TREE=/var/www/virtual/hornse/schulprozesse-src \
GIT_DIR=/home/hornse/repos/schulprozesse.git \
git checkout -f main
EOF
chmod +x hooks/post-receive
```

Lokal als Remote hinzufügen:
```bash
git remote add uberspace hornse@halimede.uberspace.de:repos/schulprozesse.git
```

---

## Datenbank-Migrationen

Einmalig bei Erstinstallation – in dieser Reihenfolge:

```bash
DB=/var/www/virtual/hornse/schulprozesse-src/data/app.sqlite
SRC=/var/www/virtual/hornse/schulprozesse-src/migrations

sqlite3 $DB < $SRC/001_init.sql
sqlite3 $DB < $SRC/002_seed_schritte.sql
sqlite3 $DB < $SRC/003_einstellungen.sql        # Erscheinungsbild-Tabelle
sqlite3 $DB < $SRC/004_instanz_anpassungen.sql  # Instanz-Anpassungen
sqlite3 $DB < $SRC/seed_vorlagen_snapshots.sql  # Optional: 6 fertige Vorlagen
```

---

## Logs aktivieren (Debugging)

```bash
uberspace web log apache_error enable
uberspace web log php_error enable
uberspace web log access enable

# Logs lesen
tail -f ~/logs/webserver/error_log_apache
tail -f ~/logs/error_log_php
tail -f ~/logs/webserver/access_log

# Supervisord-Log
tail -f ~/logs/supervisord.log | grep schulprozesse
```

Logs deaktivieren wenn nicht mehr gebraucht:
```bash
uberspace web log apache_error disable
uberspace web log php_error disable
uberspace web log access disable
```

---

## Verzeichnisstruktur auf dem Server

```
/var/www/virtual/hornse/
├── prozesse.hornse.de -> schulprozesse-src/backend/public  (Symlink)
└── schulprozesse-src/
    ├── backend/
    │   ├── api/
    │   ├── public/          ← DocumentRoot
    │   │   ├── .htaccess    (Fallback für Apache)
    │   │   ├── dev-router.php (Router für PHP built-in server)
    │   │   ├── api-router.php
    │   │   ├── index.html
    │   │   ├── css/
    │   │   └── js/
    │   └── src/
    ├── config/
    │   └── config.php       (nicht in git – aus config.example.php erstellen)
    ├── data/
    │   ├── app.sqlite       (nicht in git)
    │   └── logos/           (nicht in git)
    └── migrations/

~/etc/services.d/
└── schulprozesse.ini        (nicht in git – siehe oben)

~/repos/
└── schulprozesse.git/       (bare repo für git push)
```

---

## Deployment-Workflow

```bash
# Lokal committen und auf beide Remotes pushen
git add -A
git commit -m "Beschreibung"
git push github main && git push uberspace main

# Server-Logs prüfen falls Probleme
supervisorctl status schulprozesse
```

Der post-receive Hook deployt automatisch nach `git push uberspace main`.
Ein `supervisorctl restart` ist normalerweise nicht nötig da PHP-Dateien
bei jedem Request neu geladen werden.
