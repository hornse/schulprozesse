# Benutzerhandbuch

## Übersicht der Ansichten

Die App hat drei Tabs:

- **Dashboard** – öffentliche Statusübersicht ohne Anmeldung (nur öffentliche Prozesse)
- **Zeitstrahl** – Gantt- und Timeline-Ansicht, öffentlich sichtbar
- **Checkliste** – nur nach Anmeldung, mit allen Details und Bearbeitungsmöglichkeiten

---

## Dashboard (öffentlich)

Zeigt ohne Anmeldung alle öffentlichen Prozesse als separate Tabs. Pro Prozess:
- Welcher Schritt gerade dran ist
- Überfällige Schritte (Zieldatum überschritten, noch nicht erledigt)
- Schritte, die in den nächsten 14 Tagen fällig sind
- Fortschritt je Phase als Balken

Nicht öffentlich sichtbar: Verantwortliche, Kommentare, private Prozesse.

---

## Zeitstrahl (öffentlich, erweitert nach Anmeldung)

Zwei Untertabs:

**Gantt:** Horizontale Ansicht mit Datumsachse. Schritte mit Start- und
Zieldatum erscheinen als durchgehender farbiger Balken, Schritte mit nur
einem Zieldatum als Punkt. Zoom-Schieberegler (1–7 Tage/Spalte).

**Timeline:** Chronologische Liste mit Datums-Trennlinien. Zeigt bei Schritten
mit Startdatum einen „ab TT.MM."-Hinweis.

**Export:** ⬇ SVG exportiert den Zeitstrahl als Vektorgrafik. 🖨 Drucken öffnet
den Browser-Druckdialog.

---

## Anmeldung

Über den Button „Anmelden" oben rechts mit den WebUntis-Zugangsdaten.
Ein korrektes Passwort allein reicht nicht – die Person muss zusätzlich von
einem Admin freigegeben worden sein.

Nach der Anmeldung erscheinen alle Prozesse als Tabs, denen die Person
zugewiesen ist.

---

## Checkliste (nach Anmeldung)

Über den Tab „Checkliste" erreichbar. Jeden Schritt anklicken, um Details
aufzuklappen:

**Häkchen** – setzt den Schritt auf erledigt. Wird im Aktivitätsprotokoll
aufgezeichnet.

**Verantwortlich** – Freitext, wer diesen Schritt übernimmt.

**Start** – optionales Startdatum. Zusammen mit dem Zieldatum wird ein
Zeitraum definiert, der im Gantt als Balken dargestellt wird.

**Zieldatum** – geplantes Enddatum. Schritte mit überlappenden Zeiträumen
werden automatisch als parallel erkannt.

**Parallel möglich** – manuelles Flag für diesen Prozess.

**Kommentar** – prozessspezifische Kurznotiz, nur für angemeldete Personen.

**Weiterführende Infos** – von Admins oder Verantwortlichen hinterlegte
Hinweise (Markdown), nur für Angemeldete sichtbar.

Alle Felder speichern automatisch beim Verlassen. Datumsfelder und der
Parallel-Toggle aktualisieren den Zeitstrahl sofort.

**Export:** ⬇ CSV lädt die vollständige Checkliste herunter (öffnet direkt
in Excel). 🖨 PDF öffnet den Browser-Druckdialog.

---

## Prozess verwalten (Verantwortliche)

Wer für einen Prozess als „Verantwortlich" eingetragen ist, sieht nach
der Anmeldung den Bereich „Prozess verwalten":

**Sichtbarkeit:** 🌐 Öffentlich (im Dashboard für alle sichtbar) oder
🔒 Privat (nur für Teilnehmer). Per Toggle umschaltbar.

**Teilnehmer:** Mitarbeitende hinzufügen (Kürzel eingeben + Rolle wählen),
Rollen ändern, Teilnehmer entfernen. Nur Personen die bereits unter
„Zugriff" freigegeben sind können hinzugefügt werden.

---

## Admin-Bereich

Erscheint nach Anmeldung mit der Rolle „admin" am Ende der Seite.

### Prozesse

**Neuen Prozess anlegen:**
- Name eingeben (z. B. „Abitur 2027")
- Beschreibung optional
- Basis wählen: aktuelle Vorlage oder ein gespeicherter Snapshot
- Sichtbarkeit: öffentlich oder privat
- Der Anleger wird automatisch als Verantwortlicher eingetragen

**Prozess aktivieren:** Markiert einen Prozess als aktiv (wird zuerst in
der Tab-Leiste angezeigt).

### Vorlagen-Snapshots

**Jetzt einfrieren:** Speichert den aktuellen Stand aller Phasen und
aktiven Schritte als benannten Snapshot. Dient als Basis für neue Prozesse
mit derselben Struktur.

### Zugriff

Nur Personen in dieser Liste können sich anmelden. Kürzel eingeben,
Name und Rolle (mitglied/admin) setzen, dann „Freigeben". Danach in
„Prozess verwalten" als Teilnehmer des jeweiligen Prozesses zuweisen.

### Vorlage verwalten

Phasen und Schritte der Standard-Vorlage pflegen:
- Phasen per Drag-and-Drop umsortieren, umbenennen, einfärben
- Schritte hinzufügen, bearbeiten, umsortieren, deaktivieren
- Weiterführende Infos mit Markdown und Live-Vorschau

### Aktivitätsprotokoll

Zeigt die letzten 200 Aktionen des aktiven Prozesses. CSV-Export verfügbar.

---

## Lokales Notfall-Passwort

Ein lokaler Benutzer der unabhängig von WebUntis funktioniert kann per SSH
eingerichtet werden. Genaue Befehle siehe `docs/INSTALL.md`.

---

## Was die App (noch) nicht kann

- Keine E-Mail-Erinnerungen bei überfälligen Schritten
- Kein Zeitstrahl für abgeschlossene Prozesse (nur Checkliste)
