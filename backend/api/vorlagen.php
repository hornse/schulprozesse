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
 * Endpunkte: GET /api/vorlagen, POST /api/vorlagen,
 *            PATCH /api/vorlagen/{id}, POST /api/vorlagen/reihenfolge
 *
 * Verwaltung der wiederkehrenden Schritt-VORLAGE selbst (nicht der
 * Instanzen für ein einzelnes Schuljahr - dafür ist schritte.php
 * zuständig). Alles hier ist admin-only, weil es die Arbeitsgrundlage
 * für künftige (und bei Neuanlage/Reihenfolge auch das aktuelle)
 * Schuljahre verändert.
 */

use App\Guard;
use App\Response;

function handleListVorlagen(PDO $db): void
{
    Guard::requireAdmin($db);
    $rows = $db->query(
        'SELECT sv.id, sv.phase_id, p.name AS phase, p.farbe AS phase_farbe,
                p.reihenfolge AS phase_reihenfolge, sv.reihenfolge, sv.titel,
                sv.beschreibung, sv.aktiv, sv.kann_parallel
         FROM schritt_vorlagen sv
         JOIN phasen p ON p.id = sv.phase_id
         ORDER BY p.reihenfolge, sv.reihenfolge'
    )->fetchAll();
    Response::json($rows);
}

/**
 * Legt eine neue Vorlage an (ans Ende ihrer Phase) und erstellt sofort
 * auch eine Instanz im AKTUELL aktiven Schuljahr, falls eines existiert -
 * sonst würde ein neu ergänzter Schritt erst im nächsten Schuljahr
 * auftauchen, was beim "ich hab was vergessen"-Anwendungsfall verwirrend
 * wäre.
 */
function handleCreateVorlage(PDO $db, array $config, array $input): void
{
    Guard::requireAdmin($db);

    $phaseId = isset($input['phase_id']) ? (int) $input['phase_id'] : null;
    $titel   = trim((string) ($input['titel'] ?? ''));

    if (!$phaseId || $titel === '') {
        Response::error('phase_id und titel sind erforderlich.', 400);
    }

    // Prüfen, ob die Phase existiert
    $phaseCheck = $db->prepare('SELECT id FROM phasen WHERE id = :id');
    $phaseCheck->execute([':id' => $phaseId]);
    if (!$phaseCheck->fetchColumn()) {
        Response::error('Phase nicht gefunden.', 404);
    }

    $db->beginTransaction();
    try {
        $maxStmt = $db->prepare('SELECT COALESCE(MAX(reihenfolge), 0) FROM schritt_vorlagen WHERE phase_id = :phase_id');
        $maxStmt->execute([':phase_id' => $phaseId]);
        $naechsteReihenfolge = (int) $maxStmt->fetchColumn() + 1;

        $insert = $db->prepare(
            'INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, kann_parallel)
             VALUES (:phase_id, :reihenfolge, :titel, 0)'
        );
        $insert->execute([
            ':phase_id'    => $phaseId,
            ':reihenfolge' => $naechsteReihenfolge,
            ':titel'       => $titel,
        ]);
        $vorlageId = (int) $db->lastInsertId();

        // Neue Vorlage für alle bestehenden Prozesse als Instanz anlegen
        $prozesse = $db->query('SELECT id FROM prozesse')->fetchAll();
        $instanzStmt = $db->prepare(
            'INSERT INTO schritt_instanzen (prozess_id, vorlage_id, kann_parallel)
             VALUES (:pid, :vid, 0)'
        );
        foreach ($prozesse as $p) {
            $instanzStmt->execute([':pid' => $p['id'], ':vid' => $vorlageId]);
        }

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    Response::json(['id' => $vorlageId], 201);
}

/**
 * Erlaubt Titel, Beschreibung, Farbe, Aktiv-Status und einen Phasenwechsel.
 * Reihenfolge selbst wird hier NICHT direkt gesetzt (dafür gibt es den
 * eigenen Reihenfolge-Endpunkt fürs Drag-and-Drop) - außer beim
 * Phasenwechsel, da wird automatisch ans Ende der neuen Phase angehängt.
 */
function handleUpdateVorlage(PDO $db, array $config, array $input, array $params): void
{
    Guard::requireAdmin($db);
    $id = (int) $params['id'];

    $aktuelleStmt = $db->prepare('SELECT phase_id FROM schritt_vorlagen WHERE id = :id');
    $aktuelleStmt->execute([':id' => $id]);
    $aktuellePhaseId = $aktuelleStmt->fetchColumn();
    if ($aktuellePhaseId === false) {
        Response::error('Vorlage nicht gefunden.', 404);
    }

    $sets  = [];
    $werte = [':id' => $id];

    foreach (['titel', 'beschreibung'] as $feld) {
        if (array_key_exists($feld, $input)) {
            $sets[]          = "$feld = :$feld";
            $werte[":$feld"] = $input[$feld];
        }
    }

    if (array_key_exists('aktiv', $input)) {
        $sets[]         = 'aktiv = :aktiv';
        $werte[':aktiv'] = $input['aktiv'] ? 1 : 0;
    }

    if (array_key_exists('kann_parallel', $input)) {
        $sets[]                  = 'kann_parallel = :kann_parallel';
        $werte[':kann_parallel'] = $input['kann_parallel'] ? 1 : 0;
    }

    // Phasenwechsel: ans Ende der neuen Phase anhängen
    if (array_key_exists('phase_id', $input) && (int) $input['phase_id'] !== (int) $aktuellePhaseId) {
        $neuePhaseId = (int) $input['phase_id'];
        $maxStmt = $db->prepare('SELECT COALESCE(MAX(reihenfolge), 0) FROM schritt_vorlagen WHERE phase_id = :phase_id');
        $maxStmt->execute([':phase_id' => $neuePhaseId]);
        $sets[]              = 'phase_id = :phase_id';
        $sets[]              = 'reihenfolge = :reihenfolge';
        $werte[':phase_id']  = $neuePhaseId;
        $werte[':reihenfolge'] = (int) $maxStmt->fetchColumn() + 1;
    }

    if (empty($sets)) {
        Response::error('Keine gültigen Felder übergeben.', 400);
    }

    $db->prepare('UPDATE schritt_vorlagen SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($werte);
    Response::json(['ok' => true]);
}

/**
 * Wird nach einer Drag-and-Drop-Aktion im Frontend aufgerufen: bekommt
 * die komplette, neue Reihenfolge der IDs innerhalb EINER Phase und
 * schreibt reihenfolge = Position in diesem Array. Bewusst auf eine
 * Phase begrenzt (kein phasenübergreifendes Drag-and-Drop, siehe
 * Begründung im Chat/README) - ein Phasenwechsel läuft über
 * PATCH .../{id} mit dem Feld "phase".
 */
function handleReihenfolgeVorlagen(PDO $db, array $config, array $input): void
{
    Guard::requireAdmin($db);

    $phaseId = isset($input['phase_id']) ? (int) $input['phase_id'] : null;
    $ids     = $input['vorlage_ids'] ?? null;

    if (!$phaseId || !is_array($ids) || empty($ids)) {
        Response::error('phase_id und vorlage_ids (nicht-leeres Array) sind erforderlich.', 400);
    }

    $db->beginTransaction();
    $stmt = $db->prepare('UPDATE schritt_vorlagen SET reihenfolge = :r WHERE id = :id AND phase_id = :phase_id');
    foreach (array_values($ids) as $index => $id) {
        $stmt->execute([':r' => $index + 1, ':id' => (int) $id, ':phase_id' => $phaseId]);
    }
    $db->commit();

    Response::json(['ok' => true]);
}
