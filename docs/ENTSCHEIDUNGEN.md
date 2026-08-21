# Entscheidungen

Chronologisch, mit Begründung. Neue Einträge unten anfügen, alte nicht
ändern — überholte Entscheidungen werden durch einen neuen Eintrag
aufgehoben, nicht weggelöscht.

Zweck: Diskussionen finden im Chat statt, Entscheidungen gehören ins
Repo. Sonst muss beim nächsten Mal alles neu erklärt werden.

Die ersten vier Einträge sind am 20.08.2026 nachgetragen: Entscheidungen,
die schon vorher galten, aber nur verstreut dokumentiert waren (als
CSS-Kommentar, als Testskript-Kommentar oder gar nicht). Das Datum in der
Überschrift ist das der Niederschrift; wo bekannt, steht das Datum der
eigentlichen Entscheidung zusätzlich im Text.

---

## E1 — Modul liegt unter backend/public/vendor/, nicht frontend/vendor/ (20.08.2026, entschieden 12.08.2026)

**Anlass:** Die Kopierschleife des Moduls `hornse/ci-css` schreibt für die
übrigen fünf Projekte der Reihe nach `frontend/vendor/ci-css/`.
`schulprozesse` hat kein `frontend/`-Verzeichnis; hier liegt das Modul seit
der Umstellung auf ci-css (v2.3.0, 12.08.2026) unter
`backend/public/vendor/ci-css/`.

**Warum das kein Verstoß ist:** Der Docroot von `schulprozesse` ist
`backend/public/`, nicht die Projektwurzel. Ausgeliefertes muss unter dem
Docroot liegen, sonst wird es am Router vorbei behandelt und läuft auf
Uberspace in eine Fatal (`doc_root`-Beschränkung). `backend/public/` ist
hier das, was in den anderen fünf Projekten `frontend/` ist — dieselbe
Docroot-Regel, nicht eine Ausnahme davon.

**Festgehalten im Modul-Schwesterprojekt:** `../lernzeiten/docs/ENTSCHEIDUNGEN.md`,
Eintrag E44 (19.08.2026), hält dieselbe Sache von der anderen Seite fest —
aus Sicht der Kopierschleife im Modul-Repo: "Nicht das Projekt an die
Schleife anpassen, sondern die Schleife an die Wirklichkeit." Dieser
Eintrag hier ist das Gegenstück auf Projektseite.

**Entscheidung:** `backend/public/vendor/ci-css/` bleibt. Keine Angleichung
an `frontend/vendor/`.

---

## E2 — Projekteigene Kategorienfarben sind eine zulässige Ausnahme (20.08.2026, entschieden 12.–14.08.2026)

**Anlass:** `backend/public/css/style.css` führt im `:root`-Block
projekteigene Rohfarben außerhalb von `ci-tokens.css`: `--m-oeffentlich`,
`--m-privat`, `--m-verantwortlich`, `--m-mitarbeitend` (je mit
`-bg`-Variante, für Sichtbarkeits- und Rollenmarkierungen), dazu
`--verlauf-start` (Fortschrittsanzeige) und die Transparenzwerte
`--auf-dunkel-1/2/3`, `--schatten-1/2`.

**Warum das kein Verstoß gegen „kein Farbwert außerhalb der Tokens" ist:**
Die Regel kennt eine Ausnahme für Kategorienpaletten, die nur ein Projekt
braucht — Rollen- und Sichtbarkeitsmarken, für die kein anderes Projekt der
Reihe Bedarf hat. Bedingung: Die Werte stehen im `:root`-Block des
Projekts, mit Kommentar, der den Kontrast dokumentiert. Beides ist erfüllt
(`style.css`, Zeilen 48–54). `tests-schulprozesse.sh` prüft entsprechend nur
„außerhalb des `:root`-Blocks", nicht „außerhalb von `ci-tokens.css`".

**Festgehalten im Modul-Schwesterprojekt:** `../lernzeiten/docs/ENTSCHEIDUNGEN.md`,
Eintrag E45 (19.08.2026), hält dieselbe Prüfung von außen fest und vermerkt
dort ausdrücklich: „Diese Entscheidungen stehen bisher nur als
CSS-Kommentar. Sie gehören zusätzlich in ein Entscheidungsprotokoll des
Projekts — ein Kommentar in einer Datei wird beim nächsten Umbau
mitgelöscht, ein Protokolleintrag nicht." Dieser Eintrag löst das ein.

**Die beiden Kontrastentscheidungen, die bisher nur als CSS-Kommentar
standen** (`style.css`, Zeilen 32–40 — der Kommentar dort bleibt zusätzlich
stehen):

- `--error` stand auf `#B5577A` und erreichte gegen `--paper` nur einen
  Kontrast von 4.15, gegen `--paper-2` sogar nur 3.83 — beides unter dem
  geforderten WCAG-AA-Wert von 4.5, ausgerechnet bei der Farbe für
  Fehlermeldungen. Ersetzt durch `var(--ci-fehler)` aus dem Modul.
- `--accent` stand auf `#5B6FA8`: 4.46 gegen `--paper`, 4.12 gegen
  `--paper-2` — ebenfalls unter 4.5 — und lag als „das Blaue" mit `#1d4e89`
  zu nah an der Akzentfarbe von `sprechtag`. Ersetzt durch `var(--ci-akzent)`
  (Indigo) aus dem Modul: 6.93 gegen Weiß, gegen `sprechtag` klar
  unterscheidbar.

**Entscheidung:** `--error` und `--accent` beziehen sich seither auf die
Modul-Tokens; die vier Kategorienpaletten plus Verlauf- und
Transparenzwerte bleiben projekteigen im `:root`-Block von `style.css`.

---

## E3 — Zwei dev-router.php mit unterschiedlichem Zweck (20.08.2026, seit 23.06./25.06.2026)

**Befund:** Es gibt zwei Dateien dieses Namens im Projekt. Keine Dopplung —
beide haben einen eigenen, im jeweiligen Kopfkommentar benannten Zweck:

- **`dev-router.php`** (Projektwurzel, seit dem Initial-Commit,
  23.06.2026): ausschließlich für lokale Tests mit `php -S`. Übernimmt laut
  eigenem Kommentar, was auf dem echten Server Apache über
  `backend/public/.htaccess` erledigt — der eingebaute PHP-Entwicklungsserver
  wertet `.htaccess` nicht aus. Leitet `/api/...` an
  `backend/public/api-router.php` weiter, alles andere reicht sie an den
  eingebauten Server zurück (`return false`). Aufruf laut README:
  `php -S localhost:8000 -t backend/public dev-router.php`, gestartet an
  der Projektwurzel.
- **`backend/public/dev-router.php`** (seit 25.06.2026): der
  Produktions-Router für Uberspace. Laut eigenem Kommentar von supervisord
  gestartet, ersetzt dort Apache `mod_rewrite`. Liefert statische Dateien
  direkt aus, leitet `/api/...` an `api-router.php` im selben Verzeichnis
  weiter, liefert sonst `index.html` aus (SPA-Fallback). Aufruf:
  `php -S 0.0.0.0:8083 dev-router.php`, gestartet aus `backend/public/`
  heraus — Port 8083 wie in CLAUDE.md vermerkt.

**Warum zwei:** Unterschiedliches Arbeitsverzeichnis beim Start
(Projektwurzel vs. `backend/public/`) macht unterschiedliche relative Pfade
nötig, und nur die Produktionsvariante braucht den SPA-Fallback auf
`index.html` sowie die explizite Auslieferung statischer Dateien — die
lokale Variante überlässt das dem eingebauten Server über die Option
`-t backend/public`.

**Entscheidung:** Beide bleiben bestehen, keine Zusammenlegung.

---

## E4 — Ausnahmeliste OHNE_REGEL_BEKANNT im Testskript (20.08.2026)

**Befund:** `tests-schulprozesse.sh` führt sechs Klassennamen, die `app.js`
setzt, für die aber keine CSS-Regel existiert und die auch nicht per
`querySelector` gesucht werden: `dash-schuljahr`, `gantt-schritt-tr`,
`hilfe-inhalt`, `neuer-schritt-titel`, `phasen-farb-btn`,
`zeitstrahl-inhalt`.

**Randbefund, hier nur vermerkt, nicht behoben:** Der Kommentar im Skript
(Zeile 123) spricht von „sieben" Klassen, die Liste selbst enthält sechs
Einträge. Diese Abweichung war nicht Teil dieses Auftrags und wird hier
nur festgehalten.

**Begründung laut Testskript selbst — die einzige auffindbare.** Weder
`CHANGELOG.md` noch eine Commit-Nachricht nennt einen der sechs Namen
einzeln; auch `git log --all --grep` über alle sechs Namen findet nichts.
Die Skript-eigene Begründung (Zeilen 119–131): Die Klassen standen schon
vor dem Umbau auf ci-css (Stand des Commits `0e6f344`, v2.3.0, 12.08.2026)
ohne Regel — vermutlich Überbleibsel aus früheren Zwischenständen. Sie zu
melden hieße, einen seit dem Umbau unveränderten Bestandszustand als neuen
Fehler auszugeben. Deshalb ausgenommen und einzeln benannt, statt die
Prüfung insgesamt zu lockern.

**Frühere Streichung als Beleg für die Vorsicht dabei:** `prozess-tabs`
stand früher ebenfalls auf dieser Liste und wurde entfernt, nachdem sich
zeigte, dass die fehlende Regel die Tab-Zeile der öffentlichen Ansicht
stapeln ließ — vermutlich behoben in Commit `6826359` („v2.4.0: Gerüst aus
ci-css, Tab-Zeile repariert", 14.08.2026; ein eigener CHANGELOG-Eintrag
dazu ist nicht auffindbar, der Commit-Zeitpunkt passt aber zur
Beschreibung). Das Skript vermerkt dazu ausdrücklich: „'war schon immer
ohne Regel' heißt nicht 'braucht keine'."

**Grund unbekannt** für jeden der sechs verbliebenen Namen einzeln — welche
konkrete Änderung sie ungenutzt zurückließ, ist weder im CHANGELOG noch in
Commit-Nachrichten dokumentiert. Nicht erfunden.

**Entscheidung:** Liste bleibt wie vorgefunden, hier nur dokumentiert statt
verändert.

---

## E5 — Reihenfolge beim Verschieben: je Phase gezählt (21.08.2026)

**Anlass:** `docs/AUFTRAG-schritte-verschieben.md` verlangte für Schritt 2
ausdrücklich eine begründete Empfehlung zu einer offenen Frage: zählt
`reihenfolge` fortlaufend über den ganzen Prozess oder je Phase neu ab 1?

**Begründung:** Es ist bereits die durchgängige Konvention in diesem
Projekt — `schritt_vorlagen.reihenfolge` und `instanz_schritte.reihenfolge`
zählen beide je Phase (`MAX(reihenfolge) WHERE phase_id = …` bzw. mit
gleicher Wirkung je Prozess), und die beiden bestehenden
Bulk-Umsortier-Endpunkte (`handleReihenfolgeVorlagen`,
`handleReihenfolgePhasen`, `backend/api/vorlagen.php`/`phasen.php`) tun das
auch. Eine fortlaufende Zählung hätte bei jedem Phasenwechsel eine
Neunummerierung *aller* nachfolgenden Schritte über Phasengrenzen hinweg
gebraucht; „je Phase" muss nur die eine betroffene Phase neu durchzählen —
genau das Muster, das `handleUpdateVorlage` für Vorlage-Phasenwechsel schon
vorlebt (ans Ende der neuen Phase anhängen).

**Entscheidung:** `instanz_reihenfolge` (vorlage-basierte Schritte) und
`reihenfolge` (eigene Schritte) zählen je Phase, beginnend bei 1. Der neue
Endpunkt `POST /api/prozesse/{id}/schritte/reihenfolge`
(`handleSchritteReihenfolge`, `backend/api/schritte.php`) bekommt bei jedem
Aufruf die komplette neue Reihenfolge EINER Phase (voller Ersatz), analog zu
den beiden bestehenden Endpunkten.

---

## E6 — Phasenwechsel per Freitext-Überschreibung statt Fremdschlüssel (21.08.2026)

**Anlass:** Ein vorlage-basierter Schritt (`schritt_instanzen`) hängt fest an
einer `phasen`-Zeile (`schritt_vorlagen.phase_id`) – global, geteilt von
allen Prozessen, die dieselbe Vorlage nutzen. Ein eigener Schritt
(`instanz_schritte`) kennt „Phase" dagegen nur als freies Namens-/Farbfeld
ohne eigene Tabelle. Rückfrage im Chat vor dieser Entscheidung: Soll
Drag-and-drop zwischen beiden Welten wechseln können?

**Antwort des Auftraggebers:** „Prozess verwalten" ist eine Kopie der
Vorlage, mit der Verantwortliche frei arbeiten können sollen — eine
Verschiebung dort darf niemals auf die Vorlage zurückwirken, und der Prozess
kann später archiviert und wiederverwendet werden. Das schließt beide Welten
gleichberechtigt ein: ein Wechsel muss in beide Richtungen möglich sein.

**Verworfene Alternative:** eine neue Spalte `instanz_phase_id INTEGER
REFERENCES phasen(id)`, analog zu `instanz_reihenfolge`. Verworfen, weil
eigene Phasen keine `phasen`-Zeile haben — eine Fremdschlüssel-Spalte hätte
den Wechsel in eine eigene Phase gar nicht abbilden können, ohne für jede
eigene Phase nachträglich eine `phasen`-Zeile anzulegen (ein deutlich
größerer Umbau, der `phasen` von einer projektweiten in eine
prozessspezifische Tabelle verwandelt hätte).

**Entscheidung:** Migration 006 fügt `schritt_instanzen.instanz_phase_name`
und `instanz_phase_farbe` hinzu (Freitext, wie bei `instanz_schritte` schon
immer). `NULL` (Standard) heißt: der Schritt folgt weiter seiner
Vorlage-Phase, auch über spätere Umbenennungen. Gesetzt heißt: der Schritt
wurde ausdrücklich verschoben und folgt der Vorlage-Phase ab da nicht mehr —
er „lebt" jetzt unter dem Namen/der Farbe der Zielphase, unabhängig davon ob
diese eine echte `phasen`-Zeile hat oder eine rein eigene ist. Ein reines
Umsortieren *innerhalb* der angestammten Phase setzt diese Felder nicht
(siehe `handleSchritteReihenfolge`, Vergleich der aktuellen mit der
Ziel-Phase vor jedem Schreibzugriff) — ein Schritt verliert die Kopplung an
seine Vorlage-Phase also nur, wenn er tatsächlich verschoben wurde, nicht
schon durch bloßes Umsortieren.

**Nebenwirkung, bewusst in Kauf genommen:** Ein derart verschobener Schritt
liefert `phase_id: null` (`handleListSchritte`, CASE-Ausdruck) – er ist nicht
mehr eindeutig einer `phasen`-Zeile zuordenbar, sobald er den Verbund
verlassen hat. Bestehender Code, der `phase_id` nutzt (`getPhaseId()` in
`app.js`, für die „↺ Phase"-Zurücksetzen-Knöpfe und `instanz-phasen`-Aufrufe),
sucht ohnehin unter allen Schritten derselben angezeigten Phase nach einem
mit gesetzter `phase_id` — ein verschobener Schritt wird dabei einfach
übersprungen, ein noch nicht verschobener liefert weiter die richtige ID.
Sind irgendwann *alle* Schritte einer ehemaligen Vorlage-Phase weggezogen,
verhält sich der (jetzt leere) Rest wie eine eigene Phase — folgerichtig,
nicht als Fehler zu werten.

---

## E7 — Gleichzeitige Bearbeitung: kein Konflikt-Schutz, wie beim bestehenden Muster (21.08.2026)

**Anlass:** `docs/AUFTRAG-schritte-verschieben.md`, Schritt 3, verlangt eine
ausdrückliche Antwort auf die Frage, was passiert, wenn zwei Personen
denselben Prozess gleichzeitig umsortieren — auch wenn die Antwort
„vernachlässigbar" lautet.

**Bestandsaufnahme:** Die beiden bereits bestehenden Bulk-Umsortier-Endpunkte
dieses Projekts (`handleReihenfolgeVorlagen`, `handleReihenfolgePhasen`)
prüfen keinerlei Version oder Zeitstempel — reines Last-Write-Wins, wer
zuletzt speichert gewinnt vollständig.

**Entscheidung:** `handleSchritteReihenfolge` macht es genauso — kein
Versions-/Zeitstempel-Abgleich. Begründung: kleiner, den Beteiligten
bekannter Nutzerkreis (Kolleginnen und Kollegen, keine Fremden), in aller
Regel genau eine verantwortliche Person je Prozess-Phase, die gerade
umsortiert. Ein echter Zeitkonflikt zweier gleichzeitiger Verschiebungen
derselben Phase gilt als vernachlässigbar selten und wird explizit in Kauf
genommen — **ausdrücklich**, nicht stillschweigend.

Ein struktureller Schutz bleibt trotzdem bestehen, weil er sich aus der
Robustheitsanforderung „kein Schritt darf verlorengehen" ergibt, nicht aus
Konflikt-Erkennung: Jeder Eintrag im Aufruf muss existieren und zu diesem
Prozess gehören, sonst wird die GESAMTE Anfrage abgelehnt (409), bevor
irgendetwas geschrieben wird. Das verhindert, dass ein Schritt verschwindet,
weil er zwischen Laden und Speichern durch eine andere Aktion (z. B.
Löschen) aus der Liste verschwunden ist — es ist aber kein Schutz gegen zwei
gleichzeitige, beide für sich genommen gültige Umsortierungen derselben
Phase.

---

## E8 — Tastaturbedienung: Pfeilschalter + Auswahl statt Drag-in-Nachbarphase (21.08.2026)

**Anlass:** `docs/AUFTRAG-schritte-verschieben.md`, Schritt 3, verlangt
Tastaturbedienbarkeit und schlägt „zwei Pfeilschalter je Schritt" vor, „das
Verschieben in eine andere Phase braucht dann eine Auswahl."

**Entscheidung, wie genau:** Die Pfeilschalter (↑/↓) verschieben nur
innerhalb der eigenen Phase um eine Position (am Rand deaktiviert). Der
Phasenwechsel läuft über ein `<select>` je Schritt, das alle *aktuell
sichtbaren* Phasen dieses Prozesses als Ziel anbietet (ermittelt aus der
bereits geladenen Schrittliste, nicht aus einer zusätzlichen Abfrage) und
den Schritt beim Auswählen ans Ende der Zielphase anhängt. Drag-and-drop
erzeugt dabei bewusst **keine neue Phase** — wer eine neue Phase braucht,
nutzt weiter das bestehende Formular „Neue Phase anlegen". Diese Beschränkung
gilt gleichermaßen für Maus-Drag-and-drop: es lässt sich nur auf bereits
gerenderte Phasenblöcke ziehen, also ebenfalls nur auf bestehende Phasen.

**Warum keine „leere Phase" als Ziel vorkommen kann:** Eine `phasen`-Zeile
ohne zugehörige Schritt-Instanzen in diesem Prozess wird hier gar nicht erst
als Block gerendert, eine eigene Phase existiert nur, solange sie
mindestens einen Schritt hat. Der vom Auftrag geforderte Testfall „in eine
leere Phase" ist deshalb ein Fall der *reinen Funktion* `verschiebeSchritt()`
(offline, mit synthetischen Daten in `tests/schritt-verschieben.test.js`
geprüft), nicht ein Bedienfall in der Oberfläche.

---

## E9 — Berührungsbedienung: kein echtes Ziehen, aber vollständig über dieselben Knöpfe bedienbar (21.08.2026)

**Anlass:** `docs/AUFTRAG-schritte-verschieben.md`, Schritt 3: „Wenn das
[Berührungsbedienung] den Rahmen sprengt, sag es."

**Befund:** Native HTML5-Drag-and-drop-Ereignisse (`dragstart`/`dragover`/
`drop`), wie sie hier für Maus-Bedienung eingesetzt werden (gleiches Muster
wie im bestehenden Vorlagen-Editor, `renderPhasenBlock`/`renderVorlagenZeile`
in `app.js`), lösen auf Tablets nicht zuverlässig aus. Eine eigene,
Pointer-Events-basierte Zieh-Geste dafür ist nicht umgesetzt — das hätte den
Rahmen dieses Auftrags gesprengt.

**Warum das kein Bedienbarkeitsverlust ist:** Die Pfeilschalter und die
Zielphasen-Auswahl aus E8 sind gewöhnliche `<button>`- und `<select>`-
Elemente – auf einem Tablet per Fingertipp genauso bedienbar wie eine
Maus-Bedienung. Tablet-Nutzerinnen und -Nutzer verlieren also keine
Funktion, nur die Zieh-Geste selbst bleibt Maus-only.

**Entscheidung:** So ausgeliefert. Eine echte Touch-Zieh-Geste bleibt
offen für einen späteren, eigenen Auftrag, falls sich das als tatsächliches
Bedürfnis herausstellt.

---

## E10 — "Prozesse verwalten" gruppiert nach angezeigter Phase, nicht nach Schritt-Herkunft (22.08.2026)

**Anlass:** Unmittelbar nach Auslieferung von E5–E9 gemeldet: Wurde ein
eigener Schritt in eine Vorlage-Phase verschoben, erschien eine zweite,
scheinbar neue Phase mit demselben Namen statt sich der bestehenden Phase
anzuschließen – der verschobene Schritt blieb dadurch von den übrigen
Schritten seiner Phase getrennt.

**Ursache:** `renderInstanzSchrittVerwaltung()` (Verwaltungsseite
„Prozesse verwalten") baute Vorlage- und eigene Schritte in zwei strikt nach
`s.quelle` getrennten Abschnitten auf – eine Aufteilung, die so lange
unproblematisch war, wie ein Schritt seine Herkunft nie wechseln konnte.
E6 hat genau das ermöglicht (`instanz_phase_name`/`-farbe`), ohne dass die
Darstellung in „Prozesse verwalten" mitgezogen wurde – die drei anderen
Ansichten (Checkliste, Zeitstrahl, Gantt) gruppieren dagegen schon seit dem
Fund vom 21.08.2026 über `gruppiereNachPhase()` rein nach dem angezeigten
Phasennamen und waren von diesem Fehler nicht betroffen.

**Entscheidung:** „Prozesse verwalten" gruppiert jetzt ebenfalls über
`gruppiereNachPhase()`, in einem einzigen Durchlauf über beide Herkünfte
gemeinsam. Eine Phasengruppe gilt als „echte" Vorlage-Phase, sobald
*irgendein* ihrer aktuellen Mitglieder eine `phase_id` trägt (nicht mehr:
sobald die *zuerst* einsortierte Zeile Vorlage-Herkunft hat) – das war schon
vor diesem Fund die robustere, aber ungenutzte Variante.

**Folgeentscheidung – Umbenennen/Umfärben nimmt abgekoppelte Mitglieder mit:**
Eine Phase umzubenennen betraf bisher nur Schritte derselben Herkunft wie
die Phase selbst. Jetzt läuft der Kern weiter über `instanz_phasen` (damit
nicht verschobene Vorlage-Schritte automatisch künftigen Umbenennungen
folgen), zusätzlich werden aber alle bereits „abgekoppelten" Mitglieder
(verschobene Vorlage-Schritte, alle eigenen Schritte – erkennbar an
fehlender `phase_id`) explizit über den Reihenfolge-Endpunkt mitgenommen.
Ohne das bliebe ein verschobener Schritt bei jeder weiteren Umbenennung
seiner neuen Phase wieder zurück.

**Folgeentscheidung – "🗑 Phase" löscht keine Vorlage-Schritte mehr:**
Enthält eine rein eigene Phase durch eine Verschiebung jetzt auch
Vorlage-Schritte, würde ein pauschales Löschen aller Mitglieder Daten
zerstören, die nicht diesem einen Prozess gehören (bzw. bei
prozesseigenen Vorlage-Schritten zumindest nicht ohne Weiteres
wiederherstellbar). Entscheidung: „🗑 Phase" löscht weiterhin nur die
eigenen Schritte endgültig, hierher verschobene Vorlage-Schritte werden
stattdessen nur deaktiviert (ausgeblendet, wiederherstellbar über
„↩ reaktivieren" in ihrer neuen Phase) – mit entsprechendem Hinweis in der
Rückfrage.

**Nicht angefasst, bewusst offen gelassen:** „↺ Alles" setzt weiterhin nur
Umbenennungen und Ausblendungen der *ursprünglichen* Vorlage-Phase zurück,
nicht `instanz_phase_name`/`-farbe` – ein Schritt, der aus dieser Phase
herausgezogen wurde, kehrt durch „↺ Alles" also nicht automatisch dorthin
zurück. Ob das gewünscht ist, wurde nicht erfragt; hier nur als bekannte
Lücke vermerkt, nicht als Entscheidung.
