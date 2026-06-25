# Schulprozesse

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

Eine mehrbenutzer­fähige Web-App zur Koordination wiederkehrender schulischer
Prozesse mit Checklisten, Zeitstrahl, Teilnehmerverwaltung und vollständiger
prozessspezifischer Anpassbarkeit.

Entwickelt von einem Lehrer und IT-Verantwortlichen am Friedrich-Rückert-Gymnasium
Düsseldorf, mit Unterstützung von [Claude](https://claude.ai) (Anthropic).

---

## Kernkonzept: Vorlage → Prozess-Instanz

Analog zur OOP:

| OOP | App |
|---|---|
| Klasse | Vorlage (z. B. WebUntis-Wechsel) |
| Objekt/Instanz | Prozess (z. B. WebUntis Schuljahreswechsel 2026/2027) |
| Klassen-Attribut | Phase der Vorlage (z. B. „Vorbereitung in Untis") |
| Instanz-Attribut | Phase des Prozesses – kann abweichen ohne die Vorlage zu berühren |
| Klassen-Methode | Schritt der Vorlage |
| Instanz-Methode | Schritt des Prozesses – kann umbenannt/deaktiviert werden |

---

## Was die App kann

**Prozesse und Checklisten:**
- Mehrere Prozesse gleichzeitig, jeder mit eigener Checkliste und eigenen Teilnehmern
- Öffentliche Prozesse ohne Login sichtbar, private nur für Teilnehmer
- Schritte abhaken, Verantwortliche und Termine eintragen, Kommentare

**Prozess-Instanz-Anpassungen (ohne Vorlage zu berühren):**
- Phasennamen und -farben für jeden Prozess individuell überschreiben
- Schritte umbenennen (Original bleibt als Hinweis sichtbar)
- Schritte deaktivieren und reaktivieren
- Eigene Phasen und Schritte hinzufügen die nur in diesem Prozess erscheinen

**Zeitstrahl:**
- Gantt (HTML-Tabelle, perfekte Ausrichtung) und Timeline
- Zoom-Schieberegler, SVG-Export

**Admin:**
- Erscheinungsbild: Schulname, Farben, Logo über die UI konfigurierbar
- Vorlagenverwaltung mit Snapshot-Auswahl und direkter Bearbeitung
- Zugriffsliste mit Prozess-Zugehörigkeits-Badges

**Hilfe:**
- Eingebaute Hilfe-Seite ohne Login zugänglich (Erste Schritte + FAQ)

---

## Berechtigungsmodell

| Aktion | Admin | Verantwortlicher | Mitarbeitender |
|---|---|---|---|
| Prozess anlegen | ✓ | – | – |
| Erscheinungsbild konfigurieren | ✓ | – | – |
| Vorlagen verwalten | ✓ | – | – |
| Öffentlich/privat schalten | ✓ | ✓ (eigener) | – |
| Teilnehmer verwalten | ✓ | ✓ (eigener) | – |
| Phasennamen/-farben anpassen | ✓ | ✓ (eigener) | – |
| Schritte umbenennen/deaktivieren | ✓ | ✓ (eigener) | – |
| Eigene Phasen/Schritte anlegen | ✓ | ✓ (eigener) | – |
| Häkchen, Daten, Kommentare | ✓ | ✓ | ✓ |

---

## Navigation

- **Zeile 1** – Schullogo + Name links, Benutzer + Abmelden rechts
- **Zeile 2** – Dashboard · Checkliste · Zeitstrahl · Prozess verwalten · Admin · Hilfe
- **Prozess-Tabs** – wechselt alle Ansichten gleichzeitig

---

## Technischer Überblick

| Schicht | Technologie |
|---|---|
| Frontend | Vanilla JS/HTML/CSS, kein Build-Schritt |
| Backend | PHP ohne Framework, eigener Router |
| Datenbank | SQLite |
| Authentifizierung | WebUntis JSON-RPC + optionales lokales bcrypt-Passwort |
| Hosting | Uberspace 7 (empfohlen) |

---

## Sicherheitsmaßnahmen

- CSRF-Schutz über `X-Requested-With`-Header
- Prepared Statements für alle SQL-Abfragen
- Farben gegen `#RRGGBB` validiert (CSS-Injection-Schutz)
- Logo: MIME-Prüfung via `finfo`, SVG-Bereinigung, zufälliger Dateiname,
  Speicherung außerhalb des Webroots
- Session-Hardening (HttpOnly, SameSite, Secure)

---

## Datenbankschema

```
phasen                  – globale Vorlage-Phasen
schritt_vorlagen        – globale Vorlage-Schritte
prozesse                – Prozess-Instanzen
prozess_teilnehmer      – wer darf welchen Prozess sehen/bearbeiten
schritt_instanzen       – Prozess-Schritte (Vorlage-basiert, mit Anpassungsfeldern)
instanz_phasen          – prozessspezifische Phasen-Überschreibungen (Name, Farbe)
instanz_schritte        – prozessspezifische eigene Schritte (ohne Vorlage)
vorlagen_sets           – Snapshots (eingefroren für neue Prozesse)
vorlagen_set_phasen     – Phasen eines Snapshots
vorlagen_set_schritte   – Schritte eines Snapshots
einstellungen           – Erscheinungsbild (Schulname, Farben, Logo)
benutzer_rollen         – App-Freigaben
aktivitaeten            – Aktivitätsprotokoll
```

---

## Schnellstart (lokal)

```bash
cp config/config.example.php config/config.php
sqlite3 data/app.sqlite < migrations/001_init.sql
sqlite3 data/app.sqlite < migrations/002_seed_schritte.sql
sqlite3 data/app.sqlite < migrations/003_einstellungen.sql
sqlite3 data/app.sqlite < migrations/004_instanz_anpassungen.sql
php -S localhost:8000 -t backend/public dev-router.php
```

---

## Lizenz

Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
GNU General Public License v3.0 – Details siehe [LICENSE](LICENSE).

Entwickelt mit Unterstützung von [Claude](https://claude.ai) (Anthropic).
