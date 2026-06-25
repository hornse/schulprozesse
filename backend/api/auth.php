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
 * Endpunkte: POST /api/login, POST /api/logout, GET /api/me
 *
 * Login-Reihenfolge:
 *   1. Ist die Person in benutzer_rollen und hat einen passwort_hash?
 *      → Lokales Passwort prüfen (bcrypt). Bei Erfolg: einloggen ohne
 *        WebUntis-Request. Bei Misserfolg: trotzdem weiter zu Schritt 2.
 *   2. WebUntis-Authentifizierung (wie bisher).
 *   3. Freigabe-Prüfung: Person muss in benutzer_rollen stehen.
 *
 * Das lokale Passwort wird NUR per SQL gesetzt, kein UI – es ist ein
 * Notfall-Mechanismus für den Fall dass WebUntis nicht erreichbar ist.
 * Siehe migrations/008_lokales_passwort.sql für die genauen Befehle.
 */

use App\Auth\WebUntisAuth;
use App\Guard;
use App\Response;
use App\Session;

function handleLogin(PDO $db, array $config, array $input): void
{
    $username = (string) ($input['username'] ?? '');
    $password = (string) ($input['password'] ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';

    // --- Schritt 1: Lokales Passwort prüfen ---
    $lokalStmt = $db->prepare(
        'SELECT anzeigename, rolle, passwort_hash FROM benutzer_rollen WHERE webuntis_user = :u'
    );
    $lokalStmt->execute([':u' => $username]);
    $lokalZeile = $lokalStmt->fetch();

    if ($lokalZeile && !empty($lokalZeile['passwort_hash'])) {
        if (password_verify($password, $lokalZeile['passwort_hash'])) {
            // Lokaler Login erfolgreich
            protokolliereLoginVersuch($db, $username, true, 'lokal', $ip);
            $user = [
                'webuntis_user' => $username,
                'anzeigename'   => $lokalZeile['anzeigename'] ?: $username,
                'rolle'         => $lokalZeile['rolle'],
            ];
            Session::login($user);
            Response::json($user);
        }
        // Lokales Passwort falsch – weiter mit WebUntis-Prüfung
    }

    // --- Schritt 2: WebUntis-Authentifizierung ---
    $auth   = new WebUntisAuth($config, $db);
    $result = $auth->authenticate($username, $password, $ip);

    if ($result === null) {
        Response::error('Anmeldung nicht möglich. Bitte Zugangsdaten prüfen.', 401);
    }

    // --- Schritt 3: Freigabe-Prüfung ---
    $rolle = findeBenutzerRolle($db, $result['username']);

    if ($rolle === null) {
        protokolliereLoginVersuch($db, $result['username'], false, 'nicht_freigegeben', $ip);
        Response::error(
            'Diese App ist nur für freigegebene Personen (Untis/WebUntis-Team) nutzbar. '
            . 'Bitte eine Admin/einen Admin um Freischaltung bitten.',
            403
        );
    }

    protokolliereLoginVersuch($db, $result['username'], true, null, $ip);

    $user = [
        'webuntis_user' => $result['username'],
        'anzeigename'   => $rolle['anzeigename'] ?: $result['username'],
        'rolle'         => $rolle['rolle'],
    ];

    Session::login($user);
    Response::json($user);
}

function handleLogout(): void
{
    Session::logout();
    Response::json(['ok' => true]);
}

function handleMe(PDO $db): void
{
    $user = Guard::requireLogin($db);
    Response::json($user);
}

/**
 * Liest die Rollen-Zeile zu einem WebUntis-Benutzernamen.
 * Gibt bewusst NICHT passwort_hash zurück – der Hash verlässt nie den
 * Login-Handler und wird nirgendwo sonst benötigt.
 */
function findeBenutzerRolle(PDO $db, string $username): ?array
{
    $stmt = $db->prepare('SELECT anzeigename, rolle FROM benutzer_rollen WHERE webuntis_user = :u');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Ergänzt login_log auch für den Fall "WebUntis-Login korrekt, aber
 * nicht freigegeben" - WebUntisAuth kennt diesen Grund nicht, weil die
 * Freigabe-Prüfung eine Ebene höher (hier) passiert.
 */
function protokolliereLoginVersuch(PDO $db, string $username, bool $erfolgreich, ?string $grund, string $ip): void
{
    $db->prepare(
        'INSERT INTO login_log (webuntis_user, erfolgreich, grund, ip) VALUES (:u, :e, :g, :ip)'
    )->execute([':u' => $username, ':e' => $erfolgreich ? 1 : 0, ':g' => $grund, ':ip' => $ip]);
}
