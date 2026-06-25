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
 * Endpunkte: GET /api/rollen, POST /api/rollen, DELETE /api/rollen/{user}
 *
 * Alle nur für Admins zugänglich.
 */

use App\Guard;
use App\Response;

function handleListRollen(PDO $db): void
{
    Guard::requireAdmin($db);

    // Basisinfo aller freigegebenen Personen
    $rows = $db->query(
        'SELECT webuntis_user, anzeigename, rolle, erstellt_am FROM benutzer_rollen ORDER BY anzeigename'
    )->fetchAll();

    // Prozess-Zugehörigkeiten für alle Personen in einer Abfrage laden
    $teilnahmen = $db->query(
        'SELECT pt.webuntis_user, pt.rolle AS prozess_rolle, p.label AS prozess_label
         FROM prozess_teilnehmer pt
         JOIN prozesse p ON p.id = pt.prozess_id
         ORDER BY pt.webuntis_user, p.label'
    )->fetchAll();

    // Nach webuntis_user gruppieren
    $teilnahmenMap = [];
    foreach ($teilnahmen as $t) {
        $teilnahmenMap[$t['webuntis_user']][] = [
            'label' => $t['prozess_label'],
            'rolle' => $t['prozess_rolle'],
        ];
    }

    // Zusammenführen
    foreach ($rows as &$row) {
        $row['prozesse'] = $teilnahmenMap[$row['webuntis_user']] ?? [];
    }
    unset($row);

    Response::json($rows);
}

function handleUpsertRolle(PDO $db, array $config, array $input): void
{
    Guard::requireAdmin($db);

    $username    = trim((string) ($input['webuntis_user'] ?? ''));
    $rolle       = (string) ($input['rolle'] ?? 'mitglied');
    $anzeigename = trim((string) ($input['anzeigename'] ?? '')) ?: $username;

    if ($username === '' || !in_array($rolle, ['admin', 'mitglied'], true)) {
        Response::error('webuntis_user und eine gültige rolle (admin|mitglied) sind erforderlich.', 400);
    }

    $stmt = $db->prepare(
        'INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle) VALUES (:u, :name, :rolle)
         ON CONFLICT(webuntis_user) DO UPDATE SET anzeigename = :name, rolle = :rolle'
    );
    $stmt->execute([':u' => $username, ':name' => $anzeigename, ':rolle' => $rolle]);

    Response::json(['ok' => true]);
}

function handleDeleteRolle(PDO $db, array $config, array $input, array $params): void
{
    $aktuellerAdmin = Guard::requireAdmin($db);
    $username = urldecode($params['user']);

    // Selbst-Entfernung verhindern
    if ($username === $aktuellerAdmin['webuntis_user']) {
        Response::error('Du kannst dich nicht selbst aus der Zugriffsliste entfernen.', 403);
    }

    // Letzten Admin schützen: prüfen ob nach dem Löschen noch mindestens
    // ein Admin übrig bleibt
    $zielRolle = $db->prepare('SELECT rolle FROM benutzer_rollen WHERE webuntis_user = :u');
    $zielRolle->execute([':u' => $username]);
    $zeile = $zielRolle->fetch();

    if (!$zeile) {
        Response::error('Person nicht gefunden.', 404);
    }

    if ($zeile['rolle'] === 'admin') {
        $adminAnzahl = (int) $db->query("SELECT COUNT(*) FROM benutzer_rollen WHERE rolle = 'admin'")->fetchColumn();
        if ($adminAnzahl <= 1) {
            Response::error('Der letzte Admin kann nicht entfernt werden.', 403);
        }
    }

    $db->prepare('DELETE FROM benutzer_rollen WHERE webuntis_user = :u')->execute([':u' => $username]);
    Response::json(['ok' => true]);
}
