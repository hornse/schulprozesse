<?php

/**
 * NUR für lokale Tests mit `php -S` gedacht (siehe README, "Schnellstart").
 *
 * Apache liest auf dem echten Server backend/public/.htaccess und leitet
 * /api/... an api-router.php weiter. Der eingebaute PHP-Entwicklungsserver
 * wertet .htaccess nicht aus - dieses kleine Skript übernimmt für lokale
 * Tests genau dieselbe Aufgabe. Für den Uberspace-Betrieb wird diese Datei
 * nicht benötigt.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($path, '/api/')) {
    $_GET['route'] = substr($path, 1); // z. B. "api/login"
    require __DIR__ . '/backend/public/api-router.php';
    return true;
}

return false; // an den eingebauten Server zurückgeben (liefert echte Dateien)
