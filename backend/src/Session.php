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

namespace App;

/**
 * Dünner Wrapper um die native PHP-Session. Speichert nach erfolgreichem
 * WebUntis-Login nur das Nötigste - niemals ein Passwort, das kennen wir
 * an dieser Stelle ohnehin nicht mehr (siehe WebUntisAuth).
 */
final class Session
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($config['session']['name']);
        session_set_cookie_params([
            'lifetime' => 60 * $config['session']['lifetime_minutes'],
            'path'     => '/',
            'secure'   => true,      // nur über HTTPS - auf Uberspace Standard
            'httponly' => true,      // kein Zugriff aus JavaScript
            'samesite' => 'Lax',     // einfacher CSRF-Basisschutz
        ]);
        session_start();
    }

    public static function login(array $user): void
    {
        // session_regenerate_id verhindert "Session Fixation": eine vor dem
        // Login bekannte Session-ID wird nach dem Login ungültig.
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    /** @return array{webuntis_user: string, anzeigename: string, rolle: string}|null */
    public static function currentUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}
