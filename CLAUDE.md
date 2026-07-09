# CLAUDE.md – Schulprozesse

Diese Datei gibt Claude (und anderen KI-Assistenten) sofortigen Kontext
über das Projekt, die Infrastruktur und alle bekannten Fallstricke.
**Bitte zu Beginn jeder Session lesen.**

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
│   ├── public/
│   │   ├── dev-router.php      ← PHP Router
│   │   ├── api-router.php      ← API-Dispatcher
│   │   ├── index.html          ← SPA
│   │   ├── js/app.js
│   │   └── css/style.css
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
├── docs/
│   ├── INSTALL.md
│   └── BENUTZERHANDBUCH.md
├── deploy/
│   └── uberspace.md
├── deploy.sh
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
