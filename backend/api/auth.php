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
 *   0. Die Anmeldebremse (App\Auth\Anmeldebremse): den Versuch eintragen,
 *      dann zählen. Ab der Schwelle eine Minute Wartezeit (429), scheitert
 *      die Zählung 503. VOR dem Notfallpasswort: Ein ungebremstes Passwort
 *      lässt sich in Ruhe durchprobieren, und der Notfallzugang ist der
 *      mächtigste (Entscheidung Betreiber, 16.10.2026).
 *   1. Ist die Person in benutzer_rollen und hat einen passwort_hash?
 *      → Lokales Passwort prüfen (bcrypt). Bei Erfolg: einloggen ohne
 *        WebUntis-Request. Bei Misserfolg: trotzdem weiter zu Schritt 2.
 *   2. WebUntis-Authentifizierung (wie bisher).
 *   3. Freigabe-Prüfung: Person muss in benutzer_rollen stehen.
 *
 * Das lokale Passwort wird NUR per SQL gesetzt, kein UI – es ist ein
 * Notfall-Mechanismus für den Fall dass WebUntis nicht erreichbar ist.
 * Die Befehle stehen in docs/INSTALL.md, Abschnitt „Notfall-Passwort“.
 */

use App\Auth\Anmeldebremse;
use App\Auth\WebUntisAuth;
use App\Guard;
use App\Response;
use App\Session;

function handleLogin(PDO $db, array $config, array $input): void
{
    $username = (string) ($input['username'] ?? '');
    $password = (string) ($input['password'] ?? '');
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unbekannt';

    if (trim($username) === '' || $password === '') {
        Response::error('Anmeldung nicht möglich. Bitte Zugangsdaten prüfen.', 401);
    }

    // --- Schritt 0: Bremse – vor jedem Passwort ---
    $versuch = bremseBeginnen($db, $config, trim($username), $ip);

    // --- Schritt 1: Lokales Passwort prüfen ---
    $lokalStmt = $db->prepare(
        'SELECT anzeigename, rolle, passwort_hash FROM benutzer_rollen WHERE webuntis_user = :u'
    );
    $lokalStmt->execute([':u' => $username]);
    $lokalZeile = $lokalStmt->fetch();
    // Ein falsches Notfallpasswort IST ein Entscheid – auch wenn WebUntis
    // danach nicht antwortet. Sonst wäre das Notfallpasswort genau im Notfall
    // wieder ungebremst.
    $lokalFalsch = false;

    if ($lokalZeile && !empty($lokalZeile['passwort_hash'])) {
        if (password_verify($password, $lokalZeile['passwort_hash'])) {
            // Lokaler Login erfolgreich – setzt die Bremse zurück
            Anmeldebremse::abschliessen($db, $versuch, true, 'lokal');
            $user = [
                'webuntis_user' => $username,
                'anzeigename'   => $lokalZeile['anzeigename'] ?: $username,
                'rolle'         => $lokalZeile['rolle'],
            ];
            Session::login($user);
            Response::json($user);
        }
        // Lokales Passwort falsch – weiter mit WebUntis-Prüfung
        $lokalFalsch = true;
    }

    // --- Schritt 2: WebUntis-Authentifizierung ---
    $auth   = new WebUntisAuth($config, $db);
    $result = $auth->authenticate($username, $password);

    if ($result === null) {
        // Antwortete WebUntis nicht, hat kein Passwort entschieden und der
        // Versuch zählt nicht – es sei denn, das Notfallpasswort war falsch.
        // Die Antwort bleibt in jedem Fall dieselbe.
        if ($auth->grund() === 'ohne_entscheid' && $lokalFalsch) {
            Anmeldebremse::abschliessen($db, $versuch, false, 'falsches_lokales_passwort');
        } elseif ($auth->grund() === 'ohne_entscheid') {
            Anmeldebremse::ohneEntscheid($db, $versuch);
        } else {
            Anmeldebremse::abschliessen($db, $versuch, false, $auth->grund());
        }
        Response::error('Anmeldung nicht möglich. Bitte Zugangsdaten prüfen.', 401);
    }

    // --- Schritt 3: Freigabe-Prüfung ---
    $rolle = findeBenutzerRolle($db, $result['username']);

    if ($rolle === null) {
        // Nach aussen wie ein falsches Passwort: gleicher Wortlaut, gleicher
        // Statuscode. Ein abweichender Fehlschlag verriete, dass das Passwort
        // stimmte, und machte die Anwendung zum Pruefstand fuer fremde
        // Zugangsdaten (FALLSTRICKE.md 8).
        //
        // Der tatsaechliche Grund steht im Protokoll, nicht in der Antwort:
        // Wer nicht hineinkommt, meldet sich ohnehin bei einer Administratorin
        // oder einem Administrator, und die lesen login_log.grund.
        // Er zählt für die Bremse wie ein falsches Passwort – sonst verriete
        // das Ausbleiben der Wartezeit, dass das Passwort stimmte.
        Anmeldebremse::abschliessen($db, $versuch, false, 'nicht_freigegeben');
        Response::error('Anmeldung nicht möglich. Bitte Zugangsdaten prüfen.', 401);
    }

    Anmeldebremse::abschliessen($db, $versuch, true, null);

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
 * Fragt die Anmeldebremse (App\Auth\Anmeldebremse) und weist ab, wenn sie
 * nein sagt – mit ihrem eigenen Status (429 warten, 503 Zählung gescheitert).
 * Steht außerhalb von handleLogin, weil dort jede Ablehnung 401 ist, solange
 * ein Passwort entschied; hier hat noch keines entschieden.
 *
 * Schwelle und Zählfenster aus config.php: security.max_failed_logins und
 * security.lockout_minutes (heißt weiter so, ist jetzt das Zählfenster).
 */
function bremseBeginnen(PDO $db, array $config, string $username, string $ip): int
{
    $r = Anmeldebremse::beginnen(
        $db, $username, $ip,
        (int) ($config['security']['max_failed_logins'] ?? 5),
        (int) ($config['security']['lockout_minutes'] ?? 15)
    );
    if (!$r['urteil']['durch']) {
        if ($r['urteil']['status'] === 429) header('Retry-After: ' . Anmeldebremse::WARTEN_SEKUNDEN);
        Response::error($r['urteil']['meldung'], $r['urteil']['status']);
    }
    return $r['id'];
}
