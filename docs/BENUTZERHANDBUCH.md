# Benutzerhandbuch

---

## Navigation

Die App hat eine zweizeilige sticky Navigation oben:

**Zeile 1** – Schulname links, angemeldeter Benutzer mit Rolle-Badge und
Abmelden-Button rechts.

**Zeile 2** – Hauptnavigation:
- **Dashboard** – öffentliche Statusübersicht, immer sichtbar
- **Checkliste** – nur nach Anmeldung
- **Zeitstrahl** – Gantt und Timeline, immer sichtbar
- **Prozess verwalten** – für Verantwortliche und Admins
- **Admin** – nur für Admins

**Prozess-Tabs** – direkt unter der Navigation, zeigt alle Prozesse denen
man zugewiesen ist. Klick wechselt den Prozess in allen Ansichten.

---

## Dashboard (öffentlich)

Zeigt ohne Anmeldung alle öffentlichen Prozesse als separate Tabs. Pro Prozess:
- Welcher Schritt gerade dran ist
- Überfällige Schritte
- Schritte die in den nächsten 14 Tagen fällig sind
- Fortschritt je Phase

Nicht öffentlich: Verantwortliche, Kommentare, private Prozesse.

---

## Zeitstrahl (öffentlich, erweitert nach Anmeldung)

Zwei Untertabs:

**Gantt:** Horizontale Ansicht mit Datumsachse. Schritte mit Start- und
Zieldatum als Balken, nur Zieldatum als Punkt. Zoom-Schieberegler
(1–7 Tage/Spalte) – die Datumsachse wird automatisch ausgedünnt damit
die Labels nicht überlappen.

**Timeline:** Chronologische Liste mit Datums-Trennlinien.

**Export:** ⬇ SVG und 🖨 Drucken oben in der Tab-Leiste.

---

## Anmeldung

Über den „Anmelden"-Button oben rechts mit WebUntis-Zugangsdaten.
Ein korrektes Passwort allein reicht nicht – die Person muss von einem
Admin freigegeben und einem Prozess zugewiesen sein.

---

## Checkliste (nach Anmeldung)

Zeigt den aktuell gewählten Prozess (Prozess-Tab oben). Jeden Schritt
anklicken zum Aufklappen:

**Häkchen** – erledigt setzen, wird protokolliert.

**Verantwortlich** – wer diesen Schritt übernimmt.

**Start / Zieldatum** – Zeitraum für den Gantt-Balken.

**Kommentar** – prozessspezifische Kurznotiz, nur für Angemeldete.

**Weiterführende Infos** – Markdown-Hinweise von Admins/Verantwortlichen.

**Export:** ⬇ CSV und 🖨 PDF über die Schaltflächen oberhalb der Liste.

---

## Prozess verwalten (Verantwortliche + Admins)

Eigene Seite über den Tab „Prozess verwalten" in der Navigation.
Immer auf den aktuell gewählten Prozess (Prozess-Tab) bezogen.

**Sichtbarkeit:** 🌐 Öffentlich oder 🔒 Privat umschalten.

**Teilnehmer:** Kürzel eingeben, Rolle wählen (verantwortlich/mitarbeitend),
hinzufügen. Rollen ändern oder Teilnehmer entfernen. Nur Personen die
bereits unter „Zugriff" freigegeben sind, können hinzugefügt werden.

**Phasen und Schritte verwalten:** Verantwortliche können direkt auf dieser
Seite die Phasen und Schritte ihres Prozesses bearbeiten:
- Phasen per ⠿-Griff umsortieren, umbenennen, einfärben
- Schritte hinzufügen, bearbeiten, umsortieren, deaktivieren
- Weiterführende Infos mit Markdown und Live-Vorschau

---

## Admin-Bereich (nur Admins)

Eigene Seite über den Tab „Admin" in der Navigation.

### Prozesse

Übersicht aller Prozesse. Neuen Prozess anlegen:
- Name und optionale Beschreibung
- Sichtbarkeit: öffentlich oder privat
- Basis: aktuelle Vorlage, leer starten oder ein Snapshot

Der Anleger wird automatisch als Verantwortlicher eingetragen.

### Vorlagen-Snapshots

**Jetzt einfrieren:** Speichert alle aktiven Phasen und Schritte als
benannten Snapshot. Dient als Basis für neue Prozesse.

6 vorgefertigte Snapshots können mit `seed_vorlagen_snapshots.sql`
eingespielt werden (siehe INSTALL.md).

### Zugriff

Personen freigeben damit sie sich anmelden können. Danach in
„Prozess verwalten" als Teilnehmer zuweisen.

### Vorlage verwalten

Phasen und Schritte der Standard-WebUntis-Vorlage pflegen.

### Aktivitätsprotokoll

Letzte 200 Aktionen des aktuellen Prozesses, als CSV exportierbar.

---

## Lokales Notfall-Passwort

Ein lokaler Benutzer der unabhängig von WebUntis funktioniert kann per SSH
eingerichtet werden. Genaue Befehle siehe `docs/INSTALL.md`.

---

## Was die App (noch) nicht kann

- Keine E-Mail-Erinnerungen
- Kein Zeitstrahl für abgeschlossene Prozesse
