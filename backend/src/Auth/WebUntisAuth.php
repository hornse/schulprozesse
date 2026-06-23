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

namespace App\Auth;

use PDO;
use RuntimeException;

/**
 * Authentifiziert Benutzer gegen die WebUntis JSON-RPC-API.
 *
 * Diese Klasse ist bewusst eine eigenständige, von MRBS unabhängige
 * Variante des bestehenden Schul-Moduls `MRBS\Auth\AuthWebUntis`. Das
 * Grundprinzip (POST an /WebUntis/jsonrpc.do mit method=authenticate)
 * ist identisch, es gibt aber drei inhaltliche Unterschiede, die für
 * eine reine Lehrkräfte-App wichtig sind:
 *
 *   1. Rollenprüfung: Wir lesen `result.personType` aus der Antwort und
 *      lassen nur die in der Konfiguration erlaubten Typen durch
 *      (Standard: 2 = Lehrkraft). Das MRBS-Modul prüft das nicht, weil
 *      MRBS bewusst auch Schüler:innen einlässt.
 *   2. Keine Passwort-Logs: Das MRBS-Modul kann im Debug-Modus den
 *      kompletten Request-Body (inkl. Klartext-Passwort) protokollieren.
 *      Diese Klasse loggt nie den Request-Body.
 *   3. Timeouts + einfache Brute-Force-Bremse, damit ein langsames oder
 *      kompromittiertes WebUntis nicht den ganzen PHP-Prozess blockiert
 *      bzw. nicht für automatisiertes Passwort-Raten missbraucht werden
 *      kann.
 *
 * Die WebUntis-Session (sessionId), die `authenticate` zurückgibt, wird
 * NICHT weiterverwendet - wir brauchen sie nur als Nachweis, dass das
 * Passwort korrekt war, und melden uns direkt wieder ab (`logout`), um
 * auf dem WebUntis-Server keine Sitzung offen zu lassen.
 */
final class WebUntisAuth
{
    /**
     * @param array $config Die komplette Anwendungskonfiguration (aus
     *                       config.php), nicht nur der 'webuntis'-Teil -
     *                       wir brauchen zusätzlich 'security'.
     */
    public function __construct(
        private readonly array $config,
        private readonly PDO $db,
    ) {
    }

    /**
     * Versucht, den Benutzer gegen WebUntis zu authentifizieren.
     *
     * @return array{username: string, personType: int, personId: int}|null
     *         null bei falschem Passwort, falscher Rolle oder Netzwerkfehler.
     *         Der Aufrufer (siehe backend/api/auth.php) entscheidet, was er
     *         daraus für eine Fehlermeldung macht.
     */
    public function authenticate(string $username, string $password, string $ip): ?array
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return null;
        }

        if ($this->tooManyAttempts($username)) {
            $this->logAttempt($username, false, 'zu_viele_versuche', $ip);
            return null;
        }

        $response = $this->callJsonRpc('authenticate', [
            'user'     => $username,
            'password' => $password,
            'client'   => $this->config['webuntis']['client'],
        ]);

        // Netzwerkfehler oder JSON-RPC-Fehlerantwort (falsches Passwort).
        if ($response === null || isset($response['error'])) {
            $this->logAttempt($username, false, 'falsches_passwort_oder_netzwerk', $ip);
            return null;
        }

        $result = $response['result'] ?? null;
        $personType = $result['personType'] ?? null;
        $personId = $result['personId'] ?? null;

        // Sitzung beim WebUntis-Server sofort wieder freigeben. Best effort -
        // ein Fehler hier darf den Login nicht scheitern lassen.
        $this->callJsonRpc('logout', []);

        if (!in_array($personType, $this->config['webuntis']['allowed_person_types'], true)) {
            $this->logAttempt($username, false, 'falsche_rolle', $ip);
            return null;
        }

        $this->logAttempt($username, true, null, $ip);

        return [
            'username'   => $username,
            'personType' => (int) $personType,
            'personId'   => (int) $personId,
        ];
    }

    /**
     * Führt einen JSON-RPC-Aufruf gegen den konfigurierten WebUntis-Server aus.
     *
     * @return array{result?: mixed, error?: mixed}|null null bei
     *         Netzwerk-/Transportfehlern (siehe error_log).
     */
    private function callJsonRpc(string $method, array $params): ?array
    {
        $wuConfig = $this->config['webuntis'];
        $baseUrl = rtrim($wuConfig['base_url'], '/');

        // Wir bestehen auf HTTPS, weil hier im Klartext ein Passwort übertragen
        // wird. Das ist kein theoretisches Risiko - WebUntis-Server, die nur
        // HTTP anbieten, gibt es zwar kaum noch, aber ein Tippfehler in der
        // Konfiguration soll nicht versehentlich Klartext-Logins erzeugen.
        if (!str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('webuntis.base_url muss mit https:// beginnen.');
        }

        $url = $baseUrl . '/WebUntis/jsonrpc.do?school=' . urlencode($wuConfig['school']);

        $body = json_encode([
            'id'      => 'swj-' . bin2hex(random_bytes(4)),
            'method'  => $method,
            'params'  => $params,
            'jsonrpc' => '2.0',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CONNECTTIMEOUT => $wuConfig['connect_timeout'] ?? 5,
            CURLOPT_TIMEOUT        => $wuConfig['timeout'] ?? 10,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Absichtlich KEIN Logging von $body (enthält das Passwort bei
        // method=authenticate). Nur unkritische Metadaten protokollieren.
        if ($raw === false) {
            error_log("WebUntisAuth: cURL-Fehler bei method={$method}: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("WebUntisAuth: HTTP {$httpCode} bei method={$method}");
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log("WebUntisAuth: ungültige JSON-Antwort bei method={$method}");
            return null;
        }

        return $decoded;
    }

    /**
     * Einfache Brute-Force-Bremse auf Basis von login_log: blockt einen
     * Benutzernamen kurzfristig, wenn zu viele Fehlversuche aufgelaufen sind.
     * Das schützt zugleich WebUntis selbst vor automatisiertem Passwort-Raten
     * über diese App.
     */
    private function tooManyAttempts(string $username): bool
    {
        $maxAttempts = $this->config['security']['max_failed_logins'] ?? 5;
        $lockoutMinutes = $this->config['security']['lockout_minutes'] ?? 15;

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM login_log
             WHERE webuntis_user = :user
               AND erfolgreich = 0
               AND zeitpunkt >= datetime('now', :window)"
        );
        $stmt->execute([
            ':user'   => $username,
            ':window' => '-' . $lockoutMinutes . ' minutes',
        ]);

        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    private function logAttempt(string $username, bool $success, ?string $grund, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_log (webuntis_user, erfolgreich, grund, ip)
             VALUES (:user, :erfolgreich, :grund, :ip)'
        );
        $stmt->execute([
            ':user'       => $username,
            ':erfolgreich' => $success ? 1 : 0,
            ':grund'      => $grund,
            ':ip'         => $ip,
        ]);
    }
}
