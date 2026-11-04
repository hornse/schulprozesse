<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * tests/export_zugriff_test.php – Export nur für Teilnehmende und Admins (Z3,
 * koordination AUFTRAG-schulprozesse-export.md)
 *
 * Die drei Endpunkte aus backend/api/export.php werden AUSGEFÜHRT, mit den
 * echten Klassen Guard, Response und Session, gegen eine SQLite-Datei aus
 * migrations/00*.sql. Jeder Aufruf läuft in einem eigenen PHP-Prozess (diese
 * Datei mit --kind), weil die CSV-Endpunkte mit exit enden und Response
 * selbst exit ruft. Gezählt werden Status, Zeilen und PHP-Hinweise; Werte
 * werden nicht ausgegeben. Alle Daten sind erfunden.
 *
 * Die letzte Prüfung ist eine am QUELLTEXT (Engstelle, REIHENREGELN §1): Sie
 * belegt das Vorkommen einer Prozessprüfung im Rumpf, nicht ihre Wirkung.
 * Die Wirkung belegen die ausgeführten Prüfungen davor.
 *
 * Aufruf: php tests/export_zugriff_test.php   → Exit-Code 0 = alles grün
 */

declare(strict_types=1);

$W = dirname(__DIR__);

// ---------------------------------------------------------------- Kindprozess
if (($argv[1] ?? '') === '--kind') {            // --kind <db> <handler> <user|-> <prozess_id|->
    [, , $dbPfad, $handler, $wer, $pid] = $argv;
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');        // Hinweise nicht in die gezählte Antwort
    ini_set('log_errors', '0');
    spl_autoload_register(function (string $c) use ($W): void {
        if (str_starts_with($c, 'App\\')) require "$W/backend/src/" . str_replace('\\', '/', substr($c, 4)) . '.php';
    });
    require "$W/backend/api/export.php";
    $db = new PDO("sqlite:$dbPfad", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($wer !== '-') $_SESSION['user'] = ['webuntis_user' => $wer, 'anzeigename' => 'x', 'rolle' => 'x'];
    register_shutdown_function(function (): void {
        $s = http_response_code();
        fwrite(STDERR, PHP_EOL . 'STATUS=' . ($s === false ? 200 : $s) . PHP_EOL);
    });
    $handler($db, [], $pid === '-' ? [] : ['prozess_id' => $pid]);
    exit;
}

// ---------------------------------------------------------------- Suite
$fehler = 0;
function pruefe(string $name, bool $ok): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . PHP_EOL;
    if (!$ok) $fehler++;
}
set_exception_handler(function (Throwable $e): void {
    echo '  ✗ Suite abgebrochen: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    exit(1);
});

$DB = sys_get_temp_dir() . '/export_zugriff_' . getmypid() . '.sqlite';
register_shutdown_function(function () use ($DB): void { if (is_file($DB)) unlink($DB); });

// Erfundene Daten. Kennungen ab 41; jeder Prozess mit eigener Zeilenzahl,
// damit erkennbar ist, WELCHER geliefert wurde.
//   P41 privat, Teilnehmer tn          2 Schritte, 3 Aktivitäten
//   P42 privat, jüngster, Teiln. andere 4 Schritte, 5 Aktivitäten
//   P43 öffentlich, Teilnehmer andere   3 Schritte, 1 Aktivität
// Der Prozess aus migrations/002 bleibt (öffentlich, älter, ohne Schritte).
const ERWARTET = [41 => ['schritte' => 2, 'akt' => 3], 42 => ['schritte' => 4, 'akt' => 5], 43 => ['schritte' => 3, 'akt' => 1]];
$db = new PDO("sqlite:$DB", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (glob("$W/migrations/00[0-9]_*.sql") as $f) $db->exec((string)file_get_contents($f));
$db->exec("UPDATE sqlite_sequence SET seq = 40 WHERE name = 'prozesse'");
$db->exec("INSERT INTO prozesse (label, oeffentlich, erstellt_am) VALUES
    ('Probe A', 0, '2031-02-01 10:00:00'), ('Probe B', 0, '2031-03-01 10:00:00'), ('Probe C', 1, '2030-01-01 10:00:00')");
$db->exec("INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle) VALUES
    ('aussen', 'Probe1', 'mitglied'), ('tn', 'Probe2', 'mitglied'), ('adm', 'Probe3', 'admin')");
$db->exec("INSERT INTO prozess_teilnehmer VALUES (41, 'tn', 'verantwortlich'), (42, 'andere', 'verantwortlich'), (43, 'andere', 'verantwortlich')");
$vorlagen = $db->query('SELECT id FROM schritt_vorlagen ORDER BY id LIMIT 4')->fetchAll(PDO::FETCH_COLUMN);
foreach (ERWARTET as $p => $n) {
    for ($i = 0; $i < $n['schritte']; $i++)
        $db->exec("INSERT INTO schritt_instanzen (prozess_id, vorlage_id, kommentar, erledigt_von) VALUES ($p, {$vorlagen[$i]}, 'erfunden', 'erfunden')");
    for ($i = 0; $i < $n['akt']; $i++)
        $db->exec("INSERT INTO aktivitaeten (prozess_id, ereignis, anzeigename, wert_neu) VALUES ($p, 'kommentar_gesetzt', 'erfunden', 'erfunden')");
}
$zaehle = fn(string $t, int $p): int => (int)$db->query("SELECT COUNT(*) FROM $t WHERE prozess_id = $p")->fetchColumn();
$ok = count($vorlagen) === 4;
foreach (ERWARTET as $p => $n) $ok = $ok && $zaehle('schritt_instanzen', $p) === $n['schritte'] && $zaehle('aktivitaeten', $p) === $n['akt'];
$juengster = (int)$db->query('SELECT id FROM prozesse ORDER BY erstellt_am DESC LIMIT 1')->fetchColumn();
$db = null;
pruefe('Voraussetzung: Schema aus migrations/, Testdaten mit den erwarteten Mengen, P42 ist der jüngste Prozess', $ok && $juengster === 42);

/** Ein Aufruf; gibt Status, Zahl der Datensätze (JSON-Einträge bzw. CSV-Zeilen ohne Kopf), Fehlertext, Hinweise. */
function rufe(string $handler, string $wer, string $pid): array
{
    global $DB;
    $p = proc_open([PHP_BINARY, __FILE__, '--kind', $DB, $handler, $wer, $pid], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $r);
    $aus = (string)stream_get_contents($r[1]); $err = (string)stream_get_contents($r[2]); proc_close($p);
    $status = preg_match('/STATUS=(\d+)/', $err, $m) === 1 ? (int)$m[1] : -1;
    $json = json_decode($aus, true);
    $fehlertext = is_array($json) && isset($json['error']) ? $json['error'] : null;
    if ($handler === 'handleListAktivitaeten') {
        $zeilen = is_array($json) && array_is_list($json) ? count($json) : -1;
    } else {
        // CSV beginnt mit BOM und Kopfzeile; fputcsv schreibt je Datensatz eine Zeile.
        $zeilen = str_starts_with($aus, "\xEF\xBB\xBF") ? substr_count($aus, "\n") - 1 : -1;
    }
    return ['status' => $status, 'zeilen' => $zeilen, 'fehler' => $fehlertext,
            'hinweise' => preg_match_all('/^(PHP )?(Deprecated|Notice|Warning)/m', $err)];
}

const ENDPUNKTE = [
    'handleListAktivitaeten'   => ['GET /api/aktivitaeten', 'akt'],
    'handleExportAktivitaeten' => ['GET /api/export/aktivitaeten', 'akt'],
    'handleExportCsv'          => ['GET /api/export/csv', 'schritte'],
];
const KEIN_ZUGRIFF = 'Kein Zugriff auf diesen Prozess.';
const OHNE_ID      = 'prozess_id fehlt.';

echo PHP_EOL . 'Nichtteilnehmer, privater Prozess: 403, keine Daten' . PHP_EOL;
foreach (ENDPUNKTE as $h => [$name]) {
    $a = rufe($h, 'aussen', '41');
    pruefe("$name: Mitglied ohne Teilnahme an P41 bekommt 403 „" . KEIN_ZUGRIFF . '“',
        $a['status'] === 403 && $a['fehler'] === KEIN_ZUGRIFF);
}

echo PHP_EOL . 'Teilnehmer bekommt seinen Prozess' . PHP_EOL;
foreach (ENDPUNKTE as $h => [$name, $art]) {
    $a = rufe($h, 'tn', '41');
    pruefe("$name: Teilnehmer an P41 bekommt 200 mit " . ERWARTET[41][$art] . ' Datensätzen',
        $a['status'] === 200 && $a['zeilen'] === ERWARTET[41][$art]);
}

echo PHP_EOL . 'Admin bekommt jeden Prozess' . PHP_EOL;
foreach (ENDPUNKTE as $h => [$name, $art]) {
    $a = rufe($h, 'adm', '42');
    pruefe("$name: Admin ohne Teilnahme bekommt P42, 200 mit " . ERWARTET[42][$art] . ' Datensätzen',
        $a['status'] === 200 && $a['zeilen'] === ERWARTET[42][$art]);
}

echo PHP_EOL . 'Ohne prozess_id kein Rückfall auf den jüngsten Prozess' . PHP_EOL;
foreach (ENDPUNKTE as $h => [$name]) {
    // fehlend, „null“ (leerer STATE.prozessId in app.js) und 0
    $alle = array_map(fn($pid) => rufe($h, 'aussen', $pid), ['-', 'null', '0']);
    pruefe("$name: fehlend, „null“ und 0 ergeben je 400 „" . OHNE_ID . '“',
        count(array_filter($alle, fn($a) => $a['status'] === 400 && $a['fehler'] === OHNE_ID)) === 3);
}

echo PHP_EOL . 'Öffentlicher Prozess: Export trotzdem nur für Teilnehmende' . PHP_EOL;
foreach (ENDPUNKTE as $h => [$name]) {
    $a = rufe($h, 'aussen', '43');
    pruefe("$name: Mitglied ohne Teilnahme am öffentlichen P43 bekommt 403",
        $a['status'] === 403 && $a['fehler'] === KEIN_ZUGRIFF);
}

echo PHP_EOL . 'Ohne Anmeldung: 401' . PHP_EOL;
foreach (ENDPUNKTE as $h => [$name]) {
    $a = rufe($h, '-', '41');
    pruefe("$name: ohne Anmeldung 401", $a['status'] === 401 && $a['fehler'] === 'Nicht angemeldet.');
}

// ---------------------------------------------------------------- Engstelle
// AM QUELLTEXT, nicht ausgeführt. Ermittelt, nicht aufgezählt:
//   (a) jede handle*-Funktion in backend/api/, die $input['prozess_id'] oder
//       $params['prozess_id'] liest,
//   (b) jeder Handler, dessen Route im Router eine Prozesskennung trägt
//       ((?P<prozess_id>…) oder api/prozesse/(?P<id>…)).
// Für jede muss im RUMPF Guard::requireProzessZugriff, ::requireProzessVerantwortlich
// oder ::requireAdmin stehen. Über Token: Kommentare und Zeichenketten zählen nicht.
// Grenze: belegt das Vorkommen, nicht Reihenfolge oder Argument.
echo PHP_EOL . 'Engstelle (am Quelltext): jeder Endpunkt mit Prozesskennung ruft eine Prozessprüfung' . PHP_EOL;
$rumpfe = [];   // Funktionsname => Token des Rumpfs
foreach (glob("$W/backend/api/*.php") as $datei) {
    $t = array_values(array_filter(PhpToken::tokenize((string)file_get_contents($datei)),
        fn($x) => !$x->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])));
    for ($i = 0, $n = count($t); $i < $n; $i++) {
        if (!$t[$i]->is(T_FUNCTION) || !isset($t[$i + 1]) || !$t[$i + 1]->is(T_STRING)
            || !str_starts_with($t[$i + 1]->text, 'handle')) continue;
        $name = $t[$i + 1]->text;
        for ($j = $i + 2; $j < $n && $t[$j]->text !== '{'; $j++);
        $tiefe = 0; $rumpf = [];
        for (; $j < $n; $j++) {
            if ($t[$j]->text === '{' || $t[$j]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) $tiefe++;
            if ($t[$j]->text === '}') $tiefe--;
            $rumpf[] = $t[$j];
            if ($tiefe === 0) break;
        }
        $rumpfe[$name] = $rumpf;
    }
}
$liestId = function (array $r): bool {
    for ($k = 0; $k + 2 < count($r); $k++) {
        if ($r[$k]->is(T_VARIABLE) && in_array($r[$k]->text, ['$input', '$params'], true)
            && $r[$k + 1]->text === '[' && $r[$k + 2]->is(T_CONSTANT_ENCAPSED_STRING)
            && trim($r[$k + 2]->text, '\'"') === 'prozess_id') return true;
    }
    return false;
};
$prueftProzess = function (array $r): bool {
    for ($k = 0; $k + 2 < count($r); $k++) {
        if ($r[$k]->text === 'Guard' && $r[$k + 1]->is(T_DOUBLE_COLON)
            && in_array($r[$k + 2]->text, ['requireProzessZugriff', 'requireProzessVerantwortlich', 'requireAdmin'], true)) return true;
    }
    return false;
};
$kandidaten = [];
foreach ($rumpfe as $name => $r) if ($liestId($r)) $kandidaten[$name] = true;
$router = (string)file_get_contents("$W/backend/public/api-router.php");
preg_match_all("/\\[\\s*'[A-Z]+',\\s*'(#[^']*#)',\\s*'(handle[A-Za-z]+)'\\s*\\]/", $router, $routen, PREG_SET_ORDER);
$ausRouter = 0;
foreach ($routen as [, $muster, $h]) {
    if (str_contains($muster, '(?P<prozess_id>') || str_starts_with($muster, '#^api/prozesse/(?P<id>')) { $kandidaten[$h] = true; $ausRouter++; }
}
$ohne = array_keys(array_filter($kandidaten, fn($_, $h) => !isset($rumpfe[$h]) || !$prueftProzess($rumpfe[$h]), ARRAY_FILTER_USE_BOTH));
$zahl = count($kandidaten);
if (count($rumpfe) === 0 || count($routen) === 0 || $zahl === 0 || $ausRouter === 0) {
    pruefe("nichts gefunden (Funktionen " . count($rumpfe) . ', Routen ' . count($routen) . ", Kandidaten $zahl, davon aus Routen $ausRouter) – nichts geprüft", false);
} else {
    pruefe("alle $zahl Endpunkte mit Prozesskennung (" . count($rumpfe) . ' Handler, ' . count($routen) . ' Routen gelesen) rufen eine Prozessprüfung'
        . ($ohne ? ' – OHNE: ' . implode(', ', $ohne) : ''), $ohne === []);
}

exit($fehler === 0 ? 0 : 1);
