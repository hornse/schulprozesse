# Auftrag: Schritte verschieben (Drag-and-drop)

Einzelauftrag. Nach Erledigung bleibt die Datei als Beleg liegen.
Ablegen unter `docs/AUFTRAG-schritte-verschieben.md`.

**Arbeitsverzeichnis: `~/Projekte/schulprozesse`.**

---

## Was gebaut werden soll

Unter „Prozesse verwalten" sollen Schritte per Drag-and-drop verschoben
werden können:

* **innerhalb einer Phase** — Reihenfolge ändern
* **phasenübergreifend** — ein Schritt wechselt die Phase

Die neue Reihenfolge muss gespeichert werden und in **allen** Ansichten
richtig erscheinen: Prozesse verwalten, Checkliste, Zeitstrahl, Gantt.

---

## Schritt 0 — Voraussetzung prüfen, bevor irgendetwas beginnt

**Ist der Fehler mit der doppelt dargestellten Phase behoben?**

Er trat auf, wenn ein neuer Schritt hinzugefügt wurde: In Zeitstrahl und
Checkliste erschien die Phase ein zweites Mal, mit dem neuen Schritt in
der Dublette. Vermutete Ursache war eine Gruppierung über
aufeinanderfolgende Zeilen statt über die Phase.

**Wenn er noch besteht: anhalten und melden.** Drag-and-drop erzeugt
genau die Datenlage, die diesen Fehler auslöst — ein Schritt mit einer
Sortierung, die ihn zwischen fremde Phasen schiebt. Auf einer brüchigen
Gruppierung eine Verschiebefunktion zu bauen, heißt, den Fehler zum
Normalfall zu machen.

Prüfen, nicht annehmen: im `CHANGELOG.md` nachsehen und den Fehlerfall
tatsächlich herstellen.

## Schritt 1 — Ausgangsstand

```bash
cd ~/Projekte/schulprozesse
git status --short
tests-schulprozesse.sh
```

Ergebnis notieren, auch wenn etwas rot ist. **Nichts reparieren, was
nicht zum Auftrag gehört** — nur festhalten und im Bericht nennen.

## Schritt 2 — Analyse, und dann anhalten

Erst verstehen, dann bauen. Zu beantworten:

1. **Wie wird die Reihenfolge heute gespeichert?** Eine Spalte
   `sortierung`? Je Phase gezählt oder je Prozess? Was passiert beim
   Anlegen eines Schrittes?
2. **Wie liest jede der vier Ansichten die Schritte?** Gemeinsame
   Funktion oder je Ansicht eigener Code? Wo wird gruppiert, wo
   sortiert?
3. **Welche API-Handler gibt es für Schritte?** Was kann `PATCH`/`PUT`
   heute, was fehlt?
4. **Gibt es bereits eine Stelle, an der Phase oder Reihenfolge geändert
   wird?** Etwa beim Anlegen oder Bearbeiten eines Schrittes.

**Danach anhalten und berichten. Nicht weiterbauen.**

Im Bericht eine Empfehlung zur Sortierung mit Begründung, mindestens zu
dieser Frage: *Wird `sortierung` je Phase gezählt (1, 2, 3 innerhalb
jeder Phase) oder fortlaufend über den ganzen Prozess?* Beides ist
vertretbar, beides hat Folgen — je Phase macht die Gruppierung
unabhängig von der Sortierung, fortlaufend erlaubt eine einzige
`ORDER BY`-Klausel. Ich entscheide, du empfiehlst.

## Schritt 3 — Umsetzung

Erst nach meiner Zustimmung zu Schritt 2.

### Die Umsortierlogik gehört in reine Funktionen

Kein Datenbankzugriff, kein DOM. Eingabe: die Schrittliste mit Phase und
Sortierung, dazu die gewünschte Verschiebung. Ausgabe: die neue Liste.
Das ist der Teil, der offline geprüft werden kann — und der Teil, in dem
Fehler wehtun.

### Robustheit

* **Ein Schritt darf beim Verschieben nicht verlorengehen.** Vor dem
  Speichern prüfen: Ist die Zahl der Schritte unverändert? Kommt jede ID
  genau einmal vor?
* **Was passiert bei gleichzeitiger Bearbeitung?** Zwei Personen, die
  denselben Prozess umsortieren, dürfen einander nicht stillschweigend
  überschreiben. Sag mir im Bericht, wie du damit umgehst — auch wenn
  die Antwort „für diesen Nutzerkreis vernachlässigbar" lautet. Dann
  aber ausdrücklich.
* **Scheitert das Speichern, springt die Ansicht zurück** auf den letzten
  bestätigten Stand. Keine Anzeige, die etwas behauptet, was nicht
  gespeichert ist.

### Bedienung

* **Vanilla JS, kein Build-Schritt, keine Bibliothek.** Native
  Drag-and-drop-Ereignisse oder Pointer-Events.
* **Es muss auch ohne Maus gehen.** Ein Schritt, der sich nur per
  Drag-and-drop verschieben lässt, ist für Tastaturbedienung unerreichbar
  — und diese Anwendung wird von Kolleginnen und Kollegen genutzt, nicht
  von Entwicklern. Zwei Pfeilschalter je Schritt genügen; das Verschieben
  in eine andere Phase braucht dann eine Auswahl.
* **Berührungsbedienung nicht vergessen.** Native
  Drag-and-drop-Ereignisse wirken auf Tablets nicht. Wenn das den Rahmen
  sprengt, sag es — dann bleibt es für später, aber es steht im Bericht.
* Während des Ziehens muss erkennbar sein, wo der Schritt landet.
* Farben und Abstände ausschließlich aus `ci-tokens.css`.

## Schritt 4 — Tests

* **Eigene Testdatei für die Umsortierlogik**, offline lauffähig. Darin
  mindestens: innerhalb einer Phase nach oben und nach unten, an den
  Anfang, ans Ende, in eine andere Phase, in eine leere Phase, ein
  einzelner Schritt als einziger seiner Phase.
* **Ein Test, der den alten Fehler festhält:** Ein Schritt mit einer
  Sortierung, die ihn zwischen fremde Phasen schiebt, darf in keiner
  Ansicht eine zweite Phasenüberschrift erzeugen.
* **Jeder neue Test braucht eine Gegenprobe.** Den Fehlerfall künstlich
  herstellen und zeigen, dass der Test anschlägt. Sonst weiß niemand, ob
  er überhaupt etwas prüft.
* **Die Prüfungszahl von `tests-schulprozesse.sh` muss steigen** — um den
  erwarteten Betrag. Steigt sie anders, ist das ein Befund.
* `export LC_ALL=C` in jedem Shell-Skript mit Zahlenvergleichen.

## Schritt 5 — Dokumentation

* **`docs/ENTSCHEIDUNGEN.md`**: neue Einträge für die Sortierentscheidung
  aus Schritt 2, für den Umgang mit gleichzeitiger Bearbeitung und für
  die Tastaturbedienung. Jeweils mit Anlass und Begründung, nicht nur mit
  dem Ergebnis. Alte Einträge werden nicht geändert.
* **`CHANGELOG.md`**: mit Begründungen, nicht nur Aufzählung. Gefundene
  Fehler mitbenennen, auch wenn sie nicht zum Auftrag gehörten.
* **`docs/BENUTZERHANDBUCH.md`**: die neue Bedienung beschreiben,
  einschließlich der Bedienung ohne Maus.
* **`CLAUDE.md`**: nur ergänzen, wenn eine **Dauerregel** entstanden ist
  — etwas, das in sechs Monaten noch gilt. Tagesarbeit gehört nicht
  hinein.

## Schritt 6 — Ausliefern

```bash
tests-schulprozesse.sh
git add -A
git commit -m "Schritte per Drag-and-drop verschieben"
./deploy.sh "Schritte verschieben"
```

Danach kontrollieren, dass beide Gegenstellen den neuen Stand tragen.

**Bei Rot: anhalten und melden, nicht reparieren.**

## Schritt 7 — Bericht

Als Fließtext mit Tabellen, nicht als Aufzählung von Dateinamen:

1. **Ausgangsstand** aus Schritt 1
2. **Was die Analyse ergeben hat** und was davon anders war als vermutet
3. **Welche Entscheidungen gefallen sind** und mit welcher Begründung
4. **Prüfungszahl vorher und nachher**, und ob sie um den erwarteten
   Betrag gestiegen ist
5. **Was nicht umgesetzt wurde** und warum — Berührungsbedienung,
   gleichzeitige Bearbeitung, was auch immer offen blieb
6. **Was unterwegs gefunden wurde**, das nicht zum Auftrag gehörte
7. **Commit-Hashes** und Bestätigung, dass beide Gegenstellen stimmen

## Grundsätzliches

* **Vermutungen als Vermutungen kennzeichnen**, mit dem Versuch dazu, der
  sie entscheiden würde.
* **„Ich weiß es nicht" statt einer plausiblen Vermutung.** Befunde
  stammen aus gelesenem Code oder einem echten Lauf, nicht aus
  Mustererkennung.
* **Rückfragen vor Entscheidungen mit Tragweite**, nicht danach.
* **Trocken ist der Standard**, wo ein Skript etwas Zerstörendes tun
  kann.
