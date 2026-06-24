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

    $stmt = $db->prepare(
        'SELECT si.id, si.erledigt, si.verantwortlich_user, si.verantwortlich_anzeigename,
                si.start_datum, si.geplantes_datum, si.erledigt_am, si.erledigt_von,
                si.kommentar, si.kann_parallel,
                p.name AS phase, p.farbe AS phase_farbe, p.reihenfolge AS phase_reihenfolge,
                sv.reihenfolge, sv.titel, sv.beschreibung
         FROM schritt_instanzen si
         JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         JOIN phasen p ON p.id = sv.phase_id
         WHERE si.prozess_id = :pid
         ORDER BY p.reihenfolge, sv.reihenfolge'
    );
    $stmt->execute([':pid' => $prozessId]);

    Response::json(['prozess_id' => $prozessId, 'schritte' => $stmt->fetchAll()]);
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
                   'start_datum', 'geplantes_datum', 'kommentar'];
    $sets  = [];
    $werte = [':id' => $id];

    foreach ($textfelder as $feld) {
        if (array_key_exists($feld, $input)) {
            $sets[]          = "$feld = :$feld";
            $werte[":$feld"] = $input[$feld];
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
