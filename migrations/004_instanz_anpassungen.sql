-- ============================================================================
-- Migration 004: Prozessspezifische Instanz-Anpassungen
-- ============================================================================
-- Ermöglicht es Verantwortlichen, Schritte ihrer Prozess-Instanz anzupassen
-- ohne die globale Vorlage zu berühren.
--
-- instanz_titel        – überschreibt schritt_vorlagen.titel wenn gesetzt
-- instanz_reihenfolge  – überschreibt die Vorlage-Reihenfolge wenn gesetzt
-- deaktiviert          – blendet den Schritt in diesem Prozess aus (0/1)
--
-- Bestehende Instanzen werden nicht verändert (alle Felder NULL/0 = Standardverhalten).
-- ============================================================================

PRAGMA foreign_keys = ON;

ALTER TABLE schritt_instanzen ADD COLUMN instanz_titel       TEXT;
ALTER TABLE schritt_instanzen ADD COLUMN instanz_reihenfolge INTEGER;
ALTER TABLE schritt_instanzen ADD COLUMN deaktiviert         INTEGER NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_instanzen_deaktiviert
  ON schritt_instanzen(prozess_id, deaktiviert);

-- Neue Tabelle für prozessspezifische Schritte ohne Vorlage
-- (eigene Schritte die ein Verantwortlicher für seinen Prozess anlegt)
CREATE TABLE IF NOT EXISTS instanz_schritte (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    prozess_id   INTEGER NOT NULL REFERENCES prozesse(id) ON DELETE CASCADE,
    phase_name   TEXT NOT NULL,
    phase_farbe  TEXT NOT NULL DEFAULT '#5B6FA8',
    reihenfolge  INTEGER NOT NULL DEFAULT 0,
    titel        TEXT NOT NULL,
    beschreibung TEXT,
    erledigt     INTEGER NOT NULL DEFAULT 0,
    erledigt_am  TEXT,
    erledigt_von TEXT,
    kommentar    TEXT,
    deaktiviert  INTEGER NOT NULL DEFAULT 0,
    erstellt_von TEXT,
    erstellt_am  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_instanz_schritte_prozess
  ON instanz_schritte(prozess_id, deaktiviert);
