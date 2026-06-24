# Changelog

Alle wesentlichen Änderungen an diesem Projekt werden hier dokumentiert.
Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [Unreleased]

Geplant:
- Zeitstrahl-Ansicht für vergangene Prozesse
- E-Mail-Erinnerungen bei überfälligen Schritten

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
