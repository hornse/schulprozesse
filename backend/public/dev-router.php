<?php
/**
 * Router für PHP built-in server auf Uberspace.
 * Wird von supervisord gestartet, ersetzt Apache mod_rewrite.
 *
 * Start: php -S 0.0.0.0:8083 dev-router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// API-Requests an Router weiterleiten. Steht VOR der Sperrliste: Zwei Routen
// haben ein freies Segment (api/rollen/{user}, …/teilnehmer/{user}), und ein
// Wert mit einer Endung der Liste darf nicht 404 werden. Unter backend/public
// gibt es kein api/, also wird unter /api/ keine Datei ausgeliefert
// (Lauf 2, Entscheidung 2).
if (str_starts_with($uri, '/api/')) {
    $_GET['route'] = ltrim($uri, '/');
    require __DIR__ . '/api-router.php';
    exit;
}

// Sperrliste: Was Apache sperrt (Editor-Reste, Daten, Einstellungen, Doku,
// Skripte, Punktdateien – auch die .htaccess hier), liefert auch dieser
// Router nicht aus. Vor der Dateiauslieferung und vor dem SPA-Rückfall,
// damit die Antwort nicht davon abhängt, ob die Datei existiert: immer 404.
// Die Liste steht in backend/src/sperrliste.php; tests/sperrliste_test.php
// misst sie gegen die Apache-Vorlage (S62, koordination).
require_once __DIR__ . '/../src/sperrliste.php';
if (sp_pfad_gesperrt($uri)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nicht gefunden';
    return true;
}

// Statische Dateien direkt ausliefern (CSS, JS, Bilder)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Alles andere: index.html (SPA)
readfile(__DIR__ . '/index.html');
