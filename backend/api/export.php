<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Endpunkte:
 *   GET /api/export/csv              – Checkliste als CSV-Download
 *   GET /api/export/aktivitaeten     – Aktivitätsprotokoll als CSV
 *   GET /api/aktivitaeten            – Aktivitätsprotokoll als JSON
 */

use App\Guard;
use App\Response;

function handleListAktivitaeten(PDO $db, array $config, array $input): void
{
    Guard::requireLogin($db);
    $prozessId = (int) ($input['prozess_id'] ?? 0);   // „null“ aus app.js wird 0
    if ($prozessId <= 0) {
        Response::error('prozess_id fehlt.', 400);
    }
    // Nur Teilnehmende und Admins, auch bei öffentlichen Prozessen: Der
    // Export enthält Personenfelder, das öffentliche Dashboard nicht (Z3).
    Guard::requireProzessZugriff($db, $prozessId);

    $stmt = $db->prepare(
        'SELECT a.id, a.schritt_titel, a.ereignis, a.wert_neu, a.benutzer,
                a.anzeigename, a.zeitstempel
         FROM aktivitaeten a
         WHERE a.prozess_id = :sj
         ORDER BY a.zeitstempel DESC
         LIMIT 200'
    );
    $stmt->execute([':sj' => $prozessId]);
    Response::json($stmt->fetchAll());
}

function handleExportAktivitaeten(PDO $db, array $config, array $input): void
{
    Guard::requireLogin($db);
    $prozessId = (int) ($input['prozess_id'] ?? 0);   // „null“ aus app.js wird 0
    if ($prozessId <= 0) {
        Response::error('prozess_id fehlt.', 400);
    }
    // Nur Teilnehmende und Admins, auch bei öffentlichen Prozessen: Der
    // Export enthält Personenfelder, das öffentliche Dashboard nicht (Z3).
    Guard::requireProzessZugriff($db, $prozessId);

    $row = $db->prepare('SELECT label FROM prozesse WHERE id = :id');
    $row->execute([':id' => $prozessId]);
    $label = $row->fetchColumn() ?: 'export';

    $stmt = $db->prepare(
        'SELECT a.zeitstempel, a.anzeigename, a.schritt_titel, a.ereignis, a.wert_neu
         FROM aktivitaeten a
         WHERE a.prozess_id = :sj
         ORDER BY a.zeitstempel DESC'
    );
    $stmt->execute([':sj' => $prozessId]);

    $dateiname = 'aktivitaeten_' . preg_replace('/[^a-z0-9]/i', '_', $label) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-cache');
    echo "\xEF\xBB\xBF";

    $ereignisTexte = [
        'schritt_erledigt'       => 'Erledigt',
        'schritt_rueckgaengig'   => 'Rückgängig gemacht',
        'verantwortlich_gesetzt' => 'Verantwortlich gesetzt',
        'datum_gesetzt'          => 'Zieldatum gesetzt',
        'startdatum_gesetzt'     => 'Startdatum gesetzt',
        'kommentar_gesetzt'      => 'Kommentar aktualisiert',
    ];

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Zeitstempel', 'Person', 'Schritt', 'Aktion', 'Wert'], ';', '"', '\\');
    foreach ($stmt->fetchAll() as $a) {
        fputcsv($out, [
            $a['zeitstempel'],
            $a['anzeigename'],
            $a['schritt_titel'],
            $ereignisTexte[$a['ereignis']] ?? $a['ereignis'],
            $a['wert_neu'] ?? '',
        ], ';', '"', '\\');
    }
    fclose($out);
    exit;
}

function handleExportCsv(PDO $db, array $config, array $input): void
{
    Guard::requireLogin($db);
    $prozessId = (int) ($input['prozess_id'] ?? 0);   // „null“ aus app.js wird 0
    if ($prozessId <= 0) {
        Response::error('prozess_id fehlt.', 400);
    }
    // Nur Teilnehmende und Admins, auch bei öffentlichen Prozessen: Der
    // Export enthält Personenfelder, das öffentliche Dashboard nicht (Z3).
    Guard::requireProzessZugriff($db, $prozessId);

    $schuljahrLabel = $db->prepare('SELECT label FROM prozesse WHERE id = :id');
    $schuljahrLabel->execute([':id' => $prozessId]);
    $label = $schuljahrLabel->fetchColumn() ?: 'export';

    $stmt = $db->prepare(
        'SELECT p.reihenfolge AS phase_nr, p.name AS phase,
                sv.reihenfolge, sv.titel,
                si.erledigt, si.verantwortlich_anzeigename,
                si.start_datum, si.geplantes_datum,
                si.erledigt_am, si.erledigt_von,
                si.kann_parallel, si.kommentar
         FROM schritt_instanzen si
         JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         JOIN phasen p ON p.id = sv.phase_id
         WHERE si.prozess_id = :sj
         ORDER BY p.reihenfolge, sv.reihenfolge'
    );
    $stmt->execute([':sj' => $prozessId]);
    $schritte = $stmt->fetchAll();

    $dateiname = 'schulprozess_' . preg_replace('/[^a-z0-9]/i', '_', $label) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $dateiname . '"');
    header('Cache-Control: no-cache');

    // UTF-8 BOM damit Excel die Datei korrekt öffnet
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Phase-Nr', 'Phase', 'Reihenfolge', 'Schritt',
        'Erledigt', 'Verantwortlich', 'Start', 'Zieldatum',
        'Erledigt am', 'Erledigt von', 'Kann parallel', 'Kommentar'
    ], ';', '"', '\\');

    foreach ($schritte as $s) {
        fputcsv($out, [
            $s['phase_nr'],
            $s['phase'],
            $s['reihenfolge'],
            $s['titel'],
            $s['erledigt'] ? 'Ja' : 'Nein',
            $s['verantwortlich_anzeigename'] ?? '',
            $s['start_datum'] ?? '',
            $s['geplantes_datum'] ?? '',
            $s['erledigt_am'] ?? '',
            $s['erledigt_von'] ?? '',
            $s['kann_parallel'] ? 'Ja' : 'Nein',
            $s['kommentar'] ?? '',
        ], ';', '"', '\\');
    }

    fclose($out);
    exit;
}
