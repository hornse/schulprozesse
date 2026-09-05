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

Die Datei liegt unter `data/app.sqlite` und steht nicht in git.

---

## WebUntis-Konfiguration

| Was | Wert |
|---|---|
| `base_url` | `https://frg-dusseldorf.webuntis.com` |
| `school` | `frg-dusseldorf` |
| `client` | `SchuljahreswechselApp` |
| `allowed_person_types` | `[2, 16]` (kein Schüler-Login) |

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

## Die eigenen Werte

### Der Sitzungsname ist `swj_session`
```php
// In config/config.php:
'session' => [
    'name'            => 'swj_session',
    'lifetime_minutes' => 480,
],
```

### CSRF-Schutz per `X-Requested-With`
Alle schreibenden Requests müssen diesen Header enthalten:
```
X-Requested-With: SchuljahreswechselApp
```
Frontend schickt ihn automatisch mit. Bei direkten curl-Tests explizit setzen.

### Wo der JSESSIONID-Cookie gehalten wird
In `backend/src/Auth/WebUntisAuth.php` — dort wird er aus `Set-Cookie`
gelesen und für die Folgeaufrufe behalten.

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

**Die Farbwert-Ausnahme wird hier in Anspruch genommen:** Rollen- und
Sichtbarkeitsmarken für öffentlich/privat/verantwortlich/mitarbeitend
stehen im `:root`-Block von `style.css`, mit Kommentar zum Kontrast.
Begründung: `docs/ENTSCHEIDUNGEN.md`, E2. `tests-schulprozesse.sh` prüft
deshalb nur „außerhalb des `:root`-Blocks", nicht „außerhalb von
`ci-tokens.css`".

**Wie hier auf erfundene Klassennamen geprüft wird:**
`tests-schulprozesse.sh` sieht im Abschnitt „Gerüst" für jede von
`app.js` gesetzte Klasse nach, ob eine CSS-Regel existiert — mit einer
benannten Ausnahmeliste für bekannte Altlasten
(`docs/ENTSCHEIDUNGEN.md`, E4).

---

## Der Zustand der eigenen Skripte

`tests-schulprozesse.sh` setzt `export LC_ALL=C` in Zeile 10. Es läuft
ohne `-e` (nur `set -uo pipefail`); `deploy.sh` läuft mit `set -e`, aber
ohne `grep`.

`deploy.sh` erreicht den Push auch dann, wenn es nichts zu committen
gibt — seit dem 17.08.2026, Commit `753709a`. Anlass war ein
Cache-Busting-Ausdruck, der nur Ziffern erfasste und das `?v=DEV` im
Auslieferungszustand nicht traf: Das `sed` lief ins Leere, es gab nichts
zu committen, und unter `set -e` brach der Commit-Schritt vor dem Push
ab.

Die Prüfung „Gruppierung" entstand am 20.08.2026 gegen die ungruppierte,
fehlerhafte Fassung von `gruppiereNachPhase()` — erst rot, dann gegen die
reparierte grün.

---

## Vor jeder Auslieferung wirklich prüfen

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
