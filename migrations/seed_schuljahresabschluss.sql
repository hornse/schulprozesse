-- ============================================================================
-- Optionaler Seed: Vorlage "Schuljahresabschluss"
-- ============================================================================
-- Einspielen NUR wenn diese Vorlage gewünscht ist:
--   sqlite3 data/app.sqlite < migrations/seed_schuljahresabschluss.sql
-- ============================================================================

INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES
  ('1. Zeugnisse',        '#C0392B', 601),
  ('2. Übergaben',        '#D98A2B', 602),
  ('3. IT und Verwaltung','#3D7B6F', 603),
  ('4. Abschlussveranstaltungen', '#8E44AD', 604),
  ('5. Dokumentation',    '#7F8C8D', 605);

INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
-- 1. Zeugnisse
((SELECT id FROM phasen WHERE name = '1. Zeugnisse'), 1,
 'Notenabgabe-Fristen kommunizieren', 'Letzte Termine für alle Fächer, Erinnerung per Konferenz/Mail.', 1),
((SELECT id FROM phasen WHERE name = '1. Zeugnisse'), 2,
 'Klassenkonferenzen durchführen', 'Versetzungsentscheidungen, besondere Vermerke, Unterschriften.', 1),
((SELECT id FROM phasen WHERE name = '1. Zeugnisse'), 3,
 'Zeugnisse drucken und unterschreiben lassen', 'Schulleitung, Klassenlehrkraft, ggf. Schulstempel.', 1),
((SELECT id FROM phasen WHERE name = '1. Zeugnisse'), 4,
 'Zeugnisse ausgeben', 'Ausgabedatum, Empfangsbestätigung bei Abgangszeugnissen.', 1),

-- 2. Übergaben
((SELECT id FROM phasen WHERE name = '2. Übergaben'), 1,
 'Klassenräume übergeben', 'Inventar prüfen, Schäden melden, Schlüssel zurückgeben.', 1),
((SELECT id FROM phasen WHERE name = '2. Übergaben'), 2,
 'Lehrmittel und Bücher einsammeln', 'Vollständigkeit prüfen, Schäden dokumentieren.', 1),
((SELECT id FROM phasen WHERE name = '2. Übergaben'), 3,
 'Fachschaftsmaterialien sichern', 'Prüfungsaufgaben archivieren, Unterrichtsmaterialien ordnen.', 1),

-- 3. IT und Verwaltung
((SELECT id FROM phasen WHERE name = '3. IT und Verwaltung'), 1,
 'Schülerdaten für Schuljahreswechsel vorbereiten', 'Abstimmung mit WebUntis-Verantwortlichen.', 1),
((SELECT id FROM phasen WHERE name = '3. IT und Verwaltung'), 2,
 'Accounts ausgetretener Schüler:innen sperren', 'Schulnetz, E-Mail, MDM-Geräte.', 1),
((SELECT id FROM phasen WHERE name = '3. IT und Verwaltung'), 3,
 'Klassenbücher abschließen und archivieren', 'Digital: Export/Archivierung. Analog: geordnete Ablage.', 1),
((SELECT id FROM phasen WHERE name = '3. IT und Verwaltung'), 4,
 'Statistiken und Berichte erstellen', 'Fehlzeiten, Versetzungsquoten, sonstige Pflichtstatistiken.', 1),

-- 4. Abschlussveranstaltungen
((SELECT id FROM phasen WHERE name = '4. Abschlussveranstaltungen'), 1,
 'Abschlussfeier planen und durchführen', 'Programm, Reden, Musik, Technik, Bewirtung.', 1),
((SELECT id FROM phasen WHERE name = '4. Abschlussveranstaltungen'), 2,
 'Entlassungsfeier abhalten', 'Zeugnisübergabe, Ehrungen, Verabschiedung.', 1),
((SELECT id FROM phasen WHERE name = '4. Abschlussveranstaltungen'), 3,
 'Lehrerkonferenz zum Schuljahresabschluss', 'Rückblick, Beschlüsse für nächstes Jahr, Verabschiedungen.', 1),

-- 5. Dokumentation
((SELECT id FROM phasen WHERE name = '5. Dokumentation'), 1,
 'Jahresbericht erstellen', 'Schulleitung: Überblick über das Schuljahr für Schulaufsicht.', 1),
((SELECT id FROM phasen WHERE name = '5. Dokumentation'), 2,
 'Schulprogramm evaluieren und anpassen', 'Ziele des Schuljahres reflektieren, Korrekturen vornehmen.', 1),
((SELECT id FROM phasen WHERE name = '5. Dokumentation'), 3,
 'Inventur durchführen', 'Bestandslisten aktualisieren, Bedarfsliste für nächstes Jahr.', 1);
