# Installation

---

## Teil 1: Lokales Projekt in IntelliJ und GitHub anlegen

### Schritt 1 – ZIP entpacken und Verzeichnis vorbereiten

Das heruntergeladene ZIP enthält einen Ordner `schulprozesse/`. Diesen in
ein sinnvolles Verzeichnis auf deinem Rechner entpacken, z. B.:

```
~/Projekte/schulprozesse/
```

### Schritt 2 – IntelliJ öffnen

1. IntelliJ IDEA starten
2. „Open" klicken und den entpackten Ordner `schulprozesse/` auswählen
3. IntelliJ erkennt es als normales Verzeichnis (kein Framework nötig)

### Schritt 3 – Git initialisieren

Im Terminal unten in IntelliJ (oder in einem externen Terminal im Projektordner):

```bash
git init
git add -A
git commit -m "Initial commit: Schulprozesse"
```

### Schritt 4 – GitHub-Repository anlegen

1. github.com → „New repository"
2. Name: `schulprozesse`
3. Sichtbarkeit: Public oder Private (Public = andere Schulen können es nutzen)
4. **Kein** README, .gitignore oder Lizenz hinzufügen (ist schon im Projekt)
5. „Create repository" klicken
6. GitHub zeigt danach die Remote-URL, z. B.:
   `https://github.com/hornse/schulprozesse.git`

### Schritt 5 – Remote hinzufügen und pushen

```bash
git remote add github https://github.com/hornse/schulprozesse.git
git push -u github main
```

In IntelliJ: Git → Push (oder `Strg+Shift+K`) funktioniert danach auch.

---

## Teil 2: Uberspace einrichten (prozesse.hornse.de)

### Schritt 6 – Bare repo auf Uberspace anlegen

Per SSH auf dem Server:

```bash
mkdir -p ~/repos/schulprozesse.git
cd ~/repos/schulprozesse.git
git init --bare

cat > hooks/post-receive << 'EOF'
#!/bin/bash
GIT_WORK_TREE=/var/www/virtual/hornse/schulprozesse-src git checkout -f main
EOF
chmod +x hooks/post-receive
```

### Schritt 7 – Zweiten Remote lokal hinzufügen

```bash
git remote add uberspace hornse@halimede.uberspace.de:repos/schulprozesse.git
```

Ab jetzt deployen mit:
```bash
git push github main && git push uberspace main
```

### Schritt 8 – Domain und Symlink

```bash
uberspace web domain add prozesse.hornse.de

cd /var/www/virtual/hornse
ln -s schulprozesse-src/backend/public prozesse.hornse.de
```

Wichtig: der Symlink muss Geschwister von `html/` sein, nicht darin.

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
sqlite3 data/app.sqlite < migrations/001_init.sql
sqlite3 data/app.sqlite < migrations/002_seed_schritte.sql
```

### Schritt 11 – Ersten Admin eintragen

```bash
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle)
   VALUES ('DEIN_KUERZEL', 'Dein Name', 'admin');"

# Admin auch als Teilnehmer des ersten Prozesses eintragen
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO prozess_teilnehmer (prozess_id, webuntis_user, rolle)
   VALUES (1, 'DEIN_KUERZEL', 'verantwortlich');"
```

Danach auf `https://prozesse.hornse.de` einloggen – der Admin-Bereich
erscheint am Ende der Seite.

---

## Optionale Prozess-Vorlagen einspielen

Jede Vorlage legt neue Phasen und Schritte in der Vorlagentabelle an.
Nach dem Einspielen in der App unter „Vorlagen-Snapshots → Jetzt einfrieren"
einen Snapshot erstellen, dann beim Anlegen eines neuen Prozesses als Basis wählen.

```bash
# Beispiel: Abitur-Vorlage einspielen
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  < /var/www/virtual/hornse/schulprozesse-src/migrations/seed_abitur.sql
```

Verfügbare Vorlagen:

| Datei | Inhalt |
|---|---|
| `seed_schuljahresbeginn.sql` | Schuljahresbeginn allgemein (15 Schritte) |
| `seed_schuljahresabschluss.sql` | Schuljahresabschluss (14 Schritte) |
| `seed_abitur.sql` | Abitur-Organisation (17 Schritte) |
| `seed_geraeteausgabe.sql` | iPad- und Geräteausgabe (16 Schritte) |
| `seed_dsgvo.sql` | DSGVO-Jahresprüfung (15 Schritte) |
| `seed_studientag.sql` | Studientag-Organisation (14 Schritte) |

---

## Lokales Notfall-Passwort setzen (optional)

Unabhängiger lokaler Benutzer der auch ohne WebUntis funktioniert:

```bash
# Hash generieren
php -r "echo password_hash('SICHERES_PASSWORT', PASSWORD_BCRYPT) . PHP_EOL;"

# Lokalen Benutzer anlegen
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle, passwort_hash)
   VALUES ('notfalladmin', 'Notfall Admin', 'admin', 'HASH_VON_OBEN');"

# Auch als Teilnehmer aller gewünschten Prozesse eintragen
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "INSERT INTO prozess_teilnehmer (prozess_id, webuntis_user, rolle)
   VALUES (1, 'notfalladmin', 'verantwortlich');"
```

---

## Laufender Betrieb

```bash
# Änderungen deployen
git add -A
git commit -m "Beschreibung der Änderung"
git push github main && git push uberspace main
```

Keine Migrationen nötig – das Schema ist vollständig in `001_init.sql`.

---

## Migrationen im Überblick

| Datei | Inhalt |
|---|---|
| `001_init.sql` | Vollständiges Schema inkl. prozesse, prozess_teilnehmer, alle Tabellen |
| `002_seed_schritte.sql` | 13 Standard-Schritte für den WebUntis-Schuljahreswechsel + erster Prozess |
