-- ============================================================================
-- Optionaler Seed: Vorlage "DSGVO-Jahresprüfung"
-- ============================================================================
-- Einspielen NUR wenn diese Vorlage gewünscht ist:
--   sqlite3 data/app.sqlite < migrations/seed_dsgvo.sql
-- ============================================================================

INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES
  ('1. Dokumentation',          '#2980B9', 401),
  ('2. Auftragsverarbeiter',     '#8E44AD', 402),
  ('3. Technische Maßnahmen',    '#3D7B6F', 403),
  ('4. Schulung und Sensibilisierung', '#D98A2B', 404),
  ('5. Abschluss',               '#7F8C8D', 405);

INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, aktiv) VALUES
-- 1. Dokumentation
((SELECT id FROM phasen WHERE name = '1. Dokumentation'), 1,
 'Verzeichnis der Verarbeitungstätigkeiten aktualisieren', 'Art. 30 DSGVO: neue Verarbeitungen ergänzen, weggefallene entfernen.', 1),
((SELECT id FROM phasen WHERE name = '1. Dokumentation'), 2,
 'Löschkonzept prüfen und anwenden', 'Aufbewahrungsfristen für Schülerdaten, Noten, Fotos prüfen.', 1),
((SELECT id FROM phasen WHERE name = '1. Dokumentation'), 3,
 'Datenschutzerklärungen aktualisieren', 'Website, Apps, Einwilligungsformulare auf Aktualität prüfen.', 1),
((SELECT id FROM phasen WHERE name = '1. Dokumentation'), 4,
 'Einwilligungen für das neue Schuljahr einholen', 'Fotos, Schulwebsite, externe Dienste – neue Schüler:innen erfassen.', 1),

-- 2. Auftragsverarbeiter
((SELECT id FROM phasen WHERE name = '2. Auftragsverarbeiter'), 1,
 'Liste der Auftragsverarbeiter prüfen', 'Alle genutzten Cloud-Dienste, Tools und Dienstleister auflisten.', 1),
((SELECT id FROM phasen WHERE name = '2. Auftragsverarbeiter'), 2,
 'AVV-Verträge auf Vollständigkeit prüfen', 'Fehlende Auftragsverarbeitungsverträge nachfordern.', 1),
((SELECT id FROM phasen WHERE name = '2. Auftragsverarbeiter'), 3,
 'Neue Dienste auf DSGVO-Konformität prüfen', 'Vor Einführung neuer Tools: Datenschutz-Folgenabschätzung ggf. nötig.', 1),

-- 3. Technische Maßnahmen
((SELECT id FROM phasen WHERE name = '3. Technische Maßnahmen'), 1,
 'Zugriffsrechte prüfen', 'Wer hat Zugriff auf welche Daten? Ausgetretene Lehrkräfte entfernen.', 1),
((SELECT id FROM phasen WHERE name = '3. Technische Maßnahmen'), 2,
 'Passwort-Richtlinien kontrollieren', 'Ablaufdaten, Komplexitätsanforderungen, Zwei-Faktor-Auth.', 1),
((SELECT id FROM phasen WHERE name = '3. Technische Maßnahmen'), 3,
 'Datensicherung prüfen', 'Backup-Konzept testen, Wiederherstellbarkeit verifizieren.', 1),
((SELECT id FROM phasen WHERE name = '3. Technische Maßnahmen'), 4,
 'Sicherheitsupdates einspielen', 'Server, Schulnetz-Geräte, Software auf aktuellem Stand.', 1),

-- 4. Schulung
((SELECT id FROM phasen WHERE name = '4. Schulung und Sensibilisierung'), 1,
 'Kollegium über aktuelle Datenschutzthemen informieren', 'Konferenzbeitrag oder Rundmail mit relevanten Neuerungen.', 1),
((SELECT id FROM phasen WHERE name = '4. Schulung und Sensibilisierung'), 2,
 'Neue Kolleg:innen datenschutzrechtlich einweisen', 'Schulspezifische Regelungen, Meldepflichten, Kontakte.', 1),
((SELECT id FROM phasen WHERE name = '4. Schulung und Sensibilisierung'), 3,
 'Datenschutzbeauftragten informieren', 'Jahresbericht, neue Verarbeitungen, aufgetretene Vorfälle.', 1),

-- 5. Abschluss
((SELECT id FROM phasen WHERE name = '5. Abschluss'), 1,
 'Prüfung dokumentieren und ablegen', 'Datum, Ergebnis und offene Punkte festhalten.', 1),
((SELECT id FROM phasen WHERE name = '5. Abschluss'), 2,
 'Offene Maßnahmen terminieren', 'Verantwortliche benennen, Fristen setzen.', 1);
