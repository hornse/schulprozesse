<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Endpunkte:
 *   GET  /api/einstellungen          – alle Einstellungen lesen (admin)
 *   POST /api/einstellungen          – Einstellungen speichern (admin)
 *   POST /api/einstellungen/logo     – Logo hochladen (admin)
 *   GET  /api/einstellungen/logo     – Logo ausliefern (öffentlich)
 *   POST /api/einstellungen/aktivieren – Vorschau aktivieren (admin)
 *   POST /api/einstellungen/zuruecksetzen – Auf Standard zurücksetzen (admin)
 *
 * Sicherheitsmaßnahmen:
 *   - Farben werden gegen #RRGGBB validiert (verhindert CSS-Injection)
 *   - Texte werden auf maximale Länge begrenzt
 *   - Logo: nur PNG/JPG/SVG, max. 500 KB, MIME-Prüfung via finfo
 *   - SVG: wird auf gefährliche Inhalte geprüft (script, javascript:, on*)
 *   - Logo-Pfad wird nie ans Frontend übermittelt
 *   - Logo wird außerhalb des Webroots gespeichert
 */

declare(strict_types=1);

use App\Guard;
use App\Response;

// ---- Hilfsfunktionen -------------------------------------------------------

/**
 * Liest alle Einstellungen aus der DB und gibt sie als assoziatives Array
 * zurück. Logo-Pfad wird nie zurückgegeben (interner Pfad, sicherheitsrelevant).
 */
function ladeEinstellungen(PDO $db): array
{
    $rows = $db->query(
        "SELECT schluessel, wert FROM einstellungen
         WHERE schluessel != 'logo_pfad'"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    // Sicherstellen dass alle erwarteten Schlüssel vorhanden sind
    $defaults = [
        'schulname'       => 'Meine Schule',
        'app_titel'       => 'Schulprozesse',
        'farbe_akzent'    => '#5B6FA8',
        'farbe_sekundaer' => '#D98A2B',
        'logo_mime'       => '',
        'vorschau_aktiv'  => '0',
    ];

    return array_merge($defaults, $rows);
}

/**
 * Prüft ob ein String ein gültiger #RRGGBB-Hex-Farbwert ist.
 * Verhindert CSS-Injection wenn Farben direkt in Style-Attribute eingefügt werden.
 */
function validiereHexfarbe(string $farbe): bool
{
    return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $farbe);
}

/**
 * Bereinigt einen Anzeigetext: HTML-Tags entfernen, auf Maximallänge kürzen.
 * Verhindert XSS wenn der Text im Frontend gerendert wird.
 */
function bereinigText(string $text, int $maxLen): string
{
    $text = strip_tags(trim($text));
    return mb_substr($text, 0, $maxLen);
}

/**
 * Prüft eine SVG-Datei auf gefährliche Inhalte.
 * SVGs können JavaScript enthalten und werden deshalb streng geprüft.
 * Gibt true zurück wenn die Datei sicher erscheint.
 */
function svgIstSicher(string $inhalt): bool
{
    // Gefährliche Muster in SVGs
    $muster = [
        '/<script/i',           // Script-Tags
        '/javascript:/i',       // javascript:-URLs
        '/on\w+\s*=/i',         // Event-Handler (onclick, onload, ...)
        '/<iframe/i',           // iFrames
        '/<object/i',           // Object-Tags
        '/<embed/i',            // Embed-Tags
        '/xlink:href\s*=\s*["\']javascript/i', // XLink mit JS
        '/data:text\/html/i',   // Data-URLs mit HTML
        '/<?php/i',             // PHP-Tags (Vorsichtsmaßnahme)
    ];

    foreach ($muster as $m) {
        if (preg_match($m, $inhalt)) {
            return false;
        }
    }
    return true;
}

// ---- Handler ---------------------------------------------------------------

function handleGetEinstellungen(PDO $db, array $config, array $input): void
{
    Guard::requireAdmin($db);
    $einstellungen = ladeEinstellungen($db);
    // Zusätzlich: hat die Schule ein Logo hochgeladen?
    $logoVorhanden = (bool) $db->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'logo_pfad'"
    )->fetchColumn();
    $einstellungen['hat_logo'] = $logoVorhanden;
    Response::json($einstellungen);
}

function handleSaveEinstellungen(PDO $db, array $config, array $input): void
{
    $user = Guard::requireAdmin($db);

    $erlaubteFelder = [
        'schulname'       => ['typ' => 'text',  'max' => 80],
        'app_titel'       => ['typ' => 'text',  'max' => 40],
        'farbe_akzent'    => ['typ' => 'farbe'],
        'farbe_sekundaer' => ['typ' => 'farbe'],
    ];

    $stmt = $db->prepare(
        "INSERT OR REPLACE INTO einstellungen (schluessel, wert, geaendert_am, geaendert_von)
         VALUES (:k, :v, datetime('now'), :u)"
    );

    $gespeichert = [];
    foreach ($erlaubteFelder as $key => $regel) {
        if (!array_key_exists($key, $input)) continue;

        $wert = (string) $input[$key];

        if ($regel['typ'] === 'farbe') {
            if (!validiereHexfarbe($wert)) {
                Response::error("Ungültiger Farbwert für '$key'. Erwartet: #RRGGBB", 400);
            }
        } elseif ($regel['typ'] === 'text') {
            $wert = bereinigText($wert, $regel['max']);
            if ($wert === '') {
                Response::error("'$key' darf nicht leer sein.", 400);
            }
        }

        $stmt->execute([':k' => $key, ':v' => $wert, ':u' => $user['webuntis_user']]);
        $gespeichert[] = $key;
    }

    Response::json(['ok' => true, 'gespeichert' => $gespeichert]);
}

function handleLogoUpload(PDO $db, array $config, array $input): void
{
    // Output-Buffer starten damit PHP-Warnings die JSON-Antwort nicht kaputtmachen
    ob_start();

    $user = Guard::requireAdmin($db);

    if (empty($_FILES['logo'])) {
        ob_end_clean();
        Response::error('Keine Datei übermittelt.', 400);
    }

    $datei = $_FILES['logo'];

    // Upload-Fehler prüfen
    if ($datei['error'] !== UPLOAD_ERR_OK) {
        $fehler = [
            UPLOAD_ERR_INI_SIZE   => 'Datei überschreitet server­seitiges Größenlimit.',
            UPLOAD_ERR_FORM_SIZE  => 'Datei überschreitet das Formular-Größenlimit.',
            UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise übertragen.',
            UPLOAD_ERR_NO_FILE    => 'Keine Datei übermittelt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden.',
        ];
        ob_end_clean();
        Response::error($fehler[$datei['error']] ?? 'Upload-Fehler.', 400);
    }

    // Größe prüfen: max. 500 KB
    $maxBytes = 500 * 1024;
    if ($datei['size'] > $maxBytes) {
        ob_end_clean();
        Response::error('Logo darf maximal 500 KB groß sein.', 400);
    }

    // MIME-Type serverseitig prüfen (nicht vom Client vertrauen!)
    // finfo ist Standard in PHP 5.3+; Fallback auf mime_content_type falls nötig
    if (class_exists('finfo')) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($datei['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $mimeType = mime_content_type($datei['tmp_name']);
    } else {
        // Letzter Fallback: Dateiendung (weniger sicher, aber besser als nichts)
        $ext      = strtolower(pathinfo($datei['name'], PATHINFO_EXTENSION));
        $mimeType = ['png' => 'image/png', 'jpg' => 'image/jpeg',
                     'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml'][$ext] ?? 'application/octet-stream';
    }

    $erlaubteMimes = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/svg+xml' => 'svg',
        'image/gif'     => null, // explizit ablehnen
    ];

    if (!array_key_exists($mimeType, $erlaubteMimes) || $erlaubteMimes[$mimeType] === null) {
        ob_end_clean();
        Response::error(
            'Nur PNG, JPG und SVG sind erlaubt. Erkannter Typ: ' . htmlspecialchars($mimeType),
            400
        );
    }

    // SVG: auf gefährliche Inhalte prüfen
    if ($mimeType === 'image/svg+xml') {
        $inhalt = file_get_contents($datei['tmp_name']);
        if (!svgIstSicher($inhalt)) {
            ob_end_clean();
        Response::error(
                'Die SVG-Datei enthält potenziell gefährliche Inhalte (Script, Event-Handler o. ä.) ' .
                'und wurde abgelehnt.',
                400
            );
        }
    }

    // Zielverzeichnis außerhalb des Webroots
    $logoDir = dirname(__DIR__, 2) . '/data/logos/';
    if (!is_dir($logoDir)) {
        mkdir($logoDir, 0750, true);
    }

    // Altes Logo löschen wenn vorhanden
    $alterPfad = $db->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'logo_pfad'"
    )->fetchColumn();
    if ($alterPfad && is_file($alterPfad)) {
        unlink($alterPfad);
    }

    // Neuen, zufälligen Dateinamen generieren (kein Original-Name!)
    $ext       = $erlaubteMimes[$mimeType];
    $neuerName = bin2hex(random_bytes(16)) . '.' . $ext;
    $zielPfad  = $logoDir . $neuerName;

    if (!move_uploaded_file($datei['tmp_name'], $zielPfad)) {
        ob_end_clean();
        Response::error('Logo konnte nicht gespeichert werden.', 500);
    }

    // Pfad und MIME in DB speichern
    $stmt = $db->prepare(
        "INSERT OR REPLACE INTO einstellungen (schluessel, wert, geaendert_am, geaendert_von)
         VALUES (:k, :v, datetime('now'), :u)"
    );
    $stmt->execute([':k' => 'logo_pfad', ':v' => $zielPfad,  ':u' => $user['webuntis_user']]);
    $stmt->execute([':k' => 'logo_mime', ':v' => $mimeType,   ':u' => $user['webuntis_user']]);

    ob_end_clean();
        Response::json(['ok' => true, 'mime' => $mimeType]);
}

function handleLogoAusliefern(PDO $db, array $config, array $input): void
{
    // Öffentlicher Endpunkt – kein Login nötig, aber kein Pfad ans Frontend
    $row = $db->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'logo_pfad'"
    )->fetchColumn();

    if (!$row || !is_file($row)) {
        http_response_code(404);
        exit;
    }

    $mime = $db->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'logo_mime'"
    )->fetchColumn() ?: 'image/png';

    // Cache-Header: Logo ändert sich selten
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . filesize($row));
    readfile($row);
    exit;
}

function handleLogoLoeschen(PDO $db, array $config, array $input): void
{
    $user = Guard::requireAdmin($db);

    $pfad = $db->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'logo_pfad'"
    )->fetchColumn();

    if ($pfad && is_file($pfad)) {
        unlink($pfad);
    }

    $stmt = $db->prepare(
        "INSERT OR REPLACE INTO einstellungen (schluessel, wert, geaendert_am, geaendert_von)
         VALUES (:k, '', datetime('now'), :u)"
    );
    $stmt->execute([':k' => 'logo_pfad', ':u' => $user['webuntis_user']]);
    $stmt->execute([':k' => 'logo_mime', ':u' => $user['webuntis_user']]);

    Response::json(['ok' => true]);
}

function handleEinstellungenAktivieren(PDO $db, array $config, array $input): void
{
    $user  = Guard::requireAdmin($db);
    $aktiv = isset($input['aktiv']) ? ($input['aktiv'] ? '1' : '0') : '1';

    $db->prepare(
        "INSERT OR REPLACE INTO einstellungen (schluessel, wert, geaendert_am, geaendert_von)
         VALUES ('vorschau_aktiv', :v, datetime('now'), :u)"
    )->execute([':v' => $aktiv, ':u' => $user['webuntis_user']]);

    Response::json(['ok' => true, 'aktiv' => $aktiv === '1']);
}

function handleEinstellungenZuruecksetzen(PDO $db, array $config, array $input): void
{
    $user = Guard::requireAdmin($db);

    // Logo-Datei löschen
    $pfad = $db->query(
        "SELECT wert FROM einstellungen WHERE schluessel = 'logo_pfad'"
    )->fetchColumn();
    if ($pfad && is_file($pfad)) {
        unlink($pfad);
    }

    // Alle Einstellungen auf Standard zurücksetzen
    $defaults = [
        'schulname'       => 'Meine Schule',
        'app_titel'       => 'Schulprozesse',
        'farbe_akzent'    => '#5B6FA8',
        'farbe_sekundaer' => '#D98A2B',
        'logo_pfad'       => '',
        'logo_mime'       => '',
        'vorschau_aktiv'  => '0',
    ];

    $stmt = $db->prepare(
        "INSERT OR REPLACE INTO einstellungen (schluessel, wert, geaendert_am, geaendert_von)
         VALUES (:k, :v, datetime('now'), :u)"
    );
    foreach ($defaults as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => $v, ':u' => $user['webuntis_user']]);
    }

    Response::json(['ok' => true]);
}

/**
 * Temporärer Debug-Endpunkt – NUR für Diagnose, danach entfernen!
 * GET https://prozesse.hornse.de/api/debug/upload
 */
function handleDebugUpload(PDO $db, array $config, array $input): void
{
    // Alle empfangenen HTTP-Header ausgeben
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $headers[str_replace('_', '-', substr($k, 5))] = $v;
        }
    }
    $info = [
        'method'         => $_SERVER['REQUEST_METHOD'],
        'content_type'   => $_SERVER['CONTENT_TYPE'] ?? 'nicht gesetzt',
        'headers'        => $headers,
        'files_count'    => count($_FILES),
        'files'          => $_FILES,
        'post'           => $_POST,
        'input_length'   => strlen(file_get_contents('php://input')),
    ];
    \App\Response::json($info);
}
