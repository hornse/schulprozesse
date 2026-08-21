# Benutzerhandbuch

---

## Für neue Nutzer: Schnelleinstieg

Die App hat eine eingebaute **Hilfe-Seite** (Tab „?" in der Navigation)
die ohne Anmeldung zugänglich ist. Dort finden sich Erste Schritte und FAQ.

Kurzübersicht:
1. URL aufrufen → „Anmelden" → WebUntis-Zugangsdaten eingeben
2. Falls Login scheitert: Admin um Freischaltung bitten
3. Nach Anmeldung: Prozess-Tab auswählen → Checkliste bearbeiten

---

## Navigation

**Zweizeiliger Header:**
- **Zeile 1** – Schullogo + Name links, Benutzer + Rolle + Abmelden rechts
- **Zeile 2** – Dashboard · Checkliste · Zeitstrahl · Prozess verwalten · Admin · Hilfe

**Prozess-Tabs** – direkt unter dem Header, wechselt alle Ansichten gleichzeitig.
Unter „Prozess verwalten" nur Prozesse sichtbar für die man verantwortlich ist.

---

## Dashboard

Zeigt ohne Anmeldung alle öffentlichen Prozesse. Nach Anmeldung den
aktuell gewählten Prozess mit: aktuellem Schritt, überfälligen Schritten,
Schritten in 14 Tagen, Fortschritt je Phase.

---

## Zeitstrahl

**Gantt** – HTML-Tabelle mit gemeinsamer Datumsachse. Zoom 1–7 Tage/Spalte.
Schritte mit Start+Zieldatum als Balken, nur Zieldatum als Punkt.

**Timeline** – chronologische Liste nach Zieldatum.

Export: ⬇ SVG und 🖨 Drucken.

---

## Checkliste (nach Anmeldung)

Schritt aufklappen:
- **Häkchen** – erledigt markieren
- **Verantwortlich** – wer diesen Schritt übernimmt
- **Start / Zieldatum** – Zeitraum für Gantt
- **Kommentar** – nur für Angemeldete
- **Weiterführende Infos** – Markdown-Hinweise

Eigene Schritte (von Verantwortlichen angelegt) haben ein „✎ eigen"-Badge
und dasselbe Detail-Panel wie Vorlage-Schritte.

---

## Prozess verwalten (Verantwortliche + Admins)

Eigener Tab, nur sichtbar wenn man für mindestens einen Prozess
verantwortlich ist. Die Prozess-Tabs zeigen nur die eigenen verantwortlichen
Prozesse.

### Sichtbarkeit
🌐 Öffentlich oder 🔒 Privat umschalten.

### Teilnehmer
Kürzel + Rolle (verantwortlich/mitarbeitend) hinzufügen.
Nur freigegebene Personen (unter Admin → Zugriff) können zugewiesen werden.

### Schritte dieses Prozesses anpassen

**Vorlage-Schritte anpassen:**
Jede Vorlage-Phase hat einen editierbaren Kopf (Farbwähler + Namensfeld).
- Farbe ändern → sofort in Checkliste sichtbar
- Name ändern → Enter oder Feld verlassen zum Speichern
- „↺ Phase" → setzt nur Phasenname und -farbe auf den Vorlage-Standard
  zurück; Schritt-Umbenennungen und Ausblendungen bleiben erhalten
- „↺ Alles" → setzt zusätzlich alle Schritt-Umbenennungen dieser Phase
  zurück und blendet ausgeblendete Schritte wieder ein. Selbst
  hinzugefügte Schritte bleiben erhalten. Es erscheint eine Rückfrage.
- „+ Weiterer Schritt" → neuer Schritt direkt in dieser Phase,
  erscheint nur in diesem Prozess

Pro Schritt:
- Titel umbenennen (Original bleibt als „← Originalname" sichtbar)
- „✕ ausblenden" / „↩ reaktivieren"
- ⎘ duplizieren
- 🗑 löschen – nur bei Schritten mit dem Kennzeichen „nur hier", also
  solchen die über „+ Weiterer Schritt" für diesen Prozess angelegt wurden.
  Schritte aus der Vorlage lassen sich hier nicht löschen – sie gehören zur
  Vorlage und können nur ausgeblendet werden. Löschen ist dort in der
  Vorlagenverwaltung im Admin-Bereich möglich.
- ⠿ ziehen zum Umsortieren (Maus) – auch in eine andere Phase, indem auf
  einen anderen Phasenblock losgelassen wird
- ↑ / ↓ verschiebt den Schritt innerhalb seiner Phase um eine Position –
  ohne Maus bedienbar, auch per Fingertipp auf Tablets
- „→ andere Phase …" wechselt die Phase des Schritts, angehängt ans Ende
  der gewählten Phase – ebenfalls ohne Maus/per Fingertipp bedienbar. Neue
  Phasen entstehen nicht dabei, dafür „Neue Phase anlegen" weiter unten.
  Ein verschobener Vorlage-Schritt folgt danach nicht mehr Umbenennungen
  seiner ursprünglichen Vorlage-Phase – reines Umsortieren innerhalb der
  eigenen Phase ändert daran nichts.
- Deaktivierte (ausgeblendete) Schritte lassen sich nicht verschieben,
  solange sie ausgeblendet sind – erst nach „↩ reaktivieren".

Ausgeblendete Schritte werden über einen Rückgabewert an die Ausgangsposition
zurückgesetzt, wenn das Speichern einer Verschiebung fehlschlägt (z. B. bei
Verbindungsproblemen) – eine Fehlermeldung erscheint oberhalb der Liste.

**Eigene Phasen und Schritte:**
- Phasenname eingeben, Farbe wählen
- Schritte zur Phase hinzufügen (Enter oder + Schritt)
- „💾 Phase mit Schritten speichern"
- Bestehende eigene Phasen: Name und Farbe direkt editierbar,
  Schritte umbenennbar, löschbar und nachträglich ergänzbar
- „✕ Phase löschen" entfernt die eigene Phase mit allen ihren Schritten
- Auch eigene Schritte lassen sich ziehen, per Pfeiltasten verschieben und
  über „→ andere Phase …" in eine Vorlage-Phase oder eine andere eigene
  Phase verschieben.

### Aktivitätsprotokoll
Letzte 200 Aktionen dieses Prozesses, als CSV exportierbar.

---

## Admin-Bereich (nur Admins)

Eigener Tab, unabhängig von Prozess-Tabs.

### Erscheinungsbild
Schulname, App-Titel, Primär-/Sekundärfarbe, Logo (PNG/JPG/SVG, max. 500 KB).
**Workflow:** Werte anpassen → „👁 Vorschau" (nur Admin sieht es) →
„⚡ Für alle aktivieren" → „↺ Zurücksetzen" wenn nötig.

### Prozesse
Übersicht aller Prozesse. Neuen Prozess anlegen:
- Name, Beschreibung, Sichtbarkeit
- Basis: aktuelle Vorlage, Snapshot oder „⬜ Leer starten"

### Vorlagen-Snapshots
Tab-Leiste: „Standard (WebUntis)" + alle Snapshots.
Snapshots direkt bearbeiten: Phasen/Schritte hinzufügen, umbenennen, löschen.
Änderungen betreffen nur neue Prozesse – bestehende unberührt.

### Zugriff
Personen freigeben (Kürzel + App-Rolle). Spalte „Zugewiesen in" zeigt
farbige Badges welchen Prozessen jemand zugewiesen ist.

### Vorlagen verwalten
Standard-Vorlagentabelle (globale Phasen/Schritte) pflegen.

---

## Hilfe-Seite (ohne Login)

Tab „?" in der Navigation. Zwei Bereiche:
- **Erste Schritte** – 6 Karten: Was ist die App, Anmeldung, Prozess-Tabs,
  Schritte erledigen, Ansichten, Ansprechpartner
- **FAQ** – 10 aufklappbare Fragen zu häufigen Problemen

---

## Datenschutzhinweis

Nur Koordinationsdaten werden gespeichert: WebUntis-Kürzel, Anzeigenamen,
Termine, Status, Kommentare. Kein Tracking. Daten verbleiben auf dem Schulserver.
