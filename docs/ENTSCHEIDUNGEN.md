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
