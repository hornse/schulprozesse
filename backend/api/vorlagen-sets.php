<?php
/*
 * Schuljahreswechsel WebUntis
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
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
