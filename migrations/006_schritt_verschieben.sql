-- ============================================================================
-- Migration 006: Schritte per Drag-and-drop verschieben
-- ============================================================================
-- "Prozesse verwalten" braucht eine Möglichkeit, einen vorlage-basierten
-- Schritt (schritt_instanzen) in eine andere Phase zu verschieben, ohne die
-- globale Vorlage (schritt_vorlagen.phase_id) zu berühren – analog zu
-- instanz_titel/instanz_reihenfolge aus Migration 004, die schon Titel und
-- Reihenfolge pro Prozess überschreiben können, aber keine Phase.
--
-- instanz_phase_name/instanz_phase_farbe sind bewusst freie Textfelder statt
-- einer Fremdschlüssel-Spalte auf phasen(id): eigene Schritte (instanz_schritte)
-- kennen "Phase" ohnehin nur als Name+Farbe ohne eigene Tabelle (siehe
-- Migration 004), und ein Schritt muss auch dorthin verschiebbar sein. Eine
-- FK auf phasen(id) hätte das nicht abgebildet.
--
-- NULL/NULL (Default) = Schritt folgt weiter der Vorlage-Phase (inkl. späterer
-- Umbenennungen über instanz_phasen). Gesetzt = Schritt wurde für diesen
-- Prozess ausdrücklich in eine andere Phase verschoben und folgt der
-- Vorlage-Phase ab da nicht mehr – siehe docs/ENTSCHEIDUNGEN.md.
-- ============================================================================

ALTER TABLE schritt_instanzen ADD COLUMN instanz_phase_name  TEXT;
ALTER TABLE schritt_instanzen ADD COLUMN instanz_phase_farbe TEXT;
