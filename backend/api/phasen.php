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
 * Endpunkte: GET /api/phasen, POST /api/phasen,
 *            PATCH /api/phasen/{id}, POST /api/phasen/reihenfolge
 *
 * Phasen sind jetzt eigenständige Datensätze, nicht mehr nur ein
 * Textfeld an der Vorlage. Das erlaubt:
 *   - Umbenennen einer Phase ohne jede einzelne Vorlage anzufassen
 *   - Farbe zentral am Phasen-Datensatz pflegen
 *   - Explizite Reihenfolge (unabhängig vom Textnamen)
 *   - Neue Phasen anlegen und in den Ablauf einfügen
 *
 * Alles hier ist admin-only.
 */

use App\Guard;
use App\Response;

function handleListPhasen(PDO $db): void
{
    Guard::requireAdmin($db);
    $rows = $db->query(
        'SELECT id, name, farbe, reihenfolge FROM phasen ORDER BY reihenfolge'
    )->fetchAll();
    Response::json($rows);
}

function handleCreatePhase(PDO $db, array $config, array $input): void
{
    Guard::requireAdmin($db);

    $name  = trim((string) ($input['name'] ?? ''));
    $farbe = trim((string) ($input['farbe'] ?? '')) ?: '#5B6FA8';

    if ($name === '') {
        Response::error('name ist erforderlich.', 400);
    }

    $maxReihenfolge = (int) $db->query('SELECT COALESCE(MAX(reihenfolge), 0) FROM phasen')->fetchColumn();

    $db->prepare(
        'INSERT INTO phasen (name, farbe, reihenfolge) VALUES (:name, :farbe, :r)'
    )->execute([':name' => $name, ':farbe' => $farbe, ':r' => $maxReihenfolge + 1]);

    Response::json(['id' => (int) $db->lastInsertId()], 201);
}

function handleUpdatePhase(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];

    $sets  = [];
    $werte = [':id' => $id];

    foreach (['name', 'farbe'] as $feld) {
        if (array_key_exists($feld, $input)) {
            $sets[]          = "$feld = :$feld";
            $werte[":$feld"] = $input[$feld];
        }
    }

    if (empty($sets)) {
        Response::error('Keine gültigen Felder (name, farbe).', 400);
    }

    $db->prepare('UPDATE phasen SET ' . implode(', ', $sets) . ' WHERE id = :id')
       ->execute($werte);

    Response::json(['ok' => true]);
}

/**
 * Bulk-Umsortierung der Phasen selbst (Drag-and-Drop der Phasen-Überschriften
 * im Frontend). Bekommt das komplette Array aller Phasen-IDs in gewünschter
 * neuer Reihenfolge.
 */
function handleReihenfolgePhasen(PDO $db, array $config, array $input): void
{
    Guard::requireAdmin($db);

    $ids = $input['phasen_ids'] ?? null;
    if (!is_array($ids) || empty($ids)) {
        Response::error('phasen_ids (nicht-leeres Array) ist erforderlich.', 400);
    }

    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE phasen SET reihenfolge = :r WHERE id = :id');
    foreach (array_values($ids) as $index => $id) {
        $stmt->execute([':r' => $index + 1, ':id' => (int) $id]);
    }
    $db->commit();

    Response::json(['ok' => true]);
}

function handleDeletePhase(PDO $db, array $config, array $input, array $params): void
{
    // Admins und Verantwortliche (für mindestens einen Prozess) dürfen Phasen löschen
    $user = Guard::requireLogin($db);
    if ($user['rolle'] !== 'admin') {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM prozess_teilnehmer
             WHERE webuntis_user = :u AND rolle = 'verantwortlich'"
        );
        $stmt->execute([':u' => $user['webuntis_user']]);
        $istVerantwortlich = (int) $stmt->fetchColumn();

        if (!$istVerantwortlich) {
            Response::error('Nur Admins und Verantwortliche können Phasen löschen.', 403);
        }
    }

    $id = (int) $params['id'];

    // Alle Schritte der Phase und ihre Instanzen löschen (CASCADE)
    $db->beginTransaction();
    try {
        // Instanzen löschen
        $vorlagenIds = $db->prepare(
            'SELECT id FROM schritt_vorlagen WHERE phase_id = :id'
        );
        $vorlagenIds->execute([':id' => $id]);
        foreach ($vorlagenIds->fetchAll() as $v) {
            $db->prepare('DELETE FROM schritt_instanzen WHERE vorlage_id = :vid')
               ->execute([':vid' => $v['id']]);
        }
        // Vorlagen löschen
        $db->prepare('DELETE FROM schritt_vorlagen WHERE phase_id = :id')
           ->execute([':id' => $id]);
        // Phase löschen
        $db->prepare('DELETE FROM phasen WHERE id = :id')
           ->execute([':id' => $id]);
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    Response::json(['ok' => true]);
}
