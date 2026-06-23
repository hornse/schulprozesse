-- ============================================================================
-- Optionaler Seed: Vorlage "Studientag-Organisation"
-- ============================================================================
-- Einspielen NUR wenn diese Vorlage gewünscht ist:
--   sqlite3 data/app.sqlite < migrations/seed_studientag.sql
-- ============================================================================

INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES
  ('1. Planung',       '#5B6FA8', 501),
  ('2. Vorbereitung',  '#D98A2B', 502),
  ('3. Durchführung',  '#27AE60', 503),
  ('4. Nachbereitung', '#7F8C8D', 504);

INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
-- 1. Planung
((SELECT id FROM phasen WHERE name = '1. Planung'), 1,
 'Thema und Ziele festlegen', 'Konferenzbeschluss, pädagogischen Schwerpunkt definieren.', 1),
((SELECT id FROM phasen WHERE name = '1. Planung'), 2,
 'Termin festlegen und genehmigen lassen', 'Schulaufsicht informieren, Eltern rechtzeitig benachrichtigen.', 1),
((SELECT id FROM phasen WHERE name = '1. Planung'), 3,
 'Referenten/Moderatoren anfragen', 'Externe Fachkräfte, interne Kolleg:innen, Kosten klären.', 1),
((SELECT id FROM phasen WHERE name = '1. Planung'), 4,
 'Programm erstellen', 'Zeitplan, Pausen, Gruppenaufteilung, Arbeitsphasen.', 1),

-- 2. Vorbereitung
((SELECT id FROM phasen WHERE name = '2. Vorbereitung'), 1,
 'Räume buchen und einrichten', 'Bestuhlung, Technik, Moderationsmaterial bereitstellen.', 1),
((SELECT id FROM phasen WHERE name = '2. Vorbereitung'), 2,
 'Technik prüfen', 'Beamer, Mikrofon, Lautsprecher, Laptop, Internetzugang testen.', 1),
((SELECT id FROM phasen WHERE name = '2. Vorbereitung'), 3,
 'Verpflegung organisieren', 'Kaffee, Snacks, Mittagessen – intern oder extern.', 1),
((SELECT id FROM phasen WHERE name = '2. Vorbereitung'), 4,
 'Materialien vorbereiten und drucken', 'Handouts, Arbeitsblätter, Namensschilder, Programm.', 1),
((SELECT id FROM phasen WHERE name = '2. Vorbereitung'), 5,
 'Kollegium informieren', 'Programm, Räume, Erwartungen rechtzeitig mitteilen.', 1),

-- 3. Durchführung
((SELECT id FROM phasen WHERE name = '3. Durchführung'), 1,
 'Anwesenheit dokumentieren', 'Unterschriftenliste führen (für Nachweis gegenüber Schulaufsicht).', 1),
((SELECT id FROM phasen WHERE name = '3. Durchführung'), 2,
 'Ergebnisse sichern', 'Fotos von Plakaten, digitale Protokolle, Dokumentation.', 1),
((SELECT id FROM phasen WHERE name = '3. Durchführung'), 3,
 'Feedbackbögen austeilen und einsammeln', 'Anonyme Evaluation zur Qualitätssicherung.', 1),

-- 4. Nachbereitung
((SELECT id FROM phasen WHERE name = '4. Nachbereitung'), 1,
 'Ergebnisse aufbereiten und verteilen', 'Protokoll an Kollegium, Beschlüsse in Konferenzmappe.', 1),
((SELECT id FROM phasen WHERE name = '4. Nachbereitung'), 2,
 'Feedback auswerten', 'Erkenntnisse für künftige Studientage festhalten.', 1),
((SELECT id FROM phasen WHERE name = '4. Nachbereitung'), 3,
 'Maßnahmen aus Beschlüssen verfolgen', 'Verantwortliche benennen, in Jahresplanung aufnehmen.', 1),
((SELECT id FROM phasen WHERE name = '4. Nachbereitung'), 4,
 'Abrechnung erstellen', 'Referentenhonorare, Materialkosten, Bewirtung abrechnen.', 1);
