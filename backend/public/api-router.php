<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Response;

require __DIR__ . '/../api/auth.php';
require __DIR__ . '/../api/dashboard.php';
require __DIR__ . '/../api/schritte.php';
require __DIR__ . '/../api/prozesse.php';
require __DIR__ . '/../api/rollen.php';
require __DIR__ . '/../api/phasen.php';
require __DIR__ . '/../api/vorlagen.php';
require __DIR__ . '/../api/vorlagen-sets.php';
require __DIR__ . '/../api/export.php';

require __DIR__ . '/../api/einstellungen.php';

$route  = trim((string) ($_GET['route'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Query-Parameter und JSON-Body zusammenführen.
// Bei Multipart-Uploads (Logo) ist der Body kein JSON sondern $_POST/$_FILES.
$rawBody   = file_get_contents('php://input');
$bodyInput = $rawBody ? (json_decode($rawBody, true) ?? []) : [];
// Bei Multipart-Requests $_POST verwenden
if (!empty($_POST)) {
    $bodyInput = array_merge($bodyInput, $_POST);
}
$getParams = array_diff_key($_GET, ['route' => true]);
$input     = array_merge($getParams, $bodyInput);

$routes = [
    ['POST',   '#^api/login$#',                                          'handleLogin'],
    ['POST',   '#^api/logout$#',                                         'handleLogout'],
    ['GET',    '#^api/me$#',                                             'handleMe'],

    ['GET',    '#^api/dashboard$#',                                      'handleDashboard'],

    ['GET',    '#^api/schritte$#',                                       'handleListSchritte'],
    ['PATCH',  '#^api/schritte/(?P<id>\d+)$#',                           'handleUpdateSchritt'],

    ['GET',    '#^api/prozesse$#',                                       'handleListProzesse'],
    ['POST',   '#^api/prozesse$#',                                       'handleCreateProzess'],
    ['PATCH',  '#^api/prozesse/(?P<id>\d+)$#',                           'handleUpdateProzess'],
    ['POST',   '#^api/prozesse/(?P<id>\d+)/aktivieren$#',                'handleActivateProzess'],
    ['GET',    '#^api/prozesse/(?P<id>\d+)/teilnehmer$#',                'handleListTeilnehmer'],
    ['POST',   '#^api/prozesse/(?P<id>\d+)/teilnehmer$#',               'handleUpsertTeilnehmer'],
    ['DELETE', '#^api/prozesse/(?P<id>\d+)/teilnehmer/(?P<user>[^/]+)$#', 'handleDeleteTeilnehmer'],

    ['GET',    '#^api/rollen$#',                                         'handleListRollen'],
    ['POST',   '#^api/rollen$#',                                         'handleUpsertRolle'],
    ['DELETE', '#^api/rollen/(?P<user>[^/]+)$#',                        'handleDeleteRolle'],

    ['GET',    '#^api/vorlagen$#',                                       'handleListVorlagen'],
    ['POST',   '#^api/vorlagen$#',                                       'handleCreateVorlage'],
    ['PATCH',  '#^api/vorlagen/(?P<id>\d+)$#',                           'handleUpdateVorlage'],
    ['POST',   '#^api/vorlagen/reihenfolge$#',                           'handleReihenfolgeVorlagen'],

    ['GET',    '#^api/phasen$#',                                         'handleListPhasen'],
    ['POST',   '#^api/phasen$#',                                         'handleCreatePhase'],
    ['PATCH',  '#^api/phasen/(?P<id>\d+)$#',                             'handleUpdatePhase'],
    ['DELETE', '#^api/phasen/(?P<id>\d+)$#',                             'handleDeletePhase'],
    ['POST',   '#^api/phasen/reihenfolge$#',                             'handleReihenfolgePhasen'],

    ['GET',    '#^api/vorlagen-sets$#',                                  'handleListVorlagenSets'],
    ['POST',   '#^api/vorlagen-sets$#',                                  'handleCreateVorlagenSet'],
    ['GET',    '#^api/vorlagen-sets/(?P<id>\d+)$#',                      'handleGetVorlagenSet'],
    ['DELETE', '#^api/vorlagen-sets/(?P<id>\d+)$#',                      'handleDeleteVorlagenSet'],

    // Snapshot-Phasen bearbeiten
    ['POST',   '#^api/vorlagen-sets/(?P<id>\d+)/phasen$#',               'handleCreateSetPhase'],
    ['PATCH',  '#^api/vorlagen-sets/(?P<id>\d+)/phasen/(?P<pid>\d+)$#',  'handleUpdateSetPhase'],
    ['DELETE', '#^api/vorlagen-sets/(?P<id>\d+)/phasen/(?P<pid>\d+)$#',  'handleDeleteSetPhase'],
    ['POST',   '#^api/vorlagen-sets/(?P<id>\d+)/phasen/reihenfolge$#',   'handleReihenfolgeSetPhasen'],

    // Snapshot-Schritte bearbeiten
    ['POST',   '#^api/vorlagen-sets/(?P<id>\d+)/phasen/(?P<pid>\d+)/schritte$#',       'handleCreateSetSchritt'],
    ['PATCH',  '#^api/vorlagen-sets/(?P<id>\d+)/schritte/(?P<sid>\d+)$#',              'handleUpdateSetSchritt'],
    ['DELETE', '#^api/vorlagen-sets/(?P<id>\d+)/schritte/(?P<sid>\d+)$#',              'handleDeleteSetSchritt'],
    ['POST',   '#^api/vorlagen-sets/(?P<id>\d+)/phasen/(?P<pid>\d+)/schritte/reihenfolge$#', 'handleReihenfolgeSetSchritte'],

    ['GET',    '#^api/export/csv$#',                                     'handleExportCsv'],
    ['GET',    '#^api/export/aktivitaeten$#',                            'handleExportAktivitaeten'],
    ['GET',    '#^api/aktivitaeten$#',                                   'handleListAktivitaeten'],

    // Einstellungen (Erscheinungsbild)
    ['GET',    '#^api/einstellungen$#',                                  'handleGetEinstellungen'],
    ['POST',   '#^api/einstellungen$#',                                  'handleSaveEinstellungen'],
    ['POST',   '#^api/einstellungen/logo$#',                             'handleLogoUpload'],
    ['GET',    '#^api/einstellungen/logo$#',                             'handleLogoAusliefern'],
    ['DELETE', '#^api/einstellungen/logo$#',                             'handleLogoLoeschen'],
    ['POST',   '#^api/einstellungen/aktivieren$#',                       'handleEinstellungenAktivieren'],
    ['POST',   '#^api/einstellungen/zuruecksetzen$#',                    'handleEinstellungenZuruecksetzen'],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($method !== $routeMethod || !preg_match($pattern, $route, $matches)) {
        continue;
    }
    $params = array_filter($matches, fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
    $handler($db, $config, $input, $params);
    exit;
}

Response::error("Unbekannte Route: $method /$route", 404);
