# Schulprozesse

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

Eine mehrbenutzer­fähige Web-App zur Koordination wiederkehrender schulischer Prozesse
mit Checklisten, Zeitstrahl und Teilnehmerverwaltung.

Entwickelt von einem Lehrer und IT-Verantwortlichen am Friedrich-Rückert-Gymnasium Düsseldorf,
mit Unterstützung von [Claude](https://claude.ai) (Anthropic).

Bereitgestellt unter `prozesse.hornse.de`.

---

## Was die App kann

- **Mehrere Prozesse gleichzeitig** – jeder Prozess (z. B. WebUntis-Wechsel, Abitur,
  Geräteausgabe) läuft unabhängig mit eigener Checkliste und eigenen Teilnehmern;
  Wechsel per Tab-Leiste unter dem Header
- **Öffentliches Dashboard** – öffentliche Prozesse ohne Login sichtbar, private nur
  für zugewiesene Teilnehmer
- **Checkliste** – Schritte abhaken, Verantwortliche sowie Start- und Zieldatum eintragen,
  Kommentare und weiterführende Infos mit Markdown-Formatierung
- **Zeitstrahl** – Gantt- und Timeline-Ansicht, Zoom-Schieberegler (Tages- bis
  Wochenansicht), dynamisch ausgedünnte Datumsachse, SVG-Export
- **Export** – Checkliste als CSV, Zeitstrahl als SVG, beides als PDF druckbar
- **Aktivitätsprotokoll** – wer hat wann was erledigt, als Tabelle und CSV
- **Teilnehmerverwaltung** – Verantwortliche können selbst Mitarbeitende zuweisen und
  Phasen/Schritte ihres Prozesses verwalten
- **Prozess-Sichtbarkeit** – öffentlich (🌐) oder privat (🔒)
- **Vorlagen-Snapshots** – aktuellen Stand einfrieren und als Basis für neue Prozesse nutzen;
  6 vorgefertigte Vorlagen enthalten
- **Lokales Notfall-Passwort** – Login unabhängig von WebUntis, per SQL gesetzt

---

## Berechtigungsmodell

| Aktion | Admin | Verantwortlicher | Mitarbeitender |
|---|---|---|---|
| Prozess anlegen | ✓ | – | – |
| Öffentlich/privat schalten | ✓ | ✓ (eigener) | – |
| Teilnehmer verwalten | ✓ | ✓ (eigener) | – |
| Phasen/Schritte verwalten | ✓ | ✓ (eigener) | – |
| Häkchen, Daten, Kommentare | ✓ | ✓ | ✓ |

---

## Navigation

Die App hat eine zweizeilige sticky Navigation:

- **Zeile 1** – Schulname links, angemeldeter Benutzer + Abmelden rechts
- **Zeile 2** – Dashboard · Checkliste · Zeitstrahl · Prozess verwalten · Admin
- **Prozess-Tabs** – direkt darunter, wechselt Checkliste/Dashboard/Zeitstrahl

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

## Verzeichnisstruktur

```
config/               Konfigurationsvorlage (config.php wird nie eingecheckt)
data/                 SQLite-Datenbankdatei (außerhalb des Webroots)
migrations/           SQL-Skripte zum einmaligen Einspielen
backend/
  bootstrap.php       Autoloading, Konfiguration, DB, Session, CSRF-Schutz
  src/                PHP-Klassen (App\...)
  api/                Endpunkt-Handler je Themenbereich
  public/             Dokumentenwurzel: index.html, CSS, JS, api-router.php
docs/
  INSTALL.md          Einrichtung Schritt für Schritt
  BENUTZERHANDBUCH.md Bedienungsanleitung
CHANGELOG.md          Versionshistorie
LICENSE               GNU General Public License v3.0
```

---

## Schnellstart (lokal)

```bash
cp config/config.example.php config/config.php
# config.php bearbeiten: webuntis.base_url und webuntis.school setzen

sqlite3 data/app.sqlite < migrations/001_init.sql
sqlite3 data/app.sqlite < migrations/002_seed_schritte.sql

php -S localhost:8000 -t backend/public dev-router.php
```

Für Uberspace-Deployment und ersten Admin-Eintrag siehe `docs/INSTALL.md`.

---

## Optionale Prozess-Vorlagen

```bash
# Alle 6 Vorlagen als fertige Snapshots einspielen:
sqlite3 data/app.sqlite < migrations/seed_vorlagen_snapshots.sql
```

Enthaltene Vorlagen: Schuljahresbeginn, Schuljahresabschluss, Abitur-Organisation,
iPad- und Geräteausgabe, DSGVO-Jahresprüfung, Studientag-Organisation.

---

## Lizenz

Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf

Dieses Projekt steht unter der **GNU General Public License v3.0**.
Details siehe [LICENSE](LICENSE) oder
[gnu.org/licenses/gpl-3.0](https://www.gnu.org/licenses/gpl-3.0).

---

## Danksagung

Entwickelt mit Unterstützung von [Claude](https://claude.ai) (Anthropic).
