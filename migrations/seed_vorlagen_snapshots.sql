-- ============================================================================
-- Seed: Alle Vorlagen direkt als Snapshots anlegen
-- ============================================================================
-- Legt alle 6 Prozess-Vorlagen als fertige Snapshots in vorlagen_sets,
-- vorlagen_set_phasen und vorlagen_set_schritte an.
-- Die aktive Vorlagentabelle (phasen, schritt_vorlagen) wird NICHT verändert.
-- Nach dem Einspielen erscheinen alle Vorlagen sofort im Dropdown
-- "Basis wählen" beim Anlegen eines neuen Prozesses.
-- ============================================================================

-- ============================================================
-- 1. Schuljahresbeginn allgemein
-- ============================================================
INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES
  ('Schuljahresbeginn allgemein', 'Kommunikation, Räume, IT, Unterricht, Abschluss', 'system');

INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'), '1. Kommunikation',    '#5B6FA8', 1),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'), '2. Raumorganisation', '#D98A2B', 2),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'), '3. IT und Technik',   '#3D7B6F', 3),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'), '4. Unterricht',       '#B5577A', 4),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'), '5. Abschluss',        '#7F8C8D', 5);

INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung) VALUES
  -- 1. Kommunikation
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '1. Kommunikation'),
   1, 'Willkommensbrief an Eltern versenden', 'Klassenlehrkräfte, Schulbeginn, wichtige Termine und Kontaktdaten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '1. Kommunikation'),
   2, 'Stundenplan aushängen und digital bereitstellen', 'Schwarzes Brett und Schulwebsite aktualisieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '1. Kommunikation'),
   3, 'Elternabende terminieren und ankündigen', 'Erstmals in den ersten zwei Schulwochen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '1. Kommunikation'),
   4, 'Vertretungsplan-System aktualisieren', 'Zuständigkeiten, Vertretungsregelungen und Kontaktlisten prüfen.'),
  -- 2. Raumorganisation
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '2. Raumorganisation'),
   1, 'Schlüsselvergabe organisieren', 'Schlüsselliste aktualisieren, neue Kolleg:innen einweisen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '2. Raumorganisation'),
   2, 'Klassenräume zuweisen und prüfen', 'Raumplan erstellen, Ausstattung kontrollieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '2. Raumorganisation'),
   3, 'Fachraumbelegung koordinieren', 'Sporthalle, Informatik, Musik, Kunst – Belegungspläne abstimmen.'),
  -- 3. IT und Technik
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '3. IT und Technik'),
   1, 'Neue Schüleraccounts anlegen', 'Schulnetz, E-Mail, ggf. iPad-MDM.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '3. IT und Technik'),
   2, 'Accounts ausgetretener Schüler:innen sperren', 'Schulnetz, E-Mail und alle weiteren Dienste.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '3. IT und Technik'),
   3, 'Technik in Klassenräumen prüfen', 'Beamer, interaktive Tafeln, WLAN – Defekte melden.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '3. IT und Technik'),
   4, 'Schulwebsite aktualisieren', 'Neue Kolleg:innen, aktuelles Schuljahr, Termine.'),
  -- 4. Unterricht
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '4. Unterricht'),
   1, 'Lehrwerke und Materialien bereitstellen', 'Buchausgabe organisieren, fehlende Exemplare nachbestellen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '4. Unterricht'),
   2, 'Neue Kolleg:innen einweisen', 'Hausordnung, Notfallplan, Konferenzstruktur.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '4. Unterricht'),
   3, 'Klassenbücher ausgeben und einrichten', 'Digital oder analog, Verantwortliche benennen.'),
  -- 5. Abschluss
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '5. Abschluss'),
   1, 'Erste Gesamtkonferenz abhalten', 'Schuljahresplanung, Beschlüsse, Zuständigkeiten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresbeginn allgemein') AND name = '5. Abschluss'),
   2, 'Vollständigkeit aller Maßnahmen prüfen', 'Checkliste gemeinsam durchgehen, offene Punkte delegieren.');

-- ============================================================
-- 2. Abitur-Organisation
-- ============================================================
INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES
  ('Abitur-Organisation', 'Anmeldung, Raumplanung, Durchführung, Korrektur, mündliche Prüfungen', 'system');

INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'), '1. Anmeldung und Zulassung', '#2980B9', 1),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'), '2. Raumplanung',             '#D98A2B', 2),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'), '3. Durchführung',            '#C0392B', 3),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'), '4. Korrektur und Noten',     '#3D7B6F', 4),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'), '5. Mündliche Prüfungen',     '#8E44AD', 5),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'), '6. Abschluss',               '#7F8C8D', 6);

INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '1. Anmeldung und Zulassung'),
   1, 'Schüler:innen zur Abiturprüfung anmelden', 'Fristen der Schulbehörde beachten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '1. Anmeldung und Zulassung'),
   2, 'Zulassungsentscheidungen treffen', 'Konferenz, Dokumentation, Mitteilung an Schüler:innen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '1. Anmeldung und Zulassung'),
   3, 'Prüfungsfächer und -termine an Behörde melden', 'Terminabstimmung mit Schulaufsicht.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '2. Raumplanung'),
   1, 'Prüfungsräume einteilen', 'Sitzabstände, Aufsichtslisten, Reserveräume.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '2. Raumplanung'),
   2, 'Aufsichten einteilen und informieren', 'Aufsichtsplan erstellen, Belehrung über Prüfungsordnung.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '2. Raumplanung'),
   3, 'Nachteilsausgleiche organisieren', 'Separate Räume, Zeitverlängerungen, Hilfsmittel.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '2. Raumplanung'),
   4, 'Prüfungsunterlagen sicher aufbewahren', 'Versiegelter Transport, Zugangskontrolle.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '3. Durchführung'),
   1, 'Schriftliche Prüfungen durchführen', 'Beginn pünktlich, Protokolle führen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '3. Durchführung'),
   2, 'Nachschreibtermine organisieren', 'Atteste prüfen, Ersatzaufgaben bereitstellen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '4. Korrektur und Noten'),
   1, 'Erstkorrektur abschließen', 'Fristen einhalten, Punktsummen prüfen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '4. Korrektur und Noten'),
   2, 'Zweitkorrektur koordinieren', 'Tausch der Arbeiten, Abweichungen dokumentieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '4. Korrektur und Noten'),
   3, 'Noten in System einpflegen', 'Fristen der Schulbehörde beachten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '5. Mündliche Prüfungen'),
   1, 'Mündliche Prüfungen terminieren', 'Prüfungsausschuss einberufen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '5. Mündliche Prüfungen'),
   2, 'Mündliche Prüfungen durchführen', 'Protokolle führen, Beisitzer informieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '5. Mündliche Prüfungen'),
   3, 'Gesamtergebnis berechnen und beschließen', 'Abiturkonferenz, Zeugniserstellung.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '6. Abschluss'),
   1, 'Abiturzeugnisse ausstellen', 'Unterschriften, Siegel, Übergabe vorbereiten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '6. Abschluss'),
   2, 'Entlassungsfeier organisieren', 'Rednerliste, Räume, Technik, Programm.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Abitur-Organisation') AND name = '6. Abschluss'),
   3, 'Prüfungsunterlagen archivieren', 'Aufbewahrungsfristen beachten.');

-- ============================================================
-- 3. iPad- und Geräteausgabe
-- ============================================================
INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES
  ('iPad- und Geräteausgabe', 'Vorbereitung, Ausgabe, Betreuung, Rückgabe', 'system');

INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'), '1. Vorbereitung', '#2980B9', 1),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'), '2. Ausgabe',      '#27AE60', 2),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'), '3. Betreuung',    '#D98A2B', 3),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'), '4. Rückgabe',     '#C0392B', 4);

INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '1. Vorbereitung'),
   1, 'Geräteliste und Seriennummern aktualisieren', 'Bestandsliste mit MDM-System abgleichen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '1. Vorbereitung'),
   2, 'MDM-Profile und Apps aktualisieren', 'Neue Profile deployen, App-Lizenzen prüfen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '1. Vorbereitung'),
   3, 'Nutzungsvereinbarungen vorbereiten', 'Dokumente aktualisieren, Unterschriftenmappe vorbereiten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '1. Vorbereitung'),
   4, 'Ausgabeplan erstellen', 'Klassen, Zeitslots, Räume und Verantwortliche festlegen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '2. Ausgabe'),
   1, 'Nutzungsvereinbarungen unterzeichnen lassen', 'Schüler:innen und Erziehungsberechtigte.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '2. Ausgabe'),
   2, 'Geräte ausgeben und Seriennummern zuordnen', 'Ausgabeliste abhaken, Zuordnung dokumentieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '2. Ausgabe'),
   3, 'Einweisung durchführen', 'Grundfunktionen, Schulregeln, Meldewege bei Defekten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '3. Betreuung'),
   1, 'Defekte Geräte entgegennehmen und dokumentieren', 'Schadensprotokoll, Leihgerät bereitstellen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '3. Betreuung'),
   2, 'Software-Updates einspielen', 'Regelmäßig via MDM, außerhalb des Unterrichts.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '4. Rückgabe'),
   1, 'Rückgabeplan erstellen', 'Klassen, Zeitslots, Räume koordinieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '4. Rückgabe'),
   2, 'Geräte zurücknehmen und prüfen', 'Zustand dokumentieren, Schäden erfassen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '4. Rückgabe'),
   3, 'Geräte zurücksetzen', 'MDM-Enrollment aufheben, Werkseinstellungen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'iPad- und Geräteausgabe') AND name = '4. Rückgabe'),
   4, 'Schadensabrechnung vorbereiten', 'Reparaturkosten ermitteln, mit Eltern klären.');

-- ============================================================
-- 4. DSGVO-Jahresprüfung
-- ============================================================
INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES
  ('DSGVO-Jahresprüfung', 'Dokumentation, Auftragsverarbeiter, technische Maßnahmen, Schulung', 'system');

INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'), '1. Dokumentation',               '#2980B9', 1),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'), '2. Auftragsverarbeiter',          '#8E44AD', 2),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'), '3. Technische Maßnahmen',         '#3D7B6F', 3),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'), '4. Schulung',                     '#D98A2B', 4),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'), '5. Abschluss',                    '#7F8C8D', 5);

INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '1. Dokumentation'),
   1, 'Verzeichnis der Verarbeitungstätigkeiten aktualisieren', 'Art. 30 DSGVO: neue Verarbeitungen ergänzen, weggefallene entfernen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '1. Dokumentation'),
   2, 'Löschkonzept prüfen und anwenden', 'Aufbewahrungsfristen für Schülerdaten, Noten, Fotos prüfen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '1. Dokumentation'),
   3, 'Datenschutzerklärungen aktualisieren', 'Website, Apps, Einwilligungsformulare prüfen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '2. Auftragsverarbeiter'),
   1, 'Liste der Auftragsverarbeiter prüfen', 'Alle genutzten Cloud-Dienste und Tools auflisten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '2. Auftragsverarbeiter'),
   2, 'AVV-Verträge auf Vollständigkeit prüfen', 'Fehlende Auftragsverarbeitungsverträge nachfordern.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '3. Technische Maßnahmen'),
   1, 'Zugriffsrechte prüfen', 'Wer hat Zugriff auf welche Daten? Ausgetretene Lehrkräfte entfernen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '3. Technische Maßnahmen'),
   2, 'Passwort-Richtlinien kontrollieren', 'Ablaufdaten, Komplexitätsanforderungen, 2FA.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '3. Technische Maßnahmen'),
   3, 'Datensicherung prüfen', 'Backup-Konzept testen, Wiederherstellbarkeit verifizieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '4. Schulung'),
   1, 'Kollegium über Datenschutzthemen informieren', 'Konferenzbeitrag oder Rundmail mit Neuerungen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '4. Schulung'),
   2, 'Neue Kolleg:innen datenschutzrechtlich einweisen', 'Schulspezifische Regelungen, Meldepflichten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '5. Abschluss'),
   1, 'Prüfung dokumentieren und ablegen', 'Datum, Ergebnis und offene Punkte festhalten.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'DSGVO-Jahresprüfung') AND name = '5. Abschluss'),
   2, 'Offene Maßnahmen terminieren', 'Verantwortliche benennen, Fristen setzen.');

-- ============================================================
-- 5. Studientag-Organisation
-- ============================================================
INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES
  ('Studientag-Organisation', 'Planung, Vorbereitung, Durchführung, Nachbereitung', 'system');

INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'), '1. Planung',       '#5B6FA8', 1),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'), '2. Vorbereitung',  '#D98A2B', 2),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'), '3. Durchführung',  '#27AE60', 3),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'), '4. Nachbereitung', '#7F8C8D', 4);

INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '1. Planung'),
   1, 'Thema und Ziele festlegen', 'Konferenzbeschluss, pädagogischen Schwerpunkt definieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '1. Planung'),
   2, 'Termin festlegen und genehmigen lassen', 'Schulaufsicht informieren, Eltern rechtzeitig benachrichtigen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '1. Planung'),
   3, 'Referenten anfragen', 'Externe Fachkräfte oder interne Kolleg:innen, Kosten klären.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '2. Vorbereitung'),
   1, 'Räume buchen und einrichten', 'Bestuhlung, Technik, Moderationsmaterial.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '2. Vorbereitung'),
   2, 'Technik prüfen', 'Beamer, Mikrofon, Lautsprecher, Laptop testen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '2. Vorbereitung'),
   3, 'Verpflegung organisieren', 'Kaffee, Snacks, Mittagessen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '2. Vorbereitung'),
   4, 'Kollegium informieren', 'Programm, Räume, Erwartungen mitteilen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '3. Durchführung'),
   1, 'Anwesenheit dokumentieren', 'Unterschriftenliste für Nachweis gegenüber Schulaufsicht.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '3. Durchführung'),
   2, 'Ergebnisse sichern', 'Fotos von Plakaten, digitale Protokolle.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '3. Durchführung'),
   3, 'Feedbackbögen austeilen und einsammeln', 'Anonyme Evaluation zur Qualitätssicherung.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '4. Nachbereitung'),
   1, 'Ergebnisse aufbereiten und verteilen', 'Protokoll ans Kollegium, Beschlüsse in Konferenzmappe.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '4. Nachbereitung'),
   2, 'Maßnahmen aus Beschlüssen verfolgen', 'Verantwortliche benennen, in Jahresplanung aufnehmen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Studientag-Organisation') AND name = '4. Nachbereitung'),
   3, 'Abrechnung erstellen', 'Referentenhonorare, Materialkosten abrechnen.');

-- ============================================================
-- 6. Schuljahresabschluss
-- ============================================================
INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES
  ('Schuljahresabschluss', 'Zeugnisse, Übergaben, IT, Abschlussveranstaltungen, Dokumentation', 'system');

INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'), '1. Zeugnisse',               '#C0392B', 1),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'), '2. Übergaben',               '#D98A2B', 2),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'), '3. IT und Verwaltung',       '#3D7B6F', 3),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'), '4. Abschlussveranstaltungen','#8E44AD', 4),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'), '5. Dokumentation',           '#7F8C8D', 5);

INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung) VALUES
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '1. Zeugnisse'),
   1, 'Notenabgabe-Fristen kommunizieren', 'Letzte Termine für alle Fächer.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '1. Zeugnisse'),
   2, 'Klassenkonferenzen durchführen', 'Versetzungsentscheidungen, besondere Vermerke.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '1. Zeugnisse'),
   3, 'Zeugnisse drucken und unterschreiben', 'Schulleitung, Klassenlehrkraft, Schulstempel.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '1. Zeugnisse'),
   4, 'Zeugnisse ausgeben', 'Ausgabedatum, Empfangsbestätigung bei Abgangszeugnissen.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '2. Übergaben'),
   1, 'Klassenräume übergeben', 'Inventar prüfen, Schäden melden, Schlüssel zurückgeben.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '2. Übergaben'),
   2, 'Lehrmittel und Bücher einsammeln', 'Vollständigkeit prüfen, Schäden dokumentieren.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '3. IT und Verwaltung'),
   1, 'Accounts ausgetretener Schüler:innen sperren', 'Schulnetz, E-Mail, MDM-Geräte.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '3. IT und Verwaltung'),
   2, 'Klassenbücher abschließen und archivieren', 'Digital: Export/Archivierung. Analog: geordnete Ablage.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '3. IT und Verwaltung'),
   3, 'Statistiken und Berichte erstellen', 'Fehlzeiten, Versetzungsquoten, Pflichtstatistiken.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '4. Abschlussveranstaltungen'),
   1, 'Abschlussfeier planen und durchführen', 'Programm, Reden, Musik, Technik, Bewirtung.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '4. Abschlussveranstaltungen'),
   2, 'Lehrerkonferenz zum Abschluss', 'Rückblick, Beschlüsse für nächstes Jahr.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '5. Dokumentation'),
   1, 'Jahresbericht erstellen', 'Überblick über das abgelaufene Jahr für die Schulaufsicht.'),
  ((SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss'),
   (SELECT id FROM vorlagen_set_phasen WHERE set_id = (SELECT id FROM vorlagen_sets WHERE name = 'Schuljahresabschluss') AND name = '5. Dokumentation'),
   2, 'Inventur durchführen', 'Bestandslisten aktualisieren, Bedarfsliste für nächstes Jahr.');
