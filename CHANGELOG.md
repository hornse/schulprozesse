# Changelog

Alle wesentlichen Änderungen an diesem Projekt werden hier dokumentiert.
Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

---

## [Unreleased]

### Hinzugefügt
- **Schritte per Drag-and-drop verschieben** ("Prozesse verwalten") – sowohl
  innerhalb einer Phase umsortieren als auch phasenübergreifend verschieben,
  wirkt sich korrekt in allen vier Ansichten aus (Prozesse verwalten,
  Checkliste, Zeitstrahl, Gantt). Reihenfolge zählt je Phase (Begründung:
  `docs/ENTSCHEIDUNGEN.md`, E5). Ein per Vorlage entstandener Schritt
  wechselt die Phase über zwei neue, prozesseigene Spalten
  (`schritt_instanzen.instanz_phase_name`/`-farbe`, Migration 006) statt über
  die globale Vorlage – ein eigener Schritt (ohne Vorlage) trug diese
  Freiheit schon immer in `phase_name`/`phase_farbe`. Warum eine
  Fremdschlüssel-Spalte auf `phasen(id)` dafür nicht ausgereicht hätte:
  `docs/ENTSCHEIDUNGEN.md`, E6. Bedienung: native Drag-and-drop-Ereignisse für
  die Maus (gleiches Muster wie im bestehenden Vorlagen-Editor), zusätzlich
  zwei Pfeilschalter je Schritt fürs Umsortieren innerhalb der Phase und eine
  Auswahl für den Phasenwechsel – beide auch ohne Maus und auf Tablets per
  Fingertipp bedienbar (E8, E9). Neuer API-Endpunkt
  `POST /api/prozesse/{id}/schritte/reihenfolge`
  (`handleSchritteReihenfolge`, `backend/api/schritte.php`): voller Ersatz der
  Reihenfolge einer Phase je Aufruf, wie bei den beiden bestehenden
  Bulk-Umsortier-Endpunkten (`/api/vorlagen/reihenfolge`,
  `/api/phasen/reihenfolge`) – bewusst ohne Versions-/Zeitstempel-Abgleich
  für gleichzeitige Bearbeitung, Begründung E7. Scheitert das Speichern,
  springt die Ansicht auf den zuletzt bestätigten Stand zurück statt einen
  ungespeicherten Zwischenstand stehen zu lassen. Neue Testdatei
  `tests/schritt-verschieben.test.js` (in `tests-schulprozesse.sh`
  eingebunden): reine Umsortierfunktion `verschiebeSchritt()`, offline
  geprüft (innerhalb der Phase hoch/runter/Anfang/Ende, in eine andere
  Phase, in eine leere Phase, einziger Schritt seiner Phase, Regressionstest
  gegen die alte Dubletten-Phasenüberschrift, zwei Gegenproben für
  `pruefeSchrittlisteUnveraendert()`).
- **`docs/ENTSCHEIDUNGEN.md`** – Entscheidungsprotokoll nach dem Muster der
  Reihe (`../lernzeiten/docs/ENTSCHEIDUNGEN.md`), chronologisch,
  nummeriert, alte Einträge werden durch neue aufgehoben statt geändert.
  Vier Einträge nachgetragen: warum das ci-css-Modul unter
  `backend/public/vendor/` statt `frontend/vendor/` liegt (E1, Gegenstück
  zu lernzeiten E44), die projekteigenen Kategorienfarben als zulässige
  Ausnahme samt der bisher nur als CSS-Kommentar stehenden
  Kontrastentscheidungen zu `--accent`/`--error` (E2, Gegenstück zu
  lernzeiten E45), warum es zwei `dev-router.php` gibt (E3), und die
  sechs Einträge der Ausnahmeliste `OHNE_REGEL_BEKANNT` im Testskript
  (E4) – dort, wo keine Begründung auffindbar war, als „Grund unbekannt"
  vermerkt statt erfunden.
- **CLAUDE.md an den Stand der Reihe angeglichen** – Import von
  `@docs/ENTSCHEIDUNGEN.md`, ein Abschnitt „Design – ci-css" (Vendoring-
  Disziplin, Kein-Farbwert-außerhalb-der-Tokens mit der
  Kategorienpaletten-Ausnahme, keine erfundenen Modulklassennamen), ein
  Abschnitt „Regeln der Reihe" (`LC_ALL=C`, `grep`/`set -e`, Gegenprobe für
  neue Prüfungen, Prüfungszahl-Disziplin, `deploy.sh` ohne Leerfall-Falle –
  mit den tatsächlichen Anlässen aus diesem Projekt, nicht aus
  `lernzeiten` übernommen), ein Abschnitt „Vor jeder Auslieferung wirklich
  prüfen", Lizenz- und Sprachangabe. Der Dateibaum berichtigt: `tests/`,
  das `dev-router.php` an der Projektwurzel und `backend/public/vendor/`
  fehlten.
- **`deploy.sh` auf den Leerfall geprüft** – verträgt ihn bereits seit dem
  17.08.2026 (`git add -A`, dann `git commit` nur wenn `git diff --cached`
  etwas zeigt, `git push` unbedingt danach). Beide Fälle in einem
  isolierten Sandbox-Repo mit zwei lokalen Remotes durchgespielt: mit
  Änderung committet und pusht, ohne Änderung überspringt nur den Commit
  und pusht trotzdem – in beiden Fällen Exitcode 0. Kein Code geändert,
  keine Produktiv-Remotes berührt.

### Behoben
- **`handleListSchritte` sortierte gar nicht** – gefunden bei der Analyse für
  „Schritte verschieben" (Auftrag, Schritt 2), nicht Teil des ursprünglichen
  Auftrags, aber notwendig damit eine neue Reihenfolge überhaupt sichtbar
  wird: weder die Vorlage- noch die Eigene-Schritte-Abfrage hatten ein
  `ORDER BY`, und `array_merge()` reihte grundsätzlich zuerst alle
  Vorlage-Schritte, dann alle eigenen. `instanz_reihenfolge` ließ sich zwar
  schon vorher per `PATCH` setzen, wirkte sich aber in keiner der vier
  Ansichten sichtbar aus. Jetzt sortiert `handleListSchritte` selbst nach
  Phase und Reihenfolge, bevor es antwortet.
- **Dublizierte Phasenüberschrift in Checkliste und Zeitstrahl** – wurde ein
  Schritt zu einer bestehenden eigenen Phase hinzugefügt, bekam er über
  `handleCreateInstanzSchritt` eine `reihenfolge` über den ganzen Prozess statt
  je Phase (`backend/api/schritte.php`), und `handleListSchritte` liefert die
  Schritte ohne `ORDER BY`. Dadurch landete der neue Schritt in der flachen
  Liste nicht mehr neben seinen Phasengeschwistern. Checkliste, Gantt und
  SVG-Export gruppierten bisher nur über Zeilennachbarschaft
  („Phase weicht von der Vorzeile ab" → neue Überschrift) – dieselbe
  Fehlerklasse, die schon am 22.07.2026 für die Verwaltungsansicht behoben
  wurde (dort per Sortierung vor dem Gruppieren). Diesmal wird stattdessen
  echt nach Phasenwert gruppiert (`gruppiereNachPhase()`, gemeinsam für
  Checkliste, Gantt-Tabelle und SVG-Export), sodass die Reihenfolge in der
  Ausgangsliste die Anzeige nicht mehr beeinflussen kann.
- Regressionstest `tests/gruppierung.test.js` (in `tests-schulprozesse.sh`
  eingebunden) stellt einen Schritt nach, der über fremde Phasen hinweg ans
  Ende der Liste geschoben wird, und prüft, dass daraus keine zweite
  Phasenüberschrift entsteht.

Geplant:
- E-Mail-Erinnerungen bei überfälligen Schritten
- Alles-zurücksetzen für den kompletten Prozess (aktuell nur pro Phase)

---

## [2.2.2] – 2026-07-22

### Hinzugefügt
- **Selbst hinzugefügte Schritte löschen** – Schritte die über
  „+ Weiterer Schritt" für einen Prozess angelegt wurden, tragen jetzt
  das Kennzeichen „nur hier" und haben einen 🗑-Button zum endgültigen
  Löschen (mit Rückfrage). Vorlage-Schritte lassen sich weiterhin nur
  ausblenden – ein Löschversuch wird serverseitig mit HTTP 409 abgelehnt.
- **Migration 005** – neue Spalte `schritt_vorlagen.prozess_id` hält fest,
  für welchen Prozess ein Schritt angelegt wurde (NULL = Standard-Vorlage
  oder Snapshot)
- `DELETE /api/schritte/{id}` löscht Schritt-Instanz und zugehörige Vorlage,
  sofern diese ausdrücklich für den Prozess angelegt wurde
- `GET /api/schritte` liefert je Schritt zusätzlich `nur_dieser_prozess`
- Einheitliches 🗑-Symbol für Löschaktionen unter „Prozess verwalten"
- **Zurücksetzen in zwei Stufen** – jede Vorlage-Phase hat unter
  „Prozess verwalten" jetzt zwei Buttons statt einem:
  - „↺ Phase" setzt nur Phasenname und -farbe auf den Vorlage-Standard
    zurück (bisheriges Verhalten)
  - „↺ Alles" setzt zusätzlich die Schritt-Umbenennungen dieser Phase
    zurück und blendet ausgeblendete Schritte wieder ein; selbst
    hinzugefügte Schritte bleiben erhalten. Mit Bestätigungsdialog, der
    auflistet was zurückgesetzt wird.
- `DELETE /api/prozesse/{id}/instanz-phasen/{phase_id}` akzeptiert jetzt
  einen Body-Parameter `umfang` (`phase` = Standard, `alles`)

### Behoben
- **Duplizierte Phasen-Blöcke** in der Verwaltungsansicht – `neuerPhaseBlock`
  legt bei jedem Phasenwechsel einen Block an und erkennt damit nur
  aufeinanderfolgende Wechsel. Da neu angelegte Schritte hohe
  `vorlage_reihenfolge`-Werte bekamen, wechselte die Phase in der Sortierung
  mehrfach hin und her und erzeugte pro Wechsel einen neuen Block. Die
  Schritte werden jetzt vor dem Gruppieren nach `phase_reihenfolge` sortiert.
- **Zurücksetzen ohne Wirkung** – der Handler löschte nur `instanz_phasen`,
  was bei Prozessen ohne überschriebenen Phasennamen wirkungslos war
- Der Löschen-Handler für eigene Phasen griff auf eine nicht mehr
  existierende Variable `alle` zu und schlug still fehl
- Debug-Ausgaben (`console.log`) aus der Entwicklung entfernt

---

## [2.2.1] – 2026-07-20

### Hinzugefügt
- **Schritte zu bestehenden Vorlage-Phasen hinzufügen** – unter „Prozess
  verwalten → Schritte anpassen" hat jede Vorlage-Phase jetzt ein
  „Weiterer Schritt für diesen Prozess..."-Eingabefeld mit `+`-Button;
  der neue Schritt wird als `schritt_vorlage` + `schritt_instanz` nur
  für diesen Prozess angelegt – keine doppelte Phase, kein `instanz_schritt`
- **Schritte zu bestehenden eigenen Phasen hinzufügen** – auch eigene
  Phasen (unterer Bereich) haben jetzt ein Eingabefeld zum Ergänzen
  weiterer Schritte

### Behoben
- **Zurücksetzen-Button** – gibt jetzt eine sichtbare Fehlermeldung aus
  wenn `phase_id` nicht gefunden wird; `istEigenPhase` wird jetzt korrekt
  über `quelle === 'eigen'` statt nur über das Fehlen der `phase_id`
  bestimmt
- **Re-render nach Änderungen** – `block.replaceWith(renderInstanzSchrittVerwaltung())`
  durch `render()` ersetzt; der alte Ansatz renderte synchron bevor async
  Daten geladen waren, was zu leerem/unverändertem DOM führte
- **Neue Schritte erzeugten doppelte Phase** – `instanz_schritte` mit
  gleichem Phasennamen wie Vorlage-Phase erzeugte eine zweite identisch
  benannte Phase in der Checkliste; neuer Endpunkt
  `POST /api/prozesse/{id}/phasen/{phase_id}/schritte` legt Schritte
  korrekt in der bestehenden Phase an

---

## [2.2.0] – 2026-06-26

### Hinzugefügt
- **Schritte duplizieren** – jeder Vorlage-Schritt (Admin) und jeder
  Instanz-Schritt (Verantwortliche) kann mit dem ⎘-Button kopiert werden;
  Zielphase und neuer Titel wählbar; Kopie landet in der Standard-Vorlage
  (für Admins) oder als eigener Schritt im Prozess (für Verantwortliche)
- **Fortschrittsbalken zählt eigene Schritte** – `instanz_schritte` werden
  jetzt in `schritt_anzahl` und `erledigt_anzahl` mitgezählt

### Behoben
- **Standard-Vorlage** zeigt jetzt korrekt nur eigene Phasen (`quelle='standard'`);
  Phasen aus Snapshot-Prozessen werden nicht mehr angezeigt
- **Phasen-Nummerierung** in der Standard-Vorlage korrekt (1, 2, 3, 4, 5)
- **`_instanzenAusSnapshot`** – legt jetzt eigene Phasen pro Prozess an
  statt gemeinsame Phasen zu teilen; verhindert ungewollte Seiteneffekte
- **`aktiv`-Flag** in `prozesse` – neue Prozesse bekommen `aktiv = 1`;
  `handleListProzesse` filtert korrekt nach aktiven Prozessen

---

## [2.1.0] – 2026-06-26

### Hinzugefügt
- **Prozess-Archiv** – Admins können Prozesse archivieren statt löschen;
  archivierte Prozesse verschwinden aus den Tabs und sind für Nutzer
  unsichtbar; unter Admin → Prozesse → Tab „Archiv" jederzeit reaktivierbar
- **Fortschrittsbalken in Prozess-Tabs** – jeder Prozess-Tab zeigt einen
  dünnen farbigen Balken am unteren Rand der den Erledigungsgrad anzeigt
  (aktiver Tab: Akzentfarbe, inaktive: grau); Tooltip zeigt `X/Y Schritte (Z%)`
- **Vorlage-Schritte anlegen** – `+`-Button in der Admin-Vorlagenverwaltung
  legt Schritte korrekt für alle bestehenden Prozesse als Instanz an
  (vorheriger Bug: Referenz auf alte `schuljahre`-Tabelle)

### Behoben
- `handleDeletePhase` – SQL-Injection-Lücke durch String-Interpolation
  in Guard-Abfrage geschlossen; jetzt sauber mit Prepared Statement
- `export.php` – Variablenname `$schuljahrId` → `$prozessId` umbenannt
- `vorlagen-sets.php` – veralteten Kommentar aktualisiert
- `handleListProzesse` – filtert jetzt korrekt nach `aktiv = 1`;
  archivierte Prozesse nur bei `?mit_archiv=1` sichtbar

---

## [2.0.1] – 2026-06-26

### Hinzugefügt
- **deploy.sh** – Deploy-Script das automatisch den Cache-Busting-Timestamp
  in `index.html` aktualisiert (Format `YYYYMMDDHHMM`) und auf beide
  Remotes pusht; ab jetzt `./deploy.sh "Nachricht"` statt manuellem push
- **deploy/uberspace.md** – vollständige Dokumentation aller
  serverseitigen Konfigurationen die nicht in git versioniert sind:
  supervisord-Config, web backend, Domain-Symlink, git bare repo,
  Migrationen, Logs aktivieren/deaktivieren
- **Vollständiges Handbuch** als dritter Tab auf der Hilfe-Seite –
  10 Kapitel ohne Login zugänglich
- **Zugriffsliste** zeigt Prozess-Zugehörigkeit als farbige Badges

### Behoben
- Datei-Header aller PHP-Dateien auf korrekten Projektnamen aktualisiert
- `.htaccess` mit Erklärung warum sie nur Fallback ist
- Doppelter `[Unreleased]`-Block im Changelog entfernt

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
