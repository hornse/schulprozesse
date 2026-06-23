-- ============================================================================
-- Optionaler Seed: Vorlage "Schuljahresbeginn allgemein"
-- ============================================================================
-- Einspielen NUR wenn diese Vorlage gewünscht ist:
--   sqlite3 data/app.sqlite < migrations/seed_schuljahresbeginn.sql
--
-- Legt eine neue Phasenstruktur und Schritte an ohne bestehende Daten
-- zu verändern. Danach in der Oberfläche unter "Vorlagen-Snapshots" als
-- benannten Snapshot einfrieren und beim Anlegen eines neuen Schuljahres
-- als Basis wählen.
-- ============================================================================

-- Phasen anlegen (falls noch nicht vorhanden)
INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES
  ('1. Kommunikation',    '#5B6FA8', 101),
  ('2. Raumorganisation', '#D98A2B', 102),
  ('3. IT und Technik',   '#3D7B6F', 103),
  ('4. Unterricht',       '#B5577A', 104),
  ('5. Abschluss',        '#7F8C8D', 105);

-- Schritte anlegen
INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
-- 1. Kommunikation
((SELECT id FROM phasen WHERE name = '1. Kommunikation'), 1,
 'Willkommensbrief an Eltern versenden', 'Klassenlehrkräfte, Schulbeginn, wichtige Termine und Kontaktdaten.', 1),
((SELECT id FROM phasen WHERE name = '1. Kommunikation'), 2,
 'Stundenplan aushängen und digital bereitstellen', 'Schwarzes Brett und Schulwebsite aktualisieren.', 1),
((SELECT id FROM phasen WHERE name = '1. Kommunikation'), 3,
 'Elternabende terminieren und ankündigen', 'Erstmals in den ersten zwei Schulwochen, Datum in WebUntis eintragen.', 1),
((SELECT id FROM phasen WHERE name = '1. Kommunikation'), 4,
 'Vertretungsplan-System aktualisieren', 'Zuständigkeiten, Vertretungsregelungen und Kontaktlisten prüfen.', 1),

-- 2. Raumorganisation
((SELECT id FROM phasen WHERE name = '2. Raumorganisation'), 1,
 'Schlüsselvergabe organisieren', 'Schlüsselliste aktualisieren, neue Kolleg:innen einweisen.', 1),
((SELECT id FROM phasen WHERE name = '2. Raumorganisation'), 2,
 'Klassenräume zuweisen und prüfen', 'Raumplan erstellen, Ausstattung kontrollieren (Beamer, Kreide, Marker).', 1),
((SELECT id FROM phasen WHERE name = '2. Raumorganisation'), 3,
 'Fachraumbelegung koordinieren', 'Sporthalle, Informatik, Musik, Kunst – Belegungspläne abstimmen.', 1),

-- 3. IT und Technik
((SELECT id FROM phasen WHERE name = '3. IT und Technik'), 1,
 'Neue Schüleraccounts anlegen', 'Schulnetz, E-Mail, ggf. iPad-MDM. Koordination mit IT-Beauftragtem.', 1),
((SELECT id FROM phasen WHERE name = '3. IT und Technik'), 2,
 'Accounts ausgetretener Schüler:innen sperren', 'Schulnetz, E-Mail und alle weiteren Dienste.', 1),
((SELECT id FROM phasen WHERE name = '3. IT und Technik'), 3,
 'Technik in Klassenräumen prüfen', 'Beamer, interaktive Tafeln, WLAN – Defekte melden und beheben lassen.', 1),
((SELECT id FROM phasen WHERE name = '3. IT und Technik'), 4,
 'Schulwebsite aktualisieren', 'Neue Kolleg:innen, aktuelles Schuljahr, Termine.', 1),

-- 4. Unterricht
((SELECT id FROM phasen WHERE name = '4. Unterricht'), 1,
 'Lehrwerke und Materialien bereitstellen', 'Buchausgabe organisieren, fehlende Exemplare nachbestellen.', 1),
((SELECT id FROM phasen WHERE name = '4. Unterricht'), 2,
 'Neue Kolleg:innen einweisen', 'Hausordnung, Notfallplan, Konferenzstruktur, Kolleg:innenliste.', 1),
((SELECT id FROM phasen WHERE name = '4. Unterricht'), 3,
 'Klassenbücher ausgeben und einrichten', 'Digital oder analog, Verantwortliche benennen.', 1),

-- 5. Abschluss
((SELECT id FROM phasen WHERE name = '5. Abschluss'), 1,
 'Erste Gesamtkonferenz abhalten', 'Schuljahresplanung, Beschlüsse, Zuständigkeiten.', 1),
((SELECT id FROM phasen WHERE name = '5. Abschluss'), 2,
 'Vollständigkeit aller Maßnahmen prüfen', 'Checkliste gemeinsam durchgehen, offene Punkte delegieren.', 1);
