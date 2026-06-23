-- ============================================================================
-- Migration 002: Standard-Seed WebUntis-Schuljahreswechsel
-- ============================================================================

-- Phasen
INSERT INTO phasen (name, farbe, reihenfolge) VALUES
  ('Vorbereitung in Untis (Desktop)', '#D98A2B', 1),
  ('Schuljahr in WebUntis',           '#3D7B6F', 2),
  ('Schülerstammdaten',               '#5B6FA8', 3),
  ('Benutzerverwaltung',              '#B5577A', 4),
  ('Kontrolle',                       '#3B3B3B', 5);

-- Schritte
INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
(1, 1, 'Schülergruppen anlegen',
 'Zwingend VOR dem Stammdaten-Export, falls Klassen geteilt werden oder Team-Teaching stattfindet.', 1),
(1, 2, 'Stammdaten nach WebUntis exportieren',
 'Das neue Schuljahr wird damit automatisch in WebUntis angelegt.', 1),
(1, 3, 'Oberstufenkoordinatoren pflegen SchildID vor Import von Stammdaten in WebUntis ein.',
 NULL, 1),

(2, 1, 'Berichte des alten Schuljahres sichern',
 'Z. B. Klassenbuch-Deckblatt und Arbeitsbericht je Tag (Klassenbuch → Berichte).', 1),
(2, 2, 'Altes Schuljahr löschen',
 'Erst nach Ablauf der landesspezifischen Aufbewahrungsfrist und nur wenn alle Berichte gesichert sind.', 1),

(3, 1, 'Schülerdaten importieren',
 'Stichtag für die Klassenzugehörigkeit = Beginn des neuen Schuljahres (Stammdaten → Schüler → Import).', 1),
(3, 2, 'Importierte Daten kontrollieren',
 'Auf Dubletten und fehlende Geburtsdaten prüfen, besonders bei Namensgleichheit.', 1),
(3, 3, 'Schüler zu Unterrichten zuordnen',
 'Nur nötig bei Klassenteilungen/Team-Teaching, sofern Schülergruppen angelegt wurden.', 1),
(3, 4, 'Austrittsdatum für abgehende Schüler setzen',
 'Stammdaten → Schüler → "Austrittsdatum setzen".', 1),
(3, 5, 'Schülerstammdaten aus Schild exportieren',
 '- SchildID und Austrittsdatum bitte sofort mitgeben
- keine Oberstufenschüler importieren!!', 1),

(4, 1, 'Benutzer für neue Schüler anlegen',
 'Administration → Benutzer → Benutzerverwaltung → "Benutzer für Schüler anlegen". Erst nach dem Datenimport.', 1),
(4, 2, 'Benutzer ausgetretener Schüler sperren',
 'Administration → Benutzer → Benutzerverwaltung → "Benutzer von inaktiven oder ausgetretenen Personen sperren".', 1),

(5, 1, 'Stichprobe & Probelauf in WebUntis',
 'Stundenplan, Klassenzuordnungen und Logins exemplarisch prüfen, bevor das Kollegium startet.', 1);

-- Ersten Prozess anlegen
INSERT INTO prozesse (label, beschreibung, aktiv, oeffentlich)
VALUES ('WebUntis Schuljahreswechsel 2026/2027', 'Koordination des jährlichen WebUntis-Schuljahreswechsels', 1, 1);

-- Instanzen für den ersten Prozess
INSERT INTO schritt_instanzen (prozess_id, vorlage_id)
SELECT 1, id FROM schritt_vorlagen WHERE aktiv = 1;
