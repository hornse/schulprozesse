# Installation

---

## Teil 1: Lokales Projekt in IntelliJ und GitHub anlegen

### Schritt 1 – ZIP entpacken und Verzeichnis vorbereiten

Das heruntergeladene ZIP enthält einen Ordner `schulprozesse/`. Diesen in
ein sinnvolles Verzeichnis auf dem Rechner entpacken, z. B.:

```
~/Projekte/schulprozesse/
```

### Schritt 2 – IntelliJ öffnen

1. IntelliJ IDEA starten
2. „Open" klicken und den entpackten Ordner `schulprozesse/` auswählen

### Schritt 3 – Git initialisieren

Im Terminal unten in IntelliJ:

```bash
git init
git add -A
git commit -m "Initial commit: Schulprozesse"
```

### Schritt 4 – GitHub-Repository anlegen

1. github.com → „New repository"
2. Name: `schulprozesse`, Sichtbarkeit wählen
3. Kein README, .gitignore oder Lizenz hinzufügen
4. „Create repository" klicken
5. Remote-URL notieren (z. B. `https://github.com/hornse/schulprozesse.git`)

### Schritt 5 – Remote hinzufügen und pushen

```bash
git remote add github https://github.com/hornse/schulprozesse.git
git push -u github main
```

---

## Teil 2: Uberspace einrichten (prozesse.hornse.de)

### Schritt 6 – Bare repo auf Uberspace anlegen

```bash
mkdir -p ~/repos/schulprozesse.git
cd ~/repos/schulprozesse.git
git init --bare

cat > hooks/post-receive << 'EOF'
#!/bin/bash
GIT_WORK_TREE=/var/www/virtual/hornse/schulprozesse-src \
GIT_DIR=/home/hornse/repos/schulprozesse.git \
git checkout -f main
EOF
chmod +x hooks/post-receive
```

### Schritt 7 – Zweiten Remote lokal hinzufügen

```bash
git remote add uberspace hornse@halimede.uberspace.de:repos/schulprozesse.git
```

Deployen ab jetzt mit:
```bash
git push github main && git push uberspace main
```

### Schritt 8 – Domain und Symlink

```bash
uberspace web domain add prozesse.hornse.de

cd /var/www/virtual/hornse
ln -s schulprozesse-src/backend/public prozesse.hornse.de
```

Der Symlink muss Geschwister von `html/` sein, nicht darin.

### Schritt 9 – Konfiguration anlegen

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
    'allowed_person_types' => [2, 16],  // 2 = Lehrkraft, 16 = WebUntis-Admin
],
```

### Schritt 10 – Datenbank anlegen

```bash
cd /var/www/virtual/hornse/schulprozesse-src
mkdir -p data
sqlite3 data/app.sqlite < migrations/001_init.sql
sqlite3 data/app.sqlite < migrations/002_seed_schritte.sql
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

Dann `https://prozesse.hornse.de` aufrufen und einloggen.

---

## Optionale Prozess-Vorlagen einspielen

```bash
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  < /var/www/virtual/hornse/schulprozesse-src/migrations/seed_vorlagen_snapshots.sql
```

Legt 6 fertige Snapshots an, die sofort im Dropdown „Basis wählen" erscheinen.
Die aktive Vorlagentabelle wird dabei nicht verändert.

---

## Lokales Notfall-Passwort (optional)

Unabhängiger lokaler Benutzer der auch ohne WebUntis funktioniert:

```bash
# Hash generieren
php -r "echo password_hash('SICHERES_PASSWORT', PASSWORD_BCRYPT) . PHP_EOL;"

# Lokalen Benutzer anlegen
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle, passwort_hash)
   VALUES ('notfalladmin', 'Notfall Admin', 'admin', 'HASH_VON_OBEN');"

sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO prozess_teilnehmer (prozess_id, webuntis_user, rolle)
   VALUES (1, 'notfalladmin', 'verantwortlich');"
```

---

## Laufender Betrieb

```bash
git add -A
git commit -m "Beschreibung"
git push github main && git push uberspace main
```

Kein Migrationsschritt nötig – das Schema ist vollständig in `001_init.sql`.

---

## Migrationen im Überblick

| Datei | Inhalt |
|---|---|
| `001_init.sql` | Vollständiges Schema |
| `002_seed_schritte.sql` | 13 WebUntis-Schritte + erster Prozess |
| `seed_vorlagen_snapshots.sql` | 6 fertige Prozess-Vorlagen als Snapshots |
