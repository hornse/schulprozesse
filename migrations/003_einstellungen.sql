-- ============================================================================
-- Migration 003: Einstellungen-Tabelle
-- ============================================================================
-- Speichert schulspezifische Konfiguration als Key-Value-Paare.
-- Bewusst einfaches Schema: ein Schlüssel, ein Wert (TEXT), ein Zeitstempel.
-- Kein separates Logging – Änderungen landen im Aktivitätsprotokoll.
--
-- Definierte Schlüssel:
--   schulname        – Anzeigename der Schule (max. 80 Zeichen)
--   app_titel        – Titel der App (max. 40 Zeichen, Default "Schulprozesse")
--   farbe_akzent     – Primärfarbe als #RRGGBB (z. B. #5B6FA8)
--   farbe_sekundaer  – Sekundärfarbe als #RRGGBB (z. B. #D98A2B)
--   logo_pfad        – Interner Dateipfad zum Logo (wird nie ans Frontend gegeben)
--   logo_mime        – MIME-Type des Logos (image/png, image/jpeg, image/svg+xml)
--   vorschau_aktiv   – "1" wenn Vorschau-Einstellungen aktiv, "0" sonst
--
-- Nur ein Datensatz pro Schlüssel (UNIQUE). Änderungen per
-- INSERT OR REPLACE, Lesen per SELECT WHERE schluessel = '...'.
-- ============================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS einstellungen (
    schluessel  TEXT PRIMARY KEY,
    wert        TEXT NOT NULL DEFAULT '',
    geaendert_am TEXT NOT NULL DEFAULT (datetime('now')),
    geaendert_von TEXT
);

-- Standardwerte eintragen
INSERT OR IGNORE INTO einstellungen (schluessel, wert) VALUES
    ('schulname',       'Meine Schule'),
    ('app_titel',       'Schulprozesse'),
    ('farbe_akzent',    '#5B6FA8'),
    ('farbe_sekundaer', '#D98A2B'),
    ('logo_pfad',       ''),
    ('logo_mime',       ''),
    ('vorschau_aktiv',  '0');
