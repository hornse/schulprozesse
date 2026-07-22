-- Migration 005: Herkunft von Vorlage-Schritten festhalten
--
-- Schritte die über "Prozess verwalten -> + Weiterer Schritt" angelegt werden,
-- sind schritt_vorlagen die nur zu genau einem Prozess gehoeren. Bisher wurde
-- das ueber COUNT(schritt_instanzen) erschlossen - das ist falsch, sobald
-- Prozesse aus Snapshots stammen, denn die bekommen ohnehin eigene Vorlagen.
--
-- prozess_id IS NULL  -> gehoert zur Standard-Vorlage bzw. einem Snapshot
-- prozess_id = X      -> wurde fuer Prozess X angelegt, dort loeschbar

ALTER TABLE schritt_vorlagen ADD COLUMN prozess_id INTEGER NULL REFERENCES prozesse(id);

CREATE INDEX IF NOT EXISTS idx_schritt_vorlagen_prozess
    ON schritt_vorlagen(prozess_id);
