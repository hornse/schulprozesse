<?php
/*
 * Schulprozesse – prozesse.hornse.de
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

use PDO;

/**
 * Liefert eine einzelne, fertig konfigurierte PDO-Verbindung zur
 * SQLite-Datenbank. Bewusst eine simple statische Factory statt eines
 * vollen Connection-Pools - für die erwartete Last (paar Dutzend
 * Kollegen, kein Hochfrequenzbetrieb) reicht das locker.
 */
final class Database
{
    private static ?PDO $instance = null;

    public static function connect(array $config): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $path = $config['db']['sqlite_path'];

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Ohne das hier vergisst SQLite Fremdschlüssel-Constraints (z. B.
        // ON DELETE CASCADE in schritt_instanzen) standardmäßig.
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::$instance = $pdo;
        return $pdo;
    }
}
