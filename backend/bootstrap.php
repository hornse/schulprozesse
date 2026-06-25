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

/**
 * Wird von jedem API-Endpunkt zuerst eingebunden. Erledigt die Dinge, die
 * überall gleich sind: Autoloading, Konfiguration laden, Datenbank öffnen,
 * Session starten. Bewusst ohne Composer/Framework, damit das Projekt ohne
 * `composer install` direkt auf Uberspace lauffähig ist.
 */

declare(strict_types=1);

// --- Einfacher PSR-4-artiger Autoloader für den Namespace App\... ---------
spl_autoload_register(function (string $class) {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, strlen('App\\'));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

use App\Database;
use App\Session;

$configPath = __DIR__ . '/../config/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    die('config.php fehlt. Siehe config/config.example.php und docs/INSTALL.md.');
}

/** @var array $config */
$config = require $configPath;

$db = Database::connect($config);
Session::start($config);

// Sehr einfacher CSRF-Basisschutz: Anfragen, die den Zustand ändern, müssen
// diesen Header tragen. Ein normales <form>-Tag von einer fremden Seite aus
// kann das nicht setzen, fetch() mit JS auf einer fremden Domain auch nicht
// (CORS verhindert das). Kein vollwertiges CSRF-Token-System, aber für ein
// kleines internes Schultool ein angemessener Aufwand.
$method = $_SERVER['REQUEST_METHOD'];
$route  = trim((string) ($_GET['route'] ?? ''), '/');
if (in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
    $marker = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if ($marker !== 'SchuljahreswechselApp') {
        \App\Response::error('Fehlender oder ungültiger Anfrage-Header.', 403);
    }
}
