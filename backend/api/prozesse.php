<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use App\Guard;
use App\Response;

/**
 * GET  /api/prozesse               – alle Prozesse des eingeloggten Nutzers
 * POST /api/prozesse               – neuen Prozess anlegen (admin only)
 * PATCH /api/prozesse/{id}         – Prozess bearbeiten (admin oder verantwortlich)
 * POST /api/prozesse/{id}/aktivieren – Prozess aktivieren (admin)
 * GET  /api/prozesse/{id}/teilnehmer – Teilnehmer auflisten
 * POST /api/prozesse/{id}/teilnehmer – Teilnehmer hinzufügen/ändern
 * DELETE /api/prozesse/{id}/teilnehmer/{user} – Teilnehmer entfernen
 */

function handleListProzesse(PDO $db, array $config, array $input): void
{
    $user = Guard::requireLogin($db);
    // mit_archiv=1 liefert auch archivierte Prozesse (nur Admin-Archiv-Ansicht)
    $mitArchiv   = !empty($input['mit_archiv']);
    $aktivFilter = $mitArchiv ? '' : 'AND p.aktiv = 1';

    if ($user['rolle'] === 'admin') {
        $rows = $db->query(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM schritt_instanzen si WHERE si.prozess_id = p.id) as schritt_anzahl,
                    (SELECT COUNT(*) FROM schritt_instanzen si WHERE si.prozess_id = p.id AND si.erledigt = 1) as erledigt_anzahl,
                    (SELECT COUNT(*) FROM prozess_teilnehmer pt WHERE pt.prozess_id = p.id) as teilnehmer_anzahl,
                    'admin' as meine_rolle
             FROM prozesse p
             WHERE 1=1 $aktivFilter
             ORDER BY p.erstellt_am DESC"
        )->fetchAll();
    } else {
        $stmt = $db->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM schritt_instanzen si WHERE si.prozess_id = p.id) as schritt_anzahl,
                    (SELECT COUNT(*) FROM schritt_instanzen si WHERE si.prozess_id = p.id AND si.erledigt = 1) as erledigt_anzahl,
                    (SELECT COUNT(*) FROM prozess_teilnehmer pt WHERE pt.prozess_id = p.id) as teilnehmer_anzahl,
                    pt.rolle as meine_rolle
             FROM prozesse p
             JOIN prozess_teilnehmer pt ON pt.prozess_id = p.id AND pt.webuntis_user = :u
             WHERE 1=1 $aktivFilter
             ORDER BY p.erstellt_am DESC"
        );
        $stmt->execute([':u' => $user['webuntis_user']]);
        $rows = $stmt->fetchAll();
    }

    Response::json($rows);
}

function handleCreateProzess(PDO $db, array $config, array $input): void
{
    $user = Guard::requireAdmin($db);

    $label       = trim((string) ($input['label'] ?? ''));
    $beschreibung = $input['beschreibung'] ?? null;
    $oeffentlich = isset($input['oeffentlich']) ? (int) (bool) $input['oeffentlich'] : 1;
    $setId = isset($input['set_id']) ? $input['set_id'] : null;
    // 'leer' = leerer Prozess ohne Schritte; null = aktuelle Vorlage; int = Snapshot
    $leer  = $setId === 'leer';
    $setId = (!$leer && $setId !== null) ? (int) $setId : null;

    if ($label === '') Response::error('label ist erforderlich.', 400);

    $db->beginTransaction();
    try {
        // Kein aktiv=0 für alle anderen – mehrere Prozesse können gleichzeitig aktiv sein
        // aktiv=1 bedeutet nur "wird zuerst angezeigt beim Laden"

        $db->prepare(
            'INSERT INTO prozesse (label, beschreibung, aktiv, oeffentlich, erstellt_von)
             VALUES (:label, :beschreibung, 1, :oeffentlich, :von)'
        )->execute([
            ':label'       => $label,
            ':beschreibung' => $beschreibung,
            ':oeffentlich'  => $oeffentlich,
            ':von'         => $user['webuntis_user'],
        ]);
        $prozessId = (int) $db->lastInsertId();

        // Anleger automatisch als Verantwortlichen eintragen
        $db->prepare(
            'INSERT INTO prozess_teilnehmer (prozess_id, webuntis_user, rolle) VALUES (:p, :u, "verantwortlich")'
        )->execute([':p' => $prozessId, ':u' => $user['webuntis_user']]);

        // Instanzen anlegen
        if ($leer) {
            // Leerer Prozess – keine Schritte, Admin trägt sie selbst ein
        } elseif ($setId) {
            // Aus Snapshot
            _instanzenAusSnapshot($db, $prozessId, $setId);
        } else {
            // Aus aktiver Vorlage
            $vorlagen = $db->query('SELECT id, kann_parallel FROM schritt_vorlagen WHERE aktiv = 1')->fetchAll();
            $ins = $db->prepare(
                'INSERT INTO schritt_instanzen (prozess_id, vorlage_id, kann_parallel) VALUES (:p, :v, :kp)'
            );
            foreach ($vorlagen as $v) {
                $ins->execute([':p' => $prozessId, ':v' => $v['id'], ':kp' => $v['kann_parallel']]);
            }
        }

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    Response::json(['id' => $prozessId, 'label' => $label], 201);
}

function handleUpdateProzess(PDO $db, array $config, array $input, array $params): void
{
    $id   = (int) $params['id'];
    $user = Guard::requireProzessVerantwortlich($db, $id);

    $sets  = [];
    $werte = [':id' => $id];

    if (array_key_exists('label', $input) && trim($input['label']) !== '') {
        $sets[]         = 'label = :label';
        $werte[':label'] = trim($input['label']);
    }
    if (array_key_exists('beschreibung', $input)) {
        $sets[]                = 'beschreibung = :beschreibung';
        $werte[':beschreibung'] = $input['beschreibung'];
    }
    if (array_key_exists('oeffentlich', $input)) {
        $sets[]               = 'oeffentlich = :oeffentlich';
        $werte[':oeffentlich'] = (int) (bool) $input['oeffentlich'];
    }

    if (empty($sets)) Response::error('Keine gültigen Felder übergeben.', 400);

    $db->prepare('UPDATE prozesse SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($werte);
    Response::json(['ok' => true]);
}

function handleActivateProzess(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];

    // aktiv=1 setzt nur den Standard-Prozess (wird zuerst angezeigt)
    // andere Prozesse bleiben wie sie sind
    $db->prepare('UPDATE prozesse SET aktiv = 1 WHERE id = :id')->execute([':id' => $id]);
    Response::json(['ok' => true]);
}

function handleListTeilnehmer(PDO $db, array $config, array $input, array $params): void
{
    $id = (int) $params['id'];
    Guard::requireProzessZugriff($db, $id);

    $stmt = $db->prepare(
        'SELECT pt.webuntis_user, br.anzeigename, pt.rolle
         FROM prozess_teilnehmer pt
         LEFT JOIN benutzer_rollen br ON br.webuntis_user = pt.webuntis_user
         WHERE pt.prozess_id = :id ORDER BY pt.rolle, br.anzeigename'
    );
    $stmt->execute([':id' => $id]);
    Response::json($stmt->fetchAll());
}

function handleUpsertTeilnehmer(PDO $db, array $config, array $input, array $params): void
{
    $id   = (int) $params['id'];
    Guard::requireProzessVerantwortlich($db, $id);

    $webuntis_user = trim((string) ($input['webuntis_user'] ?? ''));
    $rolle         = $input['rolle'] ?? 'mitarbeitend';

    if ($webuntis_user === '' || !in_array($rolle, ['verantwortlich', 'mitarbeitend'], true)) {
        Response::error('webuntis_user und gültige rolle erforderlich.', 400);
    }

    // Prüfen ob Person in benutzer_rollen existiert
    $check = $db->prepare('SELECT webuntis_user FROM benutzer_rollen WHERE webuntis_user = :u');
    $check->execute([':u' => $webuntis_user]);
    if (!$check->fetchColumn()) {
        Response::error('Person nicht in der Zugriffsliste. Bitte zuerst unter "Zugriff" freigeben.', 404);
    }

    $db->prepare(
        'INSERT INTO prozess_teilnehmer (prozess_id, webuntis_user, rolle)
         VALUES (:p, :u, :r)
         ON CONFLICT(prozess_id, webuntis_user) DO UPDATE SET rolle = :r'
    )->execute([':p' => $id, ':u' => $webuntis_user, ':r' => $rolle]);

    Response::json(['ok' => true]);
}

function handleDeleteTeilnehmer(PDO $db, array $config, array $input, array $params): void
{
    $id   = (int) $params['id'];
    $user = Guard::requireProzessVerantwortlich($db, $id);

    $zuEntfernen = urldecode($params['user']);

    // Mindestens ein Verantwortlicher muss bleiben
    if ($zuEntfernen !== $user['webuntis_user']) {
        $anzahlVerantwortliche = (int) $db->prepare(
            'SELECT COUNT(*) FROM prozess_teilnehmer WHERE prozess_id = :p AND rolle = "verantwortlich"'
        )->execute([':p' => $id]) ? $db->query(
            "SELECT COUNT(*) FROM prozess_teilnehmer WHERE prozess_id = $id AND rolle = 'verantwortlich'"
        )->fetchColumn() : 0;

        $istVerantwortlicher = $db->prepare(
            'SELECT rolle FROM prozess_teilnehmer WHERE prozess_id = :p AND webuntis_user = :u'
        );
        $istVerantwortlicher->execute([':p' => $id, ':u' => $zuEntfernen]);
        $rolleZuEntfernen = $istVerantwortlicher->fetchColumn();

        if ($rolleZuEntfernen === 'verantwortlich' && $anzahlVerantwortliche <= 1) {
            Response::error('Der letzte Verantwortliche kann nicht entfernt werden.', 403);
        }
    }

    $db->prepare(
        'DELETE FROM prozess_teilnehmer WHERE prozess_id = :p AND webuntis_user = :u'
    )->execute([':p' => $id, ':u' => $zuEntfernen]);

    Response::json(['ok' => true]);
}

function _instanzenAusSnapshot(PDO $db, int $prozessId, int $setId): void
{
    $setPhasen = $db->prepare(
        'SELECT id, name, farbe, reihenfolge FROM vorlagen_set_phasen WHERE set_id = :sid ORDER BY reihenfolge'
    );
    $setPhasen->execute([':sid' => $setId]);
    $phasenAusSet = $setPhasen->fetchAll();

    if (empty($phasenAusSet)) return;

    $insertPhase = $db->prepare(
        'INSERT OR IGNORE INTO phasen (name, farbe, reihenfolge) VALUES (:name, :farbe, :r)'
    );
    $findPhase = $db->prepare('SELECT id FROM phasen WHERE name = :name');
    $insertVorlage = $db->prepare(
        'INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, kann_parallel)
         VALUES (:phase_id, :r, :titel, :beschreibung, :kp)'
    );
    $insertInstanz = $db->prepare(
        'INSERT INTO schritt_instanzen (prozess_id, vorlage_id, kann_parallel) VALUES (:p, :v, :kp)'
    );

    foreach ($phasenAusSet as $setPhase) {
        $insertPhase->execute([':name' => $setPhase['name'], ':farbe' => $setPhase['farbe'], ':r' => $setPhase['reihenfolge']]);
        $findPhase->execute([':name' => $setPhase['name']]);
        $phaseId = (int) $findPhase->fetchColumn();

        $setSchritte = $db->prepare(
            'SELECT reihenfolge, titel, beschreibung, kann_parallel
             FROM vorlagen_set_schritte WHERE set_id = :sid AND set_phase_id = :pid ORDER BY reihenfolge'
        );
        $setSchritte->execute([':sid' => $setId, ':pid' => $setPhase['id']]);

        foreach ($setSchritte->fetchAll() as $ss) {
            $insertVorlage->execute([
                ':phase_id'     => $phaseId,
                ':r'            => $ss['reihenfolge'],
                ':titel'        => $ss['titel'],
                ':beschreibung' => $ss['beschreibung'],
                ':kp'           => $ss['kann_parallel'],
            ]);
            $vorlageId = (int) $db->lastInsertId();
            $insertInstanz->execute([':p' => $prozessId, ':v' => $vorlageId, ':kp' => $ss['kann_parallel']]);
        }
    }
}

function handleArchivierenProzess(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];
    $db->prepare('UPDATE prozesse SET aktiv = 0 WHERE id = :id')->execute([':id' => $id]);
    Response::json(['ok' => true]);
}

function handleReaktivierenProzess(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];
    $db->prepare('UPDATE prozesse SET aktiv = 1 WHERE id = :id')->execute([':id' => $id]);
    Response::json(['ok' => true]);
}
