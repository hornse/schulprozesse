<?php
/**
 * Router für PHP built-in server auf Uberspace.
 * Wird von supervisord gestartet, ersetzt Apache mod_rewrite.
 *
 * Start: php -S 0.0.0.0:8083 dev-router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Statische Dateien direkt ausliefern (CSS, JS, Bilder)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// API-Requests an Router weiterleiten
if (str_starts_with($uri, '/api/')) {
    $_GET['route'] = ltrim($uri, '/');
    require __DIR__ . '/api-router.php';
    exit;
}

// Alles andere: index.html (SPA)
readfile(__DIR__ . '/index.html');
