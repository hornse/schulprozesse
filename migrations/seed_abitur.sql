-- ============================================================================
-- Optionaler Seed: Vorlage "Abitur-Organisation"
-- ============================================================================
-- Einspielen NUR wenn diese Vorlage gewünscht ist:
--   sqlite3 data/app.sqlite < migrations/seed_abitur.sql
-- ============================================================================

INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES
  ('1. Anmeldung und Zulassung', '#2980B9', 201),
  ('2. Raumplanung',             '#D98A2B', 202),
  ('3. Durchführung',            '#C0392B', 203),
  ('4. Korrektur und Noten',     '#3D7B6F', 204),
  ('5. Mündliche Prüfungen',     '#8E44AD', 205),
  ('6. Abschluss',               '#7F8C8D', 206);

INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
-- 1. Anmeldung und Zulassung
((SELECT id FROM phasen WHERE name = '1. Anmeldung und Zulassung'), 1,
 'Schüler:innen zur Abiturprüfung anmelden', 'Fristen der Schulbehörde beachten, Anmeldeformulare prüfen.', 1),
((SELECT id FROM phasen WHERE name = '1. Anmeldung und Zulassung'), 2,
 'Zulassungsentscheidungen treffen', 'Konferenz, Dokumentation, Mitteilung an Schüler:innen.', 1),
((SELECT id FROM phasen WHERE name = '1. Anmeldung und Zulassung'), 3,
 'Prüfungsfächer und -termine an Behörde melden', 'Terminabstimmung mit Schulaufsicht.', 1),

-- 2. Raumplanung
((SELECT id FROM phasen WHERE name = '2. Raumplanung'), 1,
 'Prüfungsräume einteilen', 'Sitzabstände, Aufsichtslisten, Reserveräume.', 1),
((SELECT id FROM phasen WHERE name = '2. Raumplanung'), 2,
 'Aufsichten einteilen und informieren', 'Aufsichtsplan erstellen, Belehrung über Prüfungsordnung.', 1),
((SELECT id FROM phasen WHERE name = '2. Raumplanung'), 3,
 'Nachteilsausgleiche organisieren', 'Separate Räume, Zeitverlängerungen, Hilfsmittel bereitstellen.', 1),
((SELECT id FROM phasen WHERE name = '2. Raumplanung'), 4,
 'Prüfungsunterlagen sicher aufbewahren', 'Versiegelter Transport, Zugangskontrolle.', 1),

-- 3. Durchführung
((SELECT id FROM phasen WHERE name = '3. Durchführung'), 1,
 'Schriftliche Prüfungen durchführen', 'Beginn pünktlich, Protokolle führen, Störungen dokumentieren.', 1),
((SELECT id FROM phasen WHERE name = '3. Durchführung'), 2,
 'Nachschreibtermine organisieren', 'Atteste prüfen, Ersatzaufgaben bereitstellen.', 1),
((SELECT id FROM phasen WHERE name = '3. Durchführung'), 3,
 'Prüfungsarbeiten sicher verwahren', 'Bis zur Korrektur verschlossen und zugangsbeschränkt.', 1),

-- 4. Korrektur und Noten
((SELECT id FROM phasen WHERE name = '4. Korrektur und Noten'), 1,
 'Erstkorrektur abschließen', 'Fristen einhalten, Punktsummen prüfen.', 1),
((SELECT id FROM phasen WHERE name = '4. Korrektur und Noten'), 2,
 'Zweitkorrektur koordinieren', 'Tausch der Arbeiten, Abweichungen dokumentieren.', 1),
((SELECT id FROM phasen WHERE name = '4. Korrektur und Noten'), 3,
 'Noten in WebUntis/System einpflegen', 'Fristen der Schulbehörde beachten.', 1),
((SELECT id FROM phasen WHERE name = '4. Korrektur und Noten'), 4,
 'Akteneinsicht ermöglichen', 'Termin bekannt geben, Begleitung durch Fachlehrkraft.', 1),

-- 5. Mündliche Prüfungen
((SELECT id FROM phasen WHERE name = '5. Mündliche Prüfungen'), 1,
 'Mündliche Prüfungen terminieren', 'Prüfungsausschuss einberufen, Termine mit Schüler:innen abstimmen.', 1),
((SELECT id FROM phasen WHERE name = '5. Mündliche Prüfungen'), 2,
 'Mündliche Prüfungen durchführen', 'Protokolle führen, Beisitzer informieren.', 1),
((SELECT id FROM phasen WHERE name = '5. Mündliche Prüfungen'), 3,
 'Gesamtergebnis berechnen und beschließen', 'Abiturkonferenz, Zeugniserstellung.', 1),

-- 6. Abschluss
((SELECT id FROM phasen WHERE name = '6. Abschluss'), 1,
 'Abiturzeugnisse ausstellen', 'Unterschriften, Siegel, Übergabe vorbereiten.', 1),
((SELECT id FROM phasen WHERE name = '6. Abschluss'), 2,
 'Entlassungsfeier organisieren', 'Rednerliste, Räume, Technik, Programm.', 1),
((SELECT id FROM phasen WHERE name = '6. Abschluss'), 3,
 'Prüfungsunterlagen archivieren', 'Aufbewahrungsfristen beachten, sicher einlagern.', 1);
