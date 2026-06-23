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
  Geräteausgabe) läuft unabhängig mit eigener Checkliste und eigenen Teilnehmern
- **Öffentliches Dashboard** – öffentliche Prozesse sind ohne Login sichtbar,
  private Prozesse nur für zugewiesene Teilnehmer
- **Checkliste** – Schritte abhaken, Verantwortliche sowie Start- und Zieldatum eintragen,
  Kommentare und weiterführende Infos mit Markdown-Formatierung
- **Zeitstrahl** – Gantt- und Timeline-Ansicht, Zoom-Schieberegler (Tages- bis Wochenansicht),
  SVG-Export
- **Export** – Checkliste als CSV, Zeitstrahl als SVG-Vektorgrafik, beides als PDF druckbar
- **Aktivitätsprotokoll** – wer hat wann was erledigt, als Tabelle und CSV
- **Parallel-Erkennung** – Schritte mit überlappenden Zeiträumen automatisch markiert
- **Teilnehmerverwaltung** – Verantwortliche können selbst Mitarbeitende zuweisen;
  Admins verwalten alle Prozesse und Freigaben
- **Prozess-Sichtbarkeit** – öffentlich (🌐) oder privat (🔒), schaltbar durch Verantwortliche
- **Vorlagen-Snapshots** – aktuellen Stand einfrieren und als Basis für neue Prozesse nutzen
- **Lokales Notfall-Passwort** – optionaler Login unabhängig von WebUntis, per SQL gesetzt
- **Mobilansicht** – optimiertes Layout für kleine Bildschirme

---

## Berechtigungsmodell

| Aktion | Admin | Verantwortlicher | Mitarbeitender |
|---|---|---|---|
| Prozess anlegen/löschen | ✓ | – | – |
| Öffentlich/privat schalten | ✓ | ✓ (eigener) | – |
| Teilnehmer verwalten | ✓ | ✓ (eigener) | – |
| Phasen/Schritte verwalten | ✓ | ✓ (eigener) | – |
| Häkchen, Felder, Kommentare | ✓ | ✓ | ✓ |

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

Im `migrations/`-Verzeichnis liegen fertige Vorlagen die optional eingespielt werden können:

- `seed_schuljahresbeginn.sql` – Schuljahresbeginn allgemein
- `seed_schuljahresabschluss.sql` – Schuljahresabschluss
- `seed_abitur.sql` – Abitur-Organisation
- `seed_geraeteausgabe.sql` – iPad- und Geräteausgabe
- `seed_dsgvo.sql` – DSGVO-Jahresprüfung
- `seed_studientag.sql` – Studientag-Organisation

Nach dem Einspielen einer Vorlage in der App unter „Vorlagen-Snapshots → Jetzt einfrieren"
einen Snapshot erstellen und beim Anlegen eines neuen Prozesses als Basis wählen.

---

## Lizenz

Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf

Dieses Projekt steht unter der **GNU General Public License v3.0**.
Details siehe [LICENSE](LICENSE) oder
[gnu.org/licenses/gpl-3.0](https://www.gnu.org/licenses/gpl-3.0).

---

## Danksagung

Entwickelt mit Unterstützung von [Claude](https://claude.ai) (Anthropic).
