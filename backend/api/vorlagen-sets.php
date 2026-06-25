<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Endpunkte:
 *   GET    /api/vorlagen-sets           – alle Snapshots auflisten
 *   POST   /api/vorlagen-sets           – aktuellen Stand als Snapshot speichern
 *   DELETE /api/vorlagen-sets/{id}      – Snapshot löschen
 *   GET    /api/vorlagen-sets/{id}      – Snapshot-Details (Phasen + Schritte)
 *
 * Die eigentliche Verwendung eines Snapshots beim Anlegen eines Schuljahres
 * läuft über POST /api/schuljahre mit einem optionalen "set_id"-Parameter
 * (siehe schuljahre.php).
 *
 * Alles hier ist admin-only.
 */

use App\Guard;
use App\Response;

function handleListVorlagenSets(PDO $db): void
{
    Guard::requireAdmin($db);

    $rows = $db->query(
        'SELECT vs.id, vs.name, vs.beschreibung, vs.erstellt_am, vs.erstellt_von,
                COUNT(vss.id) AS schritt_anzahl
         FROM vorlagen_sets vs
         LEFT JOIN vorlagen_set_schritte vss ON vss.set_id = vs.id
         GROUP BY vs.id
         ORDER BY vs.erstellt_am DESC'
    )->fetchAll();

    Response::json($rows);
}

function handleGetVorlagenSet(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];

    $set = $db->prepare('SELECT id, name, beschreibung, erstellt_am, erstellt_von FROM vorlagen_sets WHERE id = :id');
    $set->execute([':id' => $id]);
    $setRow = $set->fetch();
    if (!$setRow) {
        Response::error('Snapshot nicht gefunden.', 404);
    }

    $phasen = $db->prepare(
        'SELECT id, name, farbe, reihenfolge FROM vorlagen_set_phasen WHERE set_id = :id ORDER BY reihenfolge'
    );
    $phasen->execute([':id' => $id]);

    $schritte = $db->prepare(
        'SELECT id, set_phase_id, reihenfolge, titel, beschreibung, kann_parallel
         FROM vorlagen_set_schritte WHERE set_id = :id ORDER BY reihenfolge'
    );
    $schritte->execute([':id' => $id]);

    Response::json([
        'set'      => $setRow,
        'phasen'   => $phasen->fetchAll(),
        'schritte' => $schritte->fetchAll(),
    ]);
}

/**
 * Friert den aktuellen Stand (phasen + aktive schritt_vorlagen) als
 * benannten Snapshot ein. Der Snapshot ist danach unabhängig – spätere
 * Änderungen an der aktiven Vorlage verändern ihn nicht.
 */
function handleCreateVorlagenSet(PDO $db, array $config, array $input): void
{
    $user = Guard::requireAdmin($db);

    $name        = trim((string) ($input['name'] ?? ''));
    $beschreibung = $input['beschreibung'] ?? null;

    if ($name === '') {
        Response::error('name ist erforderlich.', 400);
    }

    $db->beginTransaction();
    try {
        // Snapshot-Kopf anlegen
        $db->prepare(
            'INSERT INTO vorlagen_sets (name, beschreibung, erstellt_von) VALUES (:name, :beschreibung, :von)'
        )->execute([':name' => $name, ':beschreibung' => $beschreibung, ':von' => $user['webuntis_user']]);
        $setId = (int) $db->lastInsertId();

        // Phasen einfrieren – Mapping old phase.id → new set_phase.id
        $phasen = $db->query('SELECT id, name, farbe, reihenfolge FROM phasen ORDER BY reihenfolge')->fetchAll();
        $phaseIdMap = [];
        $insertPhase = $db->prepare(
            'INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge) VALUES (:set, :name, :farbe, :r)'
        );
        foreach ($phasen as $phase) {
            $insertPhase->execute([
                ':set'  => $setId,
                ':name' => $phase['name'],
                ':farbe' => $phase['farbe'],
                ':r'    => $phase['reihenfolge'],
            ]);
            $phaseIdMap[$phase['id']] = (int) $db->lastInsertId();
        }

        // Aktive Schritte einfrieren
        $schritte = $db->query(
            'SELECT phase_id, reihenfolge, titel, beschreibung, kann_parallel
             FROM schritt_vorlagen WHERE aktiv = 1 ORDER BY phase_id, reihenfolge'
        )->fetchAll();
        $insertSchritt = $db->prepare(
            'INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung, kann_parallel)
             VALUES (:set, :phase, :r, :titel, :beschreibung, :kp)'
        );
        foreach ($schritte as $schritt) {
            $setPhasenId = $phaseIdMap[$schritt['phase_id']] ?? null;
            if (!$setPhasenId) continue; // Sicherheitsnetz, sollte nicht vorkommen

            $insertSchritt->execute([
                ':set'         => $setId,
                ':phase'       => $setPhasenId,
                ':r'           => $schritt['reihenfolge'],
                ':titel'       => $schritt['titel'],
                ':beschreibung' => $schritt['beschreibung'],
                ':kp'          => $schritt['kann_parallel'],
            ]);
        }

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    Response::json(['id' => $setId], 201);
}

function handleDeleteVorlagenSet(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];

    $check = $db->prepare('SELECT id FROM vorlagen_sets WHERE id = :id');
    $check->execute([':id' => $id]);
    if (!$check->fetchColumn()) {
        Response::error('Snapshot nicht gefunden.', 404);
    }

    // ON DELETE CASCADE löscht vorlagen_set_phasen und vorlagen_set_schritte mit
    $db->prepare('DELETE FROM vorlagen_sets WHERE id = :id')->execute([':id' => $id]);
    Response::json(['ok' => true]);
}

// ============================================================================
// Snapshot-Bearbeitung: Phasen und Schritte eines Snapshots editieren
// Bestehende Prozess-Instanzen werden NICHT berührt – nur neue Prozesse
// die aus diesem Snapshot erstellt werden, nutzen die aktualisierte Version.
// ============================================================================

function handleCreateSetPhase(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId = (int) $params['id'];

    $name  = trim((string) ($input['name'] ?? ''));
    $farbe = $input['farbe'] ?? '#5B6FA8';
    if ($name === '') Response::error('name ist erforderlich.', 400);
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $farbe)) $farbe = '#5B6FA8';

    // Reihenfolge: ans Ende
    $maxR = (int) $db->prepare(
        'SELECT COALESCE(MAX(reihenfolge), 0) FROM vorlagen_set_phasen WHERE set_id = :id'
    )->execute([':id' => $setId]) ? $db->query(
        "SELECT COALESCE(MAX(reihenfolge), 0) FROM vorlagen_set_phasen WHERE set_id = $setId"
    )->fetchColumn() : 0;

    $db->prepare(
        'INSERT INTO vorlagen_set_phasen (set_id, name, farbe, reihenfolge)
         VALUES (:set, :name, :farbe, :r)'
    )->execute([':set' => $setId, ':name' => $name, ':farbe' => $farbe, ':r' => $maxR + 1]);

    Response::json(['id' => (int) $db->lastInsertId(), 'name' => $name], 201);
}

function handleUpdateSetPhase(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId   = (int) $params['id'];
    $phaseId = (int) $params['pid'];

    $sets = []; $werte = [':id' => $phaseId, ':set' => $setId];
    if (isset($input['name'])  && trim($input['name']) !== '') {
        $sets[] = 'name = :name'; $werte[':name'] = trim($input['name']);
    }
    if (isset($input['farbe']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $input['farbe'])) {
        $sets[] = 'farbe = :farbe'; $werte[':farbe'] = $input['farbe'];
    }
    if (empty($sets)) Response::error('Keine gültigen Felder.', 400);

    $db->prepare('UPDATE vorlagen_set_phasen SET ' . implode(', ', $sets) .
        ' WHERE id = :id AND set_id = :set')->execute($werte);
    Response::json(['ok' => true]);
}

function handleDeleteSetPhase(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId   = (int) $params['id'];
    $phaseId = (int) $params['pid'];

    // Schritte dieser Phase zuerst löschen
    $db->prepare('DELETE FROM vorlagen_set_schritte WHERE set_id = :set AND set_phase_id = :pid')
       ->execute([':set' => $setId, ':pid' => $phaseId]);
    $db->prepare('DELETE FROM vorlagen_set_phasen WHERE id = :id AND set_id = :set')
       ->execute([':id' => $phaseId, ':set' => $setId]);

    Response::json(['ok' => true]);
}

function handleCreateSetSchritt(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId   = (int) $params['id'];
    $phaseId = (int) $params['pid'];

    $titel = trim((string) ($input['titel'] ?? ''));
    if ($titel === '') Response::error('titel ist erforderlich.', 400);

    $maxR = (int) $db->query(
        "SELECT COALESCE(MAX(reihenfolge), 0) FROM vorlagen_set_schritte
         WHERE set_id = $setId AND set_phase_id = $phaseId"
    )->fetchColumn();

    $db->prepare(
        'INSERT INTO vorlagen_set_schritte (set_id, set_phase_id, reihenfolge, titel, beschreibung, kann_parallel)
         VALUES (:set, :pid, :r, :titel, :beschreibung, :kp)'
    )->execute([
        ':set'         => $setId,
        ':pid'         => $phaseId,
        ':r'           => $maxR + 1,
        ':titel'       => $titel,
        ':beschreibung' => $input['beschreibung'] ?? null,
        ':kp'          => 0,
    ]);

    Response::json(['id' => (int) $db->lastInsertId(), 'titel' => $titel], 201);
}

function handleUpdateSetSchritt(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId     = (int) $params['id'];
    $schrittId = (int) $params['sid'];

    $sets = []; $werte = [':id' => $schrittId, ':set' => $setId];
    if (isset($input['titel']) && trim($input['titel']) !== '') {
        $sets[] = 'titel = :titel'; $werte[':titel'] = trim($input['titel']);
    }
    if (array_key_exists('beschreibung', $input)) {
        $sets[] = 'beschreibung = :beschreibung'; $werte[':beschreibung'] = $input['beschreibung'];
    }
    if (array_key_exists('kann_parallel', $input)) {
        $sets[] = 'kann_parallel = :kp'; $werte[':kp'] = $input['kann_parallel'] ? 1 : 0;
    }
    if (array_key_exists('reihenfolge', $input)) {
        $sets[] = 'reihenfolge = :r'; $werte[':r'] = (int) $input['reihenfolge'];
    }
    if (empty($sets)) Response::error('Keine gültigen Felder.', 400);

    $db->prepare('UPDATE vorlagen_set_schritte SET ' . implode(', ', $sets) .
        ' WHERE id = :id AND set_id = :set')->execute($werte);
    Response::json(['ok' => true]);
}

function handleDeleteSetSchritt(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId     = (int) $params['id'];
    $schrittId = (int) $params['sid'];

    $db->prepare('DELETE FROM vorlagen_set_schritte WHERE id = :id AND set_id = :set')
       ->execute([':id' => $schrittId, ':set' => $setId]);
    Response::json(['ok' => true]);
}

function handleReihenfolgeSetSchritte(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId   = (int) $params['id'];
    $phaseId = (int) $params['pid'];
    $ids     = $input['schritt_ids'] ?? [];

    if (empty($ids)) Response::error('schritt_ids erforderlich.', 400);

    $stmt = $db->prepare(
        'UPDATE vorlagen_set_schritte SET reihenfolge = :r WHERE id = :id AND set_id = :set'
    );
    foreach ($ids as $i => $sid) {
        $stmt->execute([':r' => $i + 1, ':id' => (int) $sid, ':set' => $setId]);
    }
    Response::json(['ok' => true]);
}

function handleReihenfolgeSetPhasen(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $setId = (int) $params['id'];
    $ids   = $input['phasen_ids'] ?? [];

    if (empty($ids)) Response::error('phasen_ids erforderlich.', 400);

    $stmt = $db->prepare(
        'UPDATE vorlagen_set_phasen SET reihenfolge = :r WHERE id = :id AND set_id = :set'
    );
    foreach ($ids as $i => $pid) {
        $stmt->execute([':r' => $i + 1, ':id' => (int) $pid, ':set' => $setId]);
    }
    Response::json(['ok' => true]);
}
