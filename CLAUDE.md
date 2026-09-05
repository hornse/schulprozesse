# CLAUDE.md – Schulprozesse

Diese Datei gibt Claude (und anderen KI-Assistenten) sofortigen Kontext
über das Projekt, die Infrastruktur und alle bekannten Fallstricke.
**Bitte zu Beginn jeder Session lesen.**

Ergänzend:
- Entscheidungen mit Begründung, chronologisch: @docs/ENTSCHEIDUNGEN.md
- Regeln der Reihe: @REIHENREGELN.md
- Fallstricke PHP/Router/WebUntis: @FALLSTRICKE.md

---

## Projektkontext

| Was | Wert |
|---|---|
| **App** | Schulprozesse – Verwaltung schulischer Prozesse und Workflows |
| **Schule** | Friedrich-Rückert-Gymnasium Düsseldorf |
| **Entwickler** | Sebastian Horn (IT-Administrator und Lehrer) |
| **Server** | `hornse@halimede.uberspace.de` |
| **Work-Tree** | `/var/www/virtual/hornse/schulprozesse-src` |
| **Bare Repo** | `/home/hornse/repos/schulprozesse.git` |
| **Domain** | `prozesse.hornse.de` |
| **Port** | `8083` |
| **Datenbank** | SQLite (`data/app.sqlite`) |
| **GitHub** | `hornse/schulprozesse` |
| **Deploy** | `./deploy.sh "commit message"` |
| **Lizenz** | GPL-3.0-or-later |
| **Sprache** | Deutsch – Bezeichner, Kommentare, Oberfläche, Commits |

---

## Stack

| Schicht | Technologie |
|---|---|
| Frontend | Vanilla JS, HTML, CSS – kein Build-Schritt |
| Backend | PHP 8.1+, Namespace `App\`, PSR-4-Autoloader |
| Datenbank | **SQLite** (nicht MariaDB!) |
| Auth | WebUntis JSON-RPC (nur Lehrer, kein lokaler Login) |
| Hosting | Uberspace 7, PHP built-in Server, supervisord |

---

## Dateistruktur

```
schulprozesse/
├── backend/
│   ├── public/                 ← Docroot
│   │   ├── dev-router.php      ← Produktions-Router (Uberspace/supervisord, Port 8083)
│   │   ├── api-router.php      ← API-Dispatcher
│   │   ├── index.html          ← SPA
│   │   ├── js/app.js
│   │   ├── css/style.css
│   │   └── vendor/ci-css/      ← Modul hornse/ci-css, vendored (siehe E1)
│   ├── src/
│   │   ├── Auth/
│   │   │   └── WebUntisAuth.php
│   │   ├── Database.php
│   │   ├── Guard.php
│   │   ├── Response.php
│   │   └── Session.php
│   ├── api/                    ← API-Handler (je Ressource eine Datei)
│   │   ├── auth.php
│   │   ├── prozesse.php
│   │   ├── phasen.php
│   │   └── ...
│   └── bootstrap.php
├── config/
│   ├── config.example.php
│   └── config.php              ← NICHT in git
├── migrations/
│   ├── 001_init.sql
│   ├── 002_seed_schritte.sql
│   └── ...
├── data/                       ← SQLite-Datei (NICHT in git)
│   └── app.sqlite
├── tests/
│   └── gruppierung.test.js     ← per Node, eingebunden in tests-schulprozesse.sh
├── docs/
│   ├── ENTSCHEIDUNGEN.md
│   ├── INSTALL.md
│   └── BENUTZERHANDBUCH.md
├── deploy/
│   └── uberspace.md
├── dev-router.php              ← NUR lokal, `php -S ... dev-router.php` an der Projektwurzel (siehe E3)
├── deploy.sh
├── tests-schulprozesse.sh
├── CHANGELOG.md
├── README.md
└── LICENSE
```

---

## Wichtiger Unterschied zu Projektstunden

**Schulprozesse nutzt SQLite, nicht MariaDB!**

```php
// Database.php – SQLite-Verbindung
$pdo = new PDO('sqlite:' . $config['db']['sqlite_path']);
```

Alle SQL-Syntax muss SQLite-kompatibel sein:
- Kein `AUTO_INCREMENT` → `INTEGER PRIMARY KEY` (SQLite-Autoincrement)
- Kein `ENGINE=InnoDB` / `CHARSET=utf8mb4`
- Kein `NOW()` → `datetime('now')`
- Kein `DATE_SUB()` → `datetime('now', '-15 minutes')`

---

## WebUntis-Konfiguration

| Was | Wert |
|---|---|
| `base_url` | `https://frg-dusseldorf.webuntis.com` |
| `school` | `frg-dusseldorf` |
| `client` | `SchuljahreswechselApp` |
| `allowed_person_types` | `[2, 16]` (kein Schüler-Login) |
| personType 2 | Lehrkraft |
| personType 16 | WebUntis-Admin, personId = **-1** |

**Kein lokaler E-Mail/Passwort-Login** – nur WebUntis.

---

## Session-Management

Schulprozesse nutzt eine eigene `Session`-Klasse (`backend/src/Session.php`):

```php
// Im Router ganz oben:
Session::start($config);  // setzt session_name + cookie params

// Login:
Session::login($user);    // session_regenerate_id + $_SESSION['user']

// Aktueller User:
$user = Session::currentUser(); // null wenn nicht eingeloggt
```

**Kein `benutzer_id=0`-Problem** – Schulprozesse hat keinen WebUntis-only-Modus
ohne DB-Eintrag. Alle User sind in der DB.

---

## Architektur-Unterschied zu Projektstunden

| Aspekt | Projektstunden | Schulprozesse |
|---|---|---|
| DB | MariaDB, PDO, defines | SQLite, PDO, Array-Config |
| API | Alles in `api/index.php` | Je Ressource eigene Datei |
| Namespace | Kein Namespace | `App\` mit Autoloader |
| Auth | WebUntis + lokal | Nur WebUntis |
| Session | Funktionen in config.php | Klasse `App\Session` |
| Router | `backend/router.php` | `backend/public/dev-router.php` |

---

## Kritische Regeln (IMMER beachten)

### 1. SQLite-Syntax verwenden
```sql
-- ✗ FALSCH (MariaDB)
INSERT INTO log (zeitpunkt) VALUES (NOW());
-- ✓ RICHTIG (SQLite)
INSERT INTO log (zeitpunkt) VALUES (datetime('now'));
```

### 2. Session-Name ist 'swj_session'
```php
// In config/config.php:
'session' => [
    'name'            => 'swj_session',
    'lifetime_minutes' => 480,
],
```

### 3. CSRF-Schutz per X-Requested-With Header
Alle schreibenden Requests müssen diesen Header enthalten:
```
X-Requested-With: SchuljahreswechselApp
```
Frontend schickt ihn automatisch mit. Bei direkten curl-Tests explizit setzen.

### 4. WebUntis JSESSIONID-Cookie
Nach `authenticate` den Cookie aus `Set-Cookie` speichern und bei
Folgeaufrufen mitschicken. → In `WebUntisAuth.php` implementiert.

### 5. WebUntis-Admin (personType 16) hat personId = -1
Kein Eintrag in `getTeachers()`. Name aus DB per Kürzel nachschlagen.

---

## Häufige Debug-Befehle

```bash
# Server-Log
supervisorctl tail schulprozesse stderr | tail -20

# SQLite direkt abfragen
sqlite3 /var/www/virtual/hornse/schulprozesse-src/data/app.sqlite \
  "SELECT * FROM benutzer LIMIT 5;"

# API testen
curl -s https://prozesse.hornse.de/api/auth/me \
  -H "Cookie: swj_session=SESSIONID"
```

---

## Design – ci-css

Farben, Radien, Abstände und das Gerüst (`ci-huelle`, `ci-huelle--kopf`)
kommen aus dem Modul `hornse/ci-css`, vendored unter
`backend/public/vendor/ci-css/` – abweichend von den übrigen fünf
Projekten der Reihe, die es unter `frontend/vendor/ci-css/` führen.
Grund: `backend/public/` ist hier der Docroot, `frontend/` gibt es nicht
(siehe `docs/ENTSCHEIDUNGEN.md`, E1). Stand: siehe Kopfvermerk in
`backend/public/vendor/ci-css/ci-tokens.css`.

**Vendored heißt kopiert, nicht abgetippt.** Änderungen am Modul gehören
ins Modul-Repo `hornse/ci-css` und werden von dort zurückkopiert, nie
umgekehrt. Jede vendorte Datei trägt einen Kopfvermerk der Form
`VENDORED aus hornse/ci-css vX.Y.Z – dort ändern, hierher kopieren!`.

**Kein Farbwert außerhalb von `ci-tokens.css`** – mit einer Ausnahme:
projekteigene Kategorienpaletten, die nur dieses Projekt braucht (Rollen-
und Sichtbarkeitsmarken für öffentlich/privat/verantwortlich/mitarbeitend),
stehen im `:root`-Block von `style.css`, mit Kommentar zum Kontrast.
Begründung: `docs/ENTSCHEIDUNGEN.md`, E2. `tests-schulprozesse.sh` prüft
entsprechend nur „außerhalb des `:root`-Blocks", nicht „außerhalb von
`ci-tokens.css`".

**Keine erfundenen Modulklassennamen.** Ein geratener `ci-`Klassenname
sieht richtig aus und hat einfach keine Regel – in `fachkonferenzen`
verschwanden dadurch einmal alle Karten. `tests-schulprozesse.sh` prüft
deshalb im Abschnitt „Gerüst" für jede von `app.js` gesetzte Klasse, ob
eine CSS-Regel existiert, mit einer benannten Ausnahmeliste für bekannte
Altlasten (`docs/ENTSCHEIDUNGEN.md`, E4).

---

## Regeln der Reihe

Gelten projektübergreifend für alle Anwendungen der Reihe (sprechtag,
fachkonferenzen, projektstunden, schulprozesse, signage, lernzeiten):

- **`export LC_ALL=C`** in jedem Shell-Skript mit Zahlenvergleichen.
  `tests-schulprozesse.sh` setzt es bereits (Zeile 10) – ohne das kann ein
  Zahlenvergleich je nach Locale unterschiedlich ausfallen.
- **`grep` mit Exit-Code 1 unter `set -e`** bricht ein Skript ab, auch wenn
  kein Treffer der Erfolgsfall ist. `tests-schulprozesse.sh` läuft bisher
  ohne `-e` (nur `set -uo pipefail`), `deploy.sh` mit `set -e`, aber ohne
  `grep`. Kommt künftig `grep` in ein `set -e`-Skript, braucht ein
  erwarteter Nulltreffer ein `|| true` – aber nur für den echten
  Erfolgsfall, nie pauschal für einen Werkzeugfehler.
- **Jede neue Prüfung braucht eine Gegenprobe.** Den Fehlerfall künstlich
  herstellen, die Prüfung muss anschlagen – sonst weiß niemand, ob sie
  überhaupt etwas prüft. So entstand die Prüfung „Gruppierung" in
  `tests-schulprozesse.sh` (20.08.2026): erst gegen die ungruppierte,
  fehlerhafte Fassung von `gruppiereNachPhase()` laufen gelassen (rot),
  erst danach gegen die reparierte (grün).
- **Wird eine Prüfung erweitert, muss die Prüfungszahl um den erwarteten
  Betrag steigen.** Bleibt sie gleich oder steigt um weniger, ist die
  Erweiterung nicht wirksam geworden.
- **`deploy.sh` muss den Push auch dann erreichen, wenn es nichts zu
  committen gibt.** Muster: `git add -A`, dann
  `if ! git diff --cached --quiet; then git commit …`. `deploy.sh` hat das
  seit dem 17.08.2026 (Commit `753709a`) – Anlass war ein
  Cache-Busting-Ausdruck, der nur Ziffern erfasste und das `?v=DEV` im
  Auslieferungszustand nicht traf: das `sed` lief ins Leere, es gab nichts
  zu committen, und unter `set -e` brach der Commit-Schritt vor dem Push
  ab.

---

## Vor jeder Auslieferung wirklich prüfen

Nicht behaupten, sondern ausführen:

```bash
./tests-schulprozesse.sh
```

Deckt ab: `app.js`-Syntax, die Phasen-Gruppierung
(`tests/gruppierung.test.js`), Datenschutz (keine externen
Schriften/Ressourcen), keine Rohfarben außerhalb des `:root`-Blocks,
vollständige ci-Tokens, Einbindung von Modul und Kopfleiste, keine
doppelten Shell-Regeln, alle von `app.js` gesetzten Klassen haben eine
Regel, Barrierefreiheits-Nachrüstungen, `.gitignore`.

Kein separater Integrationstest gegen eine laufende Instanz und kein Lint
für PHP sind bisher eingerichtet.

---

## Aktueller Stand

**Fertig:**
- WebUntis-Auth für Lehrkräfte und Admins
- Prozesse und Phasen verwalten
- Schuljahreswechsel-Workflows
- Rollen und Benutzer

**Unterschied zu Projektstunden:**
- Keine Schüler
- Keine Bewertungen
- Fokus auf Verwaltungsprozesse
