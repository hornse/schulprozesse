# Installation

---

## Teil 1: Lokales Projekt in IntelliJ und GitHub anlegen

### Schritt 1 – ZIP entpacken

Ordner `schulprozesse/` in ein sinnvolles Verzeichnis entpacken,
z. B. `~/Projekte/schulprozesse/`.

### Schritt 2 – IntelliJ öffnen

„Open" → entpackten Ordner auswählen.

### Schritt 3 – Git initialisieren

```bash
git init
git add -A
git commit -m "Initial commit: Schulprozesse"
```

### Schritt 4 – GitHub-Repository anlegen

1. github.com → „New repository" → Name: `schulprozesse`
2. Kein README/gitignore/Lizenz hinzufügen
3. Remote-URL notieren

### Schritt 5 – Remote und Push

```bash
git remote add github https://github.com/hornse/schulprozesse.git
git push -u github main
```

---

## Teil 2: Uberspace einrichten

### Schritt 6 – Bare repo

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

### Schritt 7 – Zweiten Remote lokal

```bash
git remote add uberspace hornse@halimede.uberspace.de:repos/schulprozesse.git
```

Deployen ab jetzt:
```bash
git push github main && git push uberspace main
```

### Schritt 8 – Domain und Symlink

```bash
uberspace web domain add prozesse.hornse.de
cd /var/www/virtual/hornse
ln -s schulprozesse-src/backend/public prozesse.hornse.de
```

### Schritt 9 – Konfiguration

```bash
cd /var/www/virtual/hornse/schulprozesse-src
cp config/config.example.php config/config.php
nano config/config.php
```

Mindestens setzen:
```php
'webuntis' => [
    'base_url' => 'https://SERVERNAME.webuntis.com',
    'school'   => 'SCHULKENNUNG',
    'allowed_person_types' => [2, 16],
],
```

### Schritt 10 – Datenbank anlegen

```bash
cd /var/www/virtual/hornse/schulprozesse-src
mkdir -p data
sqlite3 data/app.sqlite < migrations/001_init.sql
sqlite3 data/app.sqlite < migrations/002_seed_schritte.sql
sqlite3 data/app.sqlite < migrations/003_einstellungen.sql
sqlite3 data/app.sqlite < migrations/004_instanz_anpassungen.sql
```

**Wichtig:** Alle vier Migrationen müssen in dieser Reihenfolge eingespielt werden.
Migration 003 legt die `einstellungen`-Tabelle an – ohne sie schlägt der
Erscheinungsbild-Bereich komplett fehl.

### Schritt 11 – PHP Backend-Server einrichten (Uberspace)

Auf Uberspace läuft die App über einen PHP built-in server statt direkt über Apache.
Das ist nötig damit Rewrite-Rules korrekt funktionieren:

```bash
# Supervisord-Config anlegen
mkdir -p ~/etc/services.d
cat > ~/etc/services.d/schulprozesse.ini << 'EOF'
[program:schulprozesse]
command=php -S 0.0.0.0:8083 /var/www/virtual/hornse/schulprozesse-src/backend/public/dev-router.php
directory=/var/www/virtual/hornse/schulprozesse-src/backend/public
autostart=yes
autorestart=yes
EOF

# Service starten
supervisorctl reread
supervisorctl update
supervisorctl status schulprozesse

# Backend bei Uberspace registrieren
uberspace web backend set prozesse.hornse.de/ --http --port 8083
```

### Schritt 11 – Ersten Admin eintragen

```bash
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle)
   VALUES ('DEIN_KUERZEL', 'Dein Name', 'admin');"

sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO prozess_teilnehmer (prozess_id, webuntis_user, rolle)
   VALUES (1, 'DEIN_KUERZEL', 'verantwortlich');"
```

---

## Optionale Vorlagen

```bash
sqlite3 data/app.sqlite < migrations/seed_vorlagen_snapshots.sql
```

6 fertige Snapshots: Schuljahresbeginn, Schuljahresabschluss,
Abitur-Organisation, iPad-/Geräteausgabe, DSGVO-Jahresprüfung,
Studientag-Organisation.

---

## Notfall-Passwort (optional)

```bash
php -r "echo password_hash('PASSWORT', PASSWORD_BCRYPT) . PHP_EOL;"

sqlite3 data/app.sqlite \
  "INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle, passwort_hash)
   VALUES ('notfalladmin', 'Notfall Admin', 'admin', 'HASH');"
```

---

## Laufender Betrieb

```bash
git add -A && git commit -m "Beschreibung"
git push github main && git push uberspace main
```

---

## Migrationen

| Datei | Inhalt |
|---|---|
| `001_init.sql` | Vollständiges Schema |
| `002_seed_schritte.sql` | 13 WebUntis-Schritte + erster Prozess |
| `003_einstellungen.sql` | Erscheinungsbild-Einstellungen |
| `004_instanz_anpassungen.sql` | Instanz-Anpassungen (Phasen, eigene Schritte) |
| `seed_vorlagen_snapshots.sql` | 6 fertige Prozess-Vorlagen als Snapshots |
