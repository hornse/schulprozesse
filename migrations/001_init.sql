-- ============================================================================
-- Migration 001: Grundschema (Prozess-Modell)
-- ============================================================================
-- Erweiterung gegenüber der schuljahreswechsel-Instanz:
--   - schuljahre → prozesse (mit oeffentlich-Flag)
--   - Neue Tabelle prozess_teilnehmer (wer darf welchen Prozess sehen/bearbeiten)
--   - benutzer_rollen.rolle: 'admin' | 'mitglied' (unverändert)
--   - Prozess-Teilnehmer-Rolle: 'verantwortlich' | 'mitarbeitend'
-- ============================================================================

PRAGMA foreign_keys = ON;

-- Ein Prozess ist ein benannter Ablauf mit einer Checkliste.
-- oeffentlich = 1: erscheint im öffentlichen Dashboard für alle
-- oeffentlich = 0: nur für zugewiesene Teilnehmer sichtbar
CREATE TABLE IF NOT EXISTS prozesse (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    label        TEXT NOT NULL,
    beschreibung TEXT,
    aktiv        INTEGER NOT NULL DEFAULT 0,
    oeffentlich  INTEGER NOT NULL DEFAULT 1,
    erstellt_am  TEXT NOT NULL DEFAULT (datetime('now')),
    erstellt_von TEXT
);

-- Wer darf an einem Prozess teilnehmen?
-- rolle: 'verantwortlich' = kann Teilnehmer verwalten + öffentlich/privat schalten
--        'mitarbeitend'   = kann Häkchen setzen, Kommentare schreiben
CREATE TABLE IF NOT EXISTS prozess_teilnehmer (
    prozess_id    INTEGER NOT NULL REFERENCES prozesse(id) ON DELETE CASCADE,
    webuntis_user TEXT NOT NULL,
    rolle         TEXT NOT NULL DEFAULT 'mitarbeitend'
                  CHECK (rolle IN ('verantwortlich', 'mitarbeitend')),
    PRIMARY KEY (prozess_id, webuntis_user)
);

-- Schritt-Vorlagen (phasenbasiert, wiederverwendbar)
CREATE TABLE IF NOT EXISTS schritt_vorlagen (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    phase_id     INTEGER NOT NULL REFERENCES phasen(id),
    reihenfolge  INTEGER NOT NULL,
    titel        TEXT NOT NULL,
    beschreibung TEXT,
    kann_parallel INTEGER NOT NULL DEFAULT 0,
    aktiv        INTEGER NOT NULL DEFAULT 1
);

-- Phasen (eigene Tabelle seit Migration 003 der alten Instanz)
CREATE TABLE IF NOT EXISTS phasen (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    farbe       TEXT NOT NULL DEFAULT '#5B6FA8',
    reihenfolge INTEGER NOT NULL DEFAULT 0
);

-- Instanzen: eine Zeile pro (Prozess, Schritt)
CREATE TABLE IF NOT EXISTS schritt_instanzen (
    id                       INTEGER PRIMARY KEY AUTOINCREMENT,
    prozess_id               INTEGER NOT NULL REFERENCES prozesse(id) ON DELETE CASCADE,
    vorlage_id               INTEGER NOT NULL REFERENCES schritt_vorlagen(id),
    erledigt                 INTEGER NOT NULL DEFAULT 0,
    verantwortlich_user      TEXT,
    verantwortlich_anzeigename TEXT,
    start_datum              TEXT,
    geplantes_datum          TEXT,
    erledigt_am              TEXT,
    erledigt_von             TEXT,
    kommentar                TEXT,
    kann_parallel            INTEGER NOT NULL DEFAULT 0,
    UNIQUE (prozess_id, vorlage_id)
);

-- Zugriffsverwaltung (unverändert)
CREATE TABLE IF NOT EXISTS benutzer_rollen (
    webuntis_user TEXT PRIMARY KEY,
    anzeigename   TEXT,
    rolle         TEXT NOT NULL DEFAULT 'mitglied' CHECK (rolle IN ('admin', 'mitglied')),
    passwort_hash TEXT,
    erstellt_am   TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Login-Protokoll
CREATE TABLE IF NOT EXISTS login_log (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    webuntis_user TEXT NOT NULL,
    erfolgreich   INTEGER NOT NULL,
    grund         TEXT,
    ip            TEXT,
    zeitpunkt     TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Aktivitätsprotokoll
CREATE TABLE IF NOT EXISTS aktivitaeten (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    prozess_id   INTEGER REFERENCES prozesse(id) ON DELETE CASCADE,
    vorlage_id   INTEGER REFERENCES schritt_vorlagen(id) ON DELETE SET NULL,
    schritt_titel TEXT,
    ereignis     TEXT NOT NULL,
    wert_neu     TEXT,
    benutzer     TEXT,
    anzeigename  TEXT,
    zeitstempel  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Vorlagen-Snapshots
CREATE TABLE IF NOT EXISTS vorlagen_sets (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    beschreibung TEXT,
    erstellt_am  TEXT NOT NULL DEFAULT (datetime('now')),
    erstellt_von TEXT
);

CREATE TABLE IF NOT EXISTS vorlagen_set_phasen (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    set_id      INTEGER NOT NULL REFERENCES vorlagen_sets(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    farbe       TEXT NOT NULL DEFAULT '#5B6FA8',
    reihenfolge INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS vorlagen_set_schritte (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    set_id        INTEGER NOT NULL REFERENCES vorlagen_sets(id) ON DELETE CASCADE,
    set_phase_id  INTEGER NOT NULL REFERENCES vorlagen_set_phasen(id) ON DELETE CASCADE,
    reihenfolge   INTEGER NOT NULL,
    titel         TEXT NOT NULL,
    beschreibung  TEXT,
    kann_parallel INTEGER NOT NULL DEFAULT 0
);

-- Indizes
CREATE INDEX IF NOT EXISTS idx_instanzen_prozess     ON schritt_instanzen(prozess_id);
CREATE INDEX IF NOT EXISTS idx_vorlagen_phase        ON schritt_vorlagen(phase_id);
CREATE INDEX IF NOT EXISTS idx_teilnehmer_prozess    ON prozess_teilnehmer(prozess_id);
CREATE INDEX IF NOT EXISTS idx_teilnehmer_user       ON prozess_teilnehmer(webuntis_user);
CREATE INDEX IF NOT EXISTS idx_aktivitaeten_prozess  ON aktivitaeten(prozess_id);
CREATE INDEX IF NOT EXISTS idx_login_log_user_zeit   ON login_log(webuntis_user, zeitpunkt);
