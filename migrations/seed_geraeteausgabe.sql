-- ============================================================================
-- Optionaler Seed: Vorlage "iPad- und Geräteausgabe"
-- ============================================================================
-- Einspielen NUR wenn diese Vorlage gewünscht ist:
--   sqlite3 data/app.sqlite < migrations/seed_geraeteausgabe.sql
-- ============================================================================

INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES
  ('1. Vorbereitung',      '#2980B9', 301),
  ('2. Ausgabe',           '#27AE60', 302),
  ('3. Betreuung',         '#D98A2B', 303),
  ('4. Rückgabe',          '#C0392B', 304);

INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
-- 1. Vorbereitung
((SELECT id FROM phasen WHERE name = '1. Vorbereitung'), 1,
 'Geräteliste und Seriennummern aktualisieren', 'Bestandsliste mit MDM-System abgleichen.', 1),
((SELECT id FROM phasen WHERE name = '1. Vorbereitung'), 2,
 'MDM-Profile und Apps aktualisieren', 'Neue Schuljahresprofile deployen, App-Lizenzen prüfen.', 1),
((SELECT id FROM phasen WHERE name = '1. Vorbereitung'), 3,
 'Nutzungsvereinbarungen vorbereiten', 'Dokumente aktualisieren, Unterschriftenmappe vorbereiten.', 1),
((SELECT id FROM phasen WHERE name = '1. Vorbereitung'), 4,
 'Ausgabeplan erstellen', 'Klassen, Zeitslots, Räume und Verantwortliche festlegen.', 1),
((SELECT id FROM phasen WHERE name = '1. Vorbereitung'), 5,
 'Schutzhüllen und Zubehör bereitstellen', 'Kabel, Netzteile, Hüllen – Vollständigkeit prüfen.', 1),

-- 2. Ausgabe
((SELECT id FROM phasen WHERE name = '2. Ausgabe'), 1,
 'Nutzungsvereinbarungen unterzeichnen lassen', 'Schüler:innen und Erziehungsberechtigte (Minderjährige).', 1),
((SELECT id FROM phasen WHERE name = '2. Ausgabe'), 2,
 'Geräte ausgeben und Seriennummern zuordnen', 'Ausgabeliste abhaken, Seriennummer–Schüler:in dokumentieren.', 1),
((SELECT id FROM phasen WHERE name = '2. Ausgabe'), 3,
 'Einweisung durchführen', 'Grundfunktionen, Schulregeln, Meldewege bei Defekten.', 1),
((SELECT id FROM phasen WHERE name = '2. Ausgabe'), 4,
 'Ausgabeliste archivieren', 'Unterschriebene Liste sicher aufbewahren.', 1),

-- 3. Betreuung
((SELECT id FROM phasen WHERE name = '3. Betreuung'), 1,
 'Defekte Geräte entgegennehmen und dokumentieren', 'Schadensprotokoll, Leihgerät bereitstellen.', 1),
((SELECT id FROM phasen WHERE name = '3. Betreuung'), 2,
 'Software-Updates einspielen', 'Regelmäßig via MDM, Zeitfenster außerhalb des Unterrichts.', 1),
((SELECT id FROM phasen WHERE name = '3. Betreuung'), 3,
 'Verlorene/gestohlene Geräte sperren', 'Im MDM sofort sperren und Diebstahlmeldung aufnehmen.', 1),

-- 4. Rückgabe
((SELECT id FROM phasen WHERE name = '4. Rückgabe'), 1,
 'Rückgabeplan erstellen', 'Klassen, Zeitslots, Räume koordinieren.', 1),
((SELECT id FROM phasen WHERE name = '4. Rückgabe'), 2,
 'Geräte zurücknehmen und prüfen', 'Zustand dokumentieren, Schäden erfassen.', 1),
((SELECT id FROM phasen WHERE name = '4. Rückgabe'), 3,
 'Geräte zurücksetzen', 'MDM-Enrollment aufheben, Werkseinstellungen wiederherstellen.', 1),
((SELECT id FROM phasen WHERE name = '4. Rückgabe'), 4,
 'Schadensabrechnung vorbereiten', 'Reparaturkosten ermitteln, mit Eltern klären.', 1),
((SELECT id FROM phasen WHERE name = '4. Rückgabe'), 5,
 'Bestandsliste für nächstes Jahr aktualisieren', 'Ausgemusterte Geräte entfernen, Neuzugänge erfassen.', 1);
