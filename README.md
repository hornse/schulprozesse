# Schulprozesse

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

Eine mehrbenutzer­fähige Web-App zur Koordination wiederkehrender schulischer Prozesse
mit Checklisten, Zeitstrahl, Teilnehmerverwaltung und schulspezifischem Erscheinungsbild.

Entwickelt von einem Lehrer und IT-Verantwortlichen am Friedrich-Rückert-Gymnasium Düsseldorf,
mit Unterstützung von [Claude](https://claude.ai) (Anthropic).

---

## Was die App kann

- **Mehrere Prozesse gleichzeitig** – WebUntis-Wechsel, Abitur, Geräteausgabe etc.
  laufen unabhängig mit eigener Checkliste, eigenen Teilnehmern und eigenen Terminen
- **Öffentliches Dashboard** – öffentliche Prozesse ohne Login sichtbar
- **Checkliste** – Schritte abhaken, Verantwortliche und Termine eintragen,
  Kommentare und Markdown-Infos
- **Zeitstrahl** – Gantt (HTML-Tabelle, perfekte Ausrichtung) und Timeline,
  Zoom-Schieberegler, SVG-Export
- **Hilfe-Seite** – Erste Schritte und FAQ, ohne Login zugänglich, Schulname
  dynamisch eingesetzt
- **Erscheinungsbild** – Schulname, App-Titel, Farben und Logo über den
  Admin-Bereich konfigurierbar; Vorschau vor Aktivierung für alle
- **Export** – Checkliste als CSV, Zeitstrahl als SVG, beides als PDF druckbar
- **Vorlagen-Snapshots** – 6 vorgefertigte Prozess-Vorlagen enthalten
- **Lokales Notfall-Passwort** – Login unabhängig von WebUntis

---

## Berechtigungsmodell

| Aktion | Admin | Verantwortlicher | Mitarbeitender |
|---|---|---|---|
| Prozess anlegen | ✓ | – | – |
| Erscheinungsbild konfigurieren | ✓ | – | – |
| Öffentlich/privat schalten | ✓ | ✓ (eigener) | – |
| Teilnehmer verwalten | ✓ | ✓ (eigener) | – |
| Phasen/Schritte verwalten | ✓ | ✓ (eigener) | – |
| Häkchen, Daten, Kommentare | ✓ | ✓ | ✓ |

---

## Navigation

Die App hat eine zweizeilige sticky Navigation:

- **Zeile 1** – Schullogo + Schulname links, Benutzer + Abmelden rechts
- **Zeile 2** – Dashboard · Checkliste · Zeitstrahl · Prozess verwalten · Admin · ? Hilfe
- **Prozess-Tabs** – direkt darunter, wechselt alle Ansichten gleichzeitig

---

## Technischer Überblick

| Schicht | Technologie |
|---|---|
| Frontend | Vanilla JS/HTML/CSS, kein Build-Schritt |
| Backend | PHP ohne Framework, eigener Router |
| Datenbank | SQLite |
| Authentifizierung | WebUntis JSON-RPC + optionales lokales bcrypt-Passwort |
| Hosting | Uberspace 7 (empfohlen) |

Bewusst ohne externe Abhängigkeiten – kein npm, kein Composer, kein CDN.

---

## Sicherheitsmaßnahmen

- CSRF-Schutz über `X-Requested-With`-Header
- Alle SQL-Abfragen als Prepared Statements
- Farben serverseitig gegen `#RRGGBB` validiert (CSS-Injection-Schutz)
- Logo: MIME-Prüfung via `finfo`, SVG-Bereinigung, zufälliger Dateiname,
  Speicherung außerhalb des Webroots
- Session-Hardening (HttpOnly, SameSite, Secure)
- Eingaben werden sanitiert und auf Maximallänge begrenzt

---

## Verzeichnisstruktur

```
config/               Konfigurationsvorlage (config.php wird nie eingecheckt)
data/                 SQLite-Datenbankdatei + Logos (außerhalb des Webroots)
migrations/           SQL-Skripte zum einmaligen Einspielen
backend/
  bootstrap.php       Autoloading, Konfiguration, DB, Session, CSRF-Schutz
  src/                PHP-Klassen (App\...)
  api/                Endpunkt-Handler je Themenbereich
  public/             Dokumentenwurzel: index.html, CSS, JS, api-router.php
docs/
  INSTALL.md          Einrichtung Schritt für Schritt
  BENUTZERHANDBUCH.md Bedienungsanleitung für alle Nutzergruppen
CHANGELOG.md          Versionshistorie
LICENSE               GNU General Public License v3.0
```

---

## Schnellstart (lokal)

```bash
cp config/config.example.php config/config.php
# config.php bearbeiten

sqlite3 data/app.sqlite < migrations/001_init.sql
sqlite3 data/app.sqlite < migrations/002_seed_schritte.sql
sqlite3 data/app.sqlite < migrations/003_einstellungen.sql

php -S localhost:8000 -t backend/public dev-router.php
```

---

## Optionale Prozess-Vorlagen

```bash
sqlite3 data/app.sqlite < migrations/seed_vorlagen_snapshots.sql
```

Enthält: Schuljahresbeginn, Schuljahresabschluss, Abitur-Organisation,
iPad- und Geräteausgabe, DSGVO-Jahresprüfung, Studientag-Organisation.

---

## Lizenz

Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf

GNU General Public License v3.0 – Details siehe [LICENSE](LICENSE).

---

Entwickelt mit Unterstützung von [Claude](https://claude.ai) (Anthropic).
