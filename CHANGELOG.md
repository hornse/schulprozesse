# Changelog

Alle wesentlichen Änderungen an diesem Projekt werden hier dokumentiert.
Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [Unreleased]

Geplant:
- Fortschrittsanzeige im Prozess-Tab (z. B. „Abitur (4/17)")
- Schritte duplizieren
- Archiv-Ansicht für abgeschlossene Prozesse
- E-Mail-Erinnerungen bei überfälligen Schritten
- Alles-zurücksetzen für Prozess-Instanz-Anpassungen

---

## [2.0.0] – 2026-06-25

### Neu: Prozess-Instanz-Anpassungen (Kernfeature)

Das wichtigste neue Konzept: Prozesse sind jetzt vollständige Instanzen
ihrer Vorlage (analog zu Objekt/Klasse in der OOP). Alle Anpassungen
betreffen nur den konkreten Prozess – Vorlage und andere Prozesse bleiben
unberührt.

- **Phasennamen und -farben prozessspezifisch** – unter „Prozess verwalten →
  Schritte anpassen" können Phasennamen und -farben für jeden Prozess
  individuell überschrieben werden; Zurücksetzen auf Vorlage jederzeit möglich
- **Schritte umbenennen** – jeder Schritt kann für diesen Prozess umbenannt
  werden; Original-Name bleibt als Hinweis sichtbar
- **Schritte deaktivieren** – nicht benötigte Schritte ausblenden und
  jederzeit reaktivieren ohne sie zu löschen
- **Eigene Phasen und Schritte** – neue Phasen mit eigener Farbe und
  beliebig vielen Schritten anlegen, die nur in diesem Prozess erscheinen
- **Neue Migrationen** `004_instanz_anpassungen.sql` mit Tabellen
  `instanz_phasen`, `instanz_schritte` (+ neue Felder in `schritt_instanzen`)

### Neu: Admin-Struktur überarbeitet

- **Admin-Tab** vollständig eigenständig – keine Prozess-Tabs mehr,
  kein Zusammenhang mit einzelnen Checklisten
- **Prozess verwalten** nur sichtbar wenn man für mindestens einen Prozess
  verantwortlich ist; Prozess-Tabs zeigen nur verantwortliche Prozesse
- **Aktivitätsprotokoll** zu „Prozess verwalten" verschoben statt im Admin
- **Hilfe-Tab** hat keine Prozess-Tabs mehr

### Neu: Vorlagenverwaltung mit Snapshot-Auswahl

- Vorlagenverwaltung im Admin hat Tab-Leiste: „Standard" + alle Snapshots
- Snapshots direkt editierbar: Phasen umbenennen/einfärben/löschen,
  Schritte hinzufügen/umbenennen/löschen
- Neue Prozesse aus editierten Snapshots übernehmen die aktuelle Version;
  bestehende Prozesse unberührt

### Neu: Zugriffsliste mit Prozess-Zugehörigkeit

- Spalte „Zugewiesen in" zeigt für jede Person farbige Badges welchen
  Prozessen sie zugewiesen ist (blau = verantwortlich, lila = mitarbeitend)

### Neu: Erscheinungsbild-Einstellungen

- Schulname, App-Titel, Primär-/Sekundärfarbe, Logo (PNG/JPG/SVG)
  über Admin-Bereich konfigurierbar
- Vorschau-Workflow: Änderungen erst nach „Für alle aktivieren" live
- Migration `003_einstellungen.sql`

### Neu: Hilfe-Seite

- Ohne Login zugänglich; zwei Tabs: Erste Schritte + FAQ (10 Fragen)
- Schulname aus Einstellungen dynamisch eingesetzt

### Behoben

- **Logo-Upload** – Umstellung auf Base64-JSON statt Multipart (Apache
  `LimitRequestBody` blockierte Multipart-Uploads)
- **Deployment auf Uberspace** – App läuft jetzt über PHP built-in server
  (supervisord, Port 8083) statt direkt über Apache; löst Rewrite-Probleme
- **Migration 003** (`einstellungen`-Tabelle) war in Installations-Doku
  nicht vollständig dokumentiert
- Globaler Exception-Handler in `api-router.php` – PHP-Fehler kommen
  immer als JSON zurück statt als Apache-HTML-Fehlerseite
- Prozess-Tabs unter „Prozess verwalten" – nur noch verantwortliche
  Prozesse sichtbar; falscher Prozess bei Mitgliedern behoben
- Prozess-Tabs wechselten Ansicht nicht (Query-Parameter-Bug im Router)
- `aktiv`-Flag aus Prozess-Logik entfernt; alle zugewiesenen Prozesse
  gleichzeitig nutzbar
- Gantt-Datumsachse überlappt nicht mehr (dynamische Label-Ausdünnung)
- Gantt als echte HTML-Tabelle für garantierte Spaltenausrichtung
- Doppelte Gantt/Timeline-Tabs beim Wechsel behoben
- Logo-Upload: finfo-Fallback, ob_start-Fix, Signatur-Korrektur

---

## [1.3.0] – 2026-06-25

### Hinzugefügt
- **Hilfe-Seite** – ohne Login zugänglich über den „?"-Tab in der Navigation;
  zwei Tabs: „Erste Schritte" (6 Karten mit geführter Einführung) und
  „FAQ" (10 aufklappbare Fragen und Antworten); Schulname aus den
  Einstellungen wird dynamisch eingesetzt
- **Hilfe-Tab** immer in der Navigationsleiste sichtbar, auch ohne Anmeldung

---

## [1.2.0] – 2026-06-25

### Hinzugefügt
- **Erscheinungsbild-Einstellungen** im Admin-Bereich: Schulname, App-Titel,
  Primär- und Sekundärfarbe, Logo-Upload (PNG/JPG/SVG, max. 500 KB)
- **Vorschau-Workflow**: Änderungen erst nach explizitem „Für alle aktivieren"
  live – Admin sieht Vorschau, alle anderen erst nach Aktivierung
- **Logo-Endpunkt** `/api/einstellungen/logo` liefert das Logo sicher über
  PHP aus (außerhalb Webroot, zufälliger Dateiname, keine direkte URL)
- **Neue Migration** `003_einstellungen.sql` mit Standardwerten
- **Sicherheit**: Farben gegen `#RRGGBB`-Muster validiert (CSS-Injection-Schutz),
  SVGs auf gefährliche Inhalte geprüft (Script, Event-Handler, iFrame etc.),
  MIME-Type serverseitig via `finfo` geprüft

### Geändert
- Header zeigt jetzt dynamisch Schulname und App-Titel aus den Einstellungen
- Logo erscheint links neben dem Schulnamen im Header

---

## [1.1.0] – 2026-06-24

### Hinzugefügt
- **Neue Navigation:** zweizeiliger sticky Header – Zeile 1 mit Schulname
  und Benutzer/Abmelden, Zeile 2 mit Hauptnavigation (Dashboard,
  Checkliste, Zeitstrahl, Prozess verwalten, Admin)
- **Prozess-Tabs** als eigene Leiste direkt unter dem Header, in allen
  Ansichten sichtbar
- **Admin und Prozess verwalten** als eigene Seiten/Tabs statt langer
  Blöcke am Seitenende – kein Scrollen durch nicht relevante Inhalte mehr
- **Verantwortliche** können jetzt auch Phasen und Schritte ihres Prozesses
  verwalten (Drag-and-Drop, neue Schritte anlegen, Vorlagen bearbeiten) –
  nicht mehr nur Admins
- Dashboard-Karten im Grid-Layout (nebeneinander auf breiten Bildschirmen)
- 6 vorgefertigte Prozess-Vorlagen direkt als Snapshots verfügbar
  (`seed_vorlagen_snapshots.sql`), ohne die aktive Vorlagentabelle zu
  berühren

### Behoben
- Prozess-Tabs wechselten die Ansicht nicht weil Query-Parameter
  (`prozess_id`) den API-Handlern nicht übergeben wurden (Router-Fix)
- `aktiv`-Flag verhinderte dass mehrere Prozesse gleichzeitig nutzbar
  waren; Flag aus der Logik entfernt, Sortierung nach `erstellt_am`
- Gantt-Datumsachse zeigte alle Labels ohne Abstand bei kleinen Zoom-
  stufen; Labels werden jetzt dynamisch ausgedünnt (jeder 2./3./7. Tag)
- Doppelte Gantt/Timeline-Tabs beim Wechsel zwischen Untertabs

---

## [1.0.0] – 2026-06-23

### Hinzugefügt

**Prozess-Modell (neue Kernfunktion):**
- Mehrere Prozesse laufen gleichzeitig, jeder mit eigener Checkliste und
  eigenen Teilnehmern; Prozess-Auswahl über Tab-Leiste
- Prozesse können öffentlich (🌐) oder privat (🔒) sein; Verantwortliche
  können die Sichtbarkeit ihres Prozesses selbst steuern
- Neue Tabelle `prozess_teilnehmer` mit Rollen `verantwortlich` und
  `mitarbeitend`; Verantwortliche können selbst Mitarbeitende zuweisen
- Öffentliches Dashboard zeigt alle öffentlichen Prozesse als separate Tabs
- Berechtigungsmodell: Admins verwalten alle Prozesse; Verantwortliche
  verwalten ihren eigenen Prozess vollständig; Mitarbeitende können
  Häkchen setzen, Daten eintragen und Kommentare schreiben

**Checkliste:**
- Schritte abhaken, Verantwortliche sowie Start- und Zieldatum eintragen
- Kommentarfeld pro Schritt (prozessspezifisch, nur für Angemeldete)
- Weiterführende Infos mit Markdown-Formatierung (von Admins/Verantwortlichen)
- Aufklapp-Zustand bleibt nach Aktualisierungen erhalten
- Parallel-Erkennung auf Basis echter Zeitraum-Überschneidungen

**Zeitstrahl:**
- Gantt- und Timeline-Ansicht mit Zoom-Schieberegler (1–7 Tage/Spalte)
- Schritte mit Start- und Zieldatum als Balken, nur Zieldatum als Punkt
- SVG-Export mit korrektem XML-Escaping und Balken-Clipping

**Export:**
- Checkliste als CSV (UTF-8 BOM, Semikolon-getrennt, öffnet direkt in Excel)
- Zeitstrahl als SVG-Vektorgrafik
- Drucken über Browser-Druckdialog

**Admin-Bereich:**
- Prozesse anlegen (mit Name, Beschreibung, Sichtbarkeit, Vorlage/Snapshot)
- Vorlagen-Snapshots einfrieren und als Basis für neue Prozesse verwenden
- Zugriffsverwaltung: Personen freigeben, Rollen ändern, entfernen
- Vorlagenverwaltung: Phasen und Schritte per Drag-and-Drop verwalten
- Aktivitätsprotokoll mit CSV-Export

**Sonstiges:**
- Lokales Notfall-Passwort (bcrypt, per SQL gesetzt) unabhängig von WebUntis
- Eigene Farbpalette (15 Farben + Hex-Eingabe) statt Browser-Farbpicker
- Mobilansicht optimiert
- Footer zeigt angemeldeten Benutzer mit Rolle-Badge
- 6 optionale Prozess-Vorlagen als Seed-Dateien
