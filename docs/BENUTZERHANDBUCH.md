# Benutzerhandbuch

Dieses Handbuch richtet sich an alle Nutzergruppen: neue Kolleg:innen,
Verantwortliche und Admins.

---

## Für neue Nutzer: Schnelleinstieg

Die App hat eine eingebaute **Hilfe-Seite** (Tab „?" in der Navigation) die
ohne Anmeldung zugänglich ist. Dort finden sich Erste Schritte und ein FAQ.

Kurzübersicht:

1. URL der App aufrufen
2. Oben rechts „Anmelden" klicken
3. WebUntis-Zugangsdaten eingeben
4. Falls der Login scheitert: Admin der App um Freischaltung bitten

---

## Navigation

**Zweizeiliger Header** – oben sticky:

- **Zeile 1** – Schullogo + Schulname links, angemeldeter Benutzer mit
  Rolle-Badge und Abmelden rechts
- **Zeile 2** – Haupttabs: Dashboard · Checkliste · Zeitstrahl ·
  Prozess verwalten (Verantwortliche/Admin) · Admin · Hilfe

**Prozess-Tabs** – direkt unter dem Header, wechselt in allen Ansichten
gleichzeitig den aktiven Prozess.

---

## Hilfe-Seite (ohne Login zugänglich)

Über den „?"-Tab in der Navigation. Zwei Bereiche:

**Erste Schritte** – geführte Einführung in 6 Karten: Was ist die App?,
Anmeldung, Prozess-Tabs, Schritte erledigen, Ansichten, Ansprechpartner.

**FAQ** – 10 aufklappbare Fragen zu häufigen Problemen und Missverständnissen,
z. B. Anmeldung schlägt fehl, versehentliches Häkchen, Datenschutz.

---

## Dashboard

Zeigt ohne Anmeldung alle öffentlichen Prozesse. Nach Anmeldung den
aktuell gewählten Prozess mit:
- Welcher Schritt gerade dran ist
- Überfällige Schritte (Zieldatum überschritten)
- Schritte in den nächsten 14 Tagen
- Fortschritt je Phase als Balken

Kommentare und Verantwortliche sind nur nach Anmeldung sichtbar.

---

## Zeitstrahl

**Gantt** – Horizontale Tabelle mit gemeinsamer Datumsachse für alle Zeilen.
Schritte mit Start + Zieldatum als Balken, nur Zieldatum als Punkt.
Zoom-Schieberegler (1–7 Tage/Spalte).

**Timeline** – Chronologische Liste nach Zieldatum sortiert.

**Export** – ⬇ SVG und 🖨 Drucken oben in der Tab-Leiste.

---

## Checkliste (nach Anmeldung)

Jeden Schritt anklicken zum Aufklappen:

- **Häkchen** – als erledigt markieren, wird im Aktivitätsprotokoll gespeichert
- **Verantwortlich** – Freitext, wer diesen Schritt übernimmt
- **Start / Zieldatum** – Zeitraum für den Gantt-Balken
- **Kommentar** – prozessspezifische Notiz, nur für Angemeldete sichtbar
- **Weiterführende Infos** – Markdown-Hinweise von Admins/Verantwortlichen

Alle Felder speichern automatisch beim Verlassen. Datumsfelder und
Parallel-Toggle aktualisieren den Zeitstrahl sofort.

**Export** – ⬇ CSV (öffnet in Excel) und 🖨 PDF oberhalb der Liste.

---

## Prozess verwalten (Verantwortliche + Admins)

Eigener Tab in der Navigation, bezogen auf den aktiven Prozess-Tab.

**Sichtbarkeit** – 🌐 Öffentlich oder 🔒 Privat umschalten.

**Teilnehmer** – Kürzel eingeben, Rolle wählen (verantwortlich/mitarbeitend),
hinzufügen. Nur Personen die unter „Zugriff" freigegeben sind, können
zugewiesen werden.

**Phasen und Schritte** – Verantwortliche können direkt hier Phasen und
Schritte ihres Prozesses bearbeiten: umsortieren (Drag-and-Drop), umbenennen,
einfärben, neue Schritte anlegen, Schritte deaktivieren.

---

## Admin-Bereich (nur Admins)

Eigener Tab in der Navigation.

### Erscheinungsbild

Schulname, App-Titel, Primär- und Sekundärfarbe, Logo (PNG/JPG/SVG,
max. 500 KB) konfigurieren.

**Workflow:**
1. Werte anpassen
2. „Vorschau anwenden" – nur der Admin sieht die Änderung
3. „Für alle aktivieren" – Änderung wird für alle Nutzer sichtbar
4. „Zurücksetzen" setzt alle Werte auf den Ausgangszustand

### Prozesse

Übersicht aller Prozesse. Neuen Prozess anlegen mit Name, Sichtbarkeit
und Basis (aktuelle Vorlage, Snapshot oder leer starten).

### Vorlagen-Snapshots

Aktuellen Stand einfrieren → als Basis für neue Prozesse nutzen.
6 vorgefertigte Snapshots per `seed_vorlagen_snapshots.sql` verfügbar.

### Zugriff

Personen freigeben (Kürzel + Rolle), dann in „Prozess verwalten"
als Teilnehmer zuweisen.

### Vorlage verwalten

Standard-Vorlagentabelle (Phasen und Schritte) pflegen.

### Aktivitätsprotokoll

Letzte 200 Aktionen des aktiven Prozesses, als CSV exportierbar.

---

## Datenschutzhinweis

Die App speichert ausschließlich die zur Prozesskoordination nötigen Daten:
WebUntis-Kürzel und Anzeigenamen, Termine, Häkchen-Status und Kommentare.
Es findet kein Tracking statt. Daten verlassen den Schulserver nicht.
