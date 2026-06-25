<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use App\Guard;
use App\Response;

function handleListSchritte(PDO $db, array $config, array $input): void
{
    $user = Guard::requireLogin($db);

    $prozessId = isset($input['prozess_id']) ? (int) $input['prozess_id'] : null;

    if (!$prozessId) {
        // Fallback: ersten Prozess des Nutzers nehmen (kein aktiv-Flag mehr)
        if ($user['rolle'] === 'admin') {
            $row = $db->query('SELECT id FROM prozesse ORDER BY erstellt_am DESC LIMIT 1')->fetch();
        } else {
            $row = $db->prepare(
                'SELECT p.id FROM prozesse p
                 JOIN prozess_teilnehmer pt ON pt.prozess_id = p.id
                 WHERE pt.webuntis_user = :u
                 ORDER BY p.erstellt_am DESC LIMIT 1'
            );
            $row->execute([':u' => $user['webuntis_user']]);
            $row = $row->fetch();
        }
        $prozessId = $row ? (int) $row['id'] : null;
    }

    if (!$prozessId) {
        Response::json(['prozess_id' => null, 'schritte' => []]);
    }

    // Zugriff prüfen
    Guard::requireProzessZugriff($db, $prozessId);

    $nurAktive = empty($input['alle']); // alle=1 liefert auch deaktivierte (für Verwaltung)

    // Vorlage-basierte Schritte
    $stmt = $db->prepare(
        'SELECT si.id, si.erledigt, si.verantwortlich_user, si.verantwortlich_anzeigename,
                si.start_datum, si.geplantes_datum, si.erledigt_am, si.erledigt_von,
                si.kommentar, si.kann_parallel, si.deaktiviert,
                si.instanz_titel, si.instanz_reihenfolge,
                p.name AS phase, p.farbe AS phase_farbe, p.reihenfolge AS phase_reihenfolge,
                sv.reihenfolge AS vorlage_reihenfolge, sv.titel AS vorlage_titel,
                sv.beschreibung,
                COALESCE(si.instanz_titel, sv.titel)             AS titel,
                COALESCE(si.instanz_reihenfolge, sv.reihenfolge) AS reihenfolge,
                "vorlage" AS quelle
         FROM schritt_instanzen si
         JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         JOIN phasen p ON p.id = sv.phase_id
         WHERE si.prozess_id = :pid' .
        ($nurAktive ? ' AND si.deaktiviert = 0' : '')
    );
    $stmt->execute([':pid' => $prozessId]);
    $vorlagenSchritte = $stmt->fetchAll();

    // Prozessspezifische Schritte (ohne Vorlage)
    $stmtEigen = $db->prepare(
        'SELECT id, erledigt,
                verantwortlich_anzeigename, verantwortlich_anzeigename AS verantwortlich_user,
                start_datum, geplantes_datum,
                erledigt_am, erledigt_von,
                kommentar, 0 AS kann_parallel, deaktiviert,
                NULL AS instanz_titel, NULL AS instanz_reihenfolge,
                phase_name AS phase, phase_farbe, 999 AS phase_reihenfolge,
                reihenfolge AS vorlage_reihenfolge, titel AS vorlage_titel,
                beschreibung,
                titel, reihenfolge,
                "eigen" AS quelle
         FROM instanz_schritte
         WHERE prozess_id = :pid' .
        ($nurAktive ? ' AND deaktiviert = 0' : '')
    );
    $stmtEigen->execute([':pid' => $prozessId]);
    $eigenSchritte = $stmtEigen->fetchAll();

    // Zusammenführen: Vorlage-Schritte zuerst, dann eigene
    $alle = array_merge($vorlagenSchritte, $eigenSchritte);

    Response::json(['prozess_id' => $prozessId, 'schritte' => $alle]);
}

function handleUpdateSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    // Prozess-Zugehörigkeit und Zugriff prüfen
    $infoStmt = $db->prepare(
        'SELECT si.prozess_id, sv.titel, si.vorlage_id
         FROM schritt_instanzen si JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         WHERE si.id = :id'
    );
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch();
    if (!$info) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessZugriff($db, (int) $info['prozess_id']);

    $textfelder = ['verantwortlich_user', 'verantwortlich_anzeigename',
                   'start_datum', 'geplantes_datum', 'kommentar',
                   'instanz_titel', 'instanz_reihenfolge'];
    $sets  = [];
    $werte = [':id' => $id];

    foreach ($textfelder as $feld) {
        if (array_key_exists($feld, $input)) {
            $sets[]          = "$feld = :$feld";
            $werte[":$feld"] = $input[$feld];
        }
    }

    // deaktiviert – nur Verantwortliche und Admins dürfen Schritte deaktivieren
    if (array_key_exists('deaktiviert', $input)) {
        $prozessRolle = Guard::requireProzessZugriff($db, (int) $info['prozess_id']);
        if (in_array($prozessRolle['prozess_rolle'], ['verantwortlich', 'admin'], true)) {
            $sets[]               = 'deaktiviert = :deaktiviert';
            $werte[':deaktiviert'] = $input['deaktiviert'] ? 1 : 0;
        }
    }

    if (array_key_exists('kann_parallel', $input)) {
        $sets[]                  = 'kann_parallel = :kann_parallel';
        $werte[':kann_parallel'] = $input['kann_parallel'] ? 1 : 0;
    }

    if (array_key_exists('erledigt', $input)) {
        $erledigt       = (bool) $input['erledigt'];
        $sets[]         = 'erledigt = :erledigt';
        $werte[':erledigt'] = $erledigt ? 1 : 0;

        if ($erledigt) {
            $sets[] = 'erledigt_am = :erledigt_am';
            $sets[] = 'erledigt_von = :erledigt_von';
            $werte[':erledigt_am']  = (new DateTime())->format(DATE_ATOM);
            $werte[':erledigt_von'] = $user['webuntis_user'];
        } else {
            $sets[] = 'erledigt_am = NULL';
            $sets[] = 'erledigt_von = NULL';
        }
    }

    if (empty($sets)) Response::error('Keine gültigen Felder übergeben.', 400);

    $db->prepare('UPDATE schritt_instanzen SET ' . implode(', ', $sets) . ' WHERE id = :id')
       ->execute($werte);

    // Aktivität protokollieren
    $logStmt = $db->prepare(
        'INSERT INTO aktivitaeten (prozess_id, vorlage_id, schritt_titel, ereignis, wert_neu, benutzer, anzeigename)
         VALUES (:p, :v, :titel, :ereignis, :wert, :benutzer, :name)'
    );

    $ereignisse = [];
    if (array_key_exists('erledigt', $input)) {
        $ereignisse[] = [(bool)$input['erledigt'] ? 'schritt_erledigt' : 'schritt_rueckgaengig', null];
    }
    if (!empty($input['verantwortlich_anzeigename'])) {
        $ereignisse[] = ['verantwortlich_gesetzt', $input['verantwortlich_anzeigename']];
    }
    if (!empty($input['geplantes_datum'])) {
        $ereignisse[] = ['datum_gesetzt', $input['geplantes_datum']];
    }
    if (!empty($input['start_datum'])) {
        $ereignisse[] = ['startdatum_gesetzt', $input['start_datum']];
    }
    if (array_key_exists('kommentar', $input) && $input['kommentar']) {
        $ereignisse[] = ['kommentar_gesetzt', null];
    }

    foreach ($ereignisse as [$ereignis, $wertNeu]) {
        $logStmt->execute([
            ':p'        => $info['prozess_id'],
            ':v'        => $info['vorlage_id'],
            ':titel'    => $info['titel'],
            ':ereignis' => $ereignis,
            ':wert'     => $wertNeu,
            ':benutzer' => $user['webuntis_user'],
            ':name'     => $user['anzeigename'],
        ]);
    }

    Response::json(['ok' => true]);
}

// ============================================================================
// Prozessspezifische Schritte (instanz_schritte)
// Ein Verantwortlicher kann eigene Schritte anlegen die nur in seinem Prozess
// erscheinen – ohne die globale Vorlage zu berühren.
// ============================================================================

function handleListInstanzSchritte(PDO $db, array $config, array $input): void
{
    $user      = Guard::requireLogin($db);
    $prozessId = isset($input['prozess_id']) ? (int) $input['prozess_id'] : null;
    if (!$prozessId) Response::error('prozess_id erforderlich.', 400);

    Guard::requireProzessZugriff($db, $prozessId);

    $stmt = $db->prepare(
        'SELECT id, phase_name, phase_farbe, reihenfolge, titel, beschreibung,
                erledigt, erledigt_am, erledigt_von, kommentar, deaktiviert
         FROM instanz_schritte
         WHERE prozess_id = :pid AND deaktiviert = 0
         ORDER BY reihenfolge, id'
    );
    $stmt->execute([':pid' => $prozessId]);
    Response::json($stmt->fetchAll());
}

function handleCreateInstanzSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user      = Guard::requireLogin($db);
    $prozessId = (int) $params['prozess_id'];

    // Nur Verantwortliche und Admins dürfen neue prozessspezifische Schritte anlegen
    $zugangsDaten = Guard::requireProzessVerantwortlich($db, $prozessId);

    $titel     = trim((string) ($input['titel'] ?? ''));
    $phaseName = trim((string) ($input['phase_name'] ?? 'Eigene Schritte'));
    $phaseFarbe = $input['phase_farbe'] ?? '#7F8C8D';
    if ($titel === '') Response::error('titel erforderlich.', 400);
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $phaseFarbe)) $phaseFarbe = '#7F8C8D';

    $maxR = (int) $db->query(
        "SELECT COALESCE(MAX(reihenfolge), 0) FROM instanz_schritte
         WHERE prozess_id = $prozessId"
    )->fetchColumn();

    $db->prepare(
        'INSERT INTO instanz_schritte
         (prozess_id, phase_name, phase_farbe, reihenfolge, titel, beschreibung, erstellt_von)
         VALUES (:pid, :pname, :pfarbe, :r, :titel, :beschreibung, :von)'
    )->execute([
        ':pid'         => $prozessId,
        ':pname'       => $phaseName,
        ':pfarbe'      => $phaseFarbe,
        ':r'           => $maxR + 1,
        ':titel'       => $titel,
        ':beschreibung' => $input['beschreibung'] ?? null,
        ':von'         => $user['webuntis_user'],
    ]);

    Response::json(['id' => (int) $db->lastInsertId()], 201);
}

function handleUpdateInstanzSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    $infoStmt = $db->prepare(
        'SELECT prozess_id FROM instanz_schritte WHERE id = :id'
    );
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch();
    if (!$info) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessZugriff($db, (int) $info['prozess_id']);

    $sets = []; $werte = [':id' => $id];
    $felder = ['titel', 'beschreibung', 'phase_name', 'phase_farbe',
               'reihenfolge', 'kommentar',
               'verantwortlich_anzeigename', 'start_datum', 'geplantes_datum'];
    foreach ($felder as $f) {
        if (array_key_exists($f, $input)) {
            $sets[] = "$f = :$f"; $werte[":$f"] = $input[$f];
        }
    }
    if (array_key_exists('erledigt', $input)) {
        $sets[] = 'erledigt = :erledigt';
        $werte[':erledigt'] = $input['erledigt'] ? 1 : 0;
        if ($input['erledigt']) {
            $sets[] = 'erledigt_am = :ea'; $sets[] = 'erledigt_von = :ev';
            $werte[':ea'] = (new DateTime())->format(DATE_ATOM);
            $werte[':ev'] = $user['webuntis_user'];
        } else {
            $sets[] = 'erledigt_am = NULL'; $sets[] = 'erledigt_von = NULL';
        }
    }
    if (array_key_exists('deaktiviert', $input)) {
        $sets[] = 'deaktiviert = :deaktiviert';
        $werte[':deaktiviert'] = $input['deaktiviert'] ? 1 : 0;
    }
    if (empty($sets)) Response::error('Keine Felder übergeben.', 400);

    $db->prepare('UPDATE instanz_schritte SET ' . implode(', ', $sets) .
        ' WHERE id = :id')->execute($werte);
    Response::json(['ok' => true]);
}

function handleDeleteInstanzSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    $infoStmt = $db->prepare('SELECT prozess_id FROM instanz_schritte WHERE id = :id');
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch();
    if (!$info) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessVerantwortlich($db, (int) $info['prozess_id']);
    $db->prepare('DELETE FROM instanz_schritte WHERE id = :id')->execute([':id' => $id]);
    Response::json(['ok' => true]);
}
