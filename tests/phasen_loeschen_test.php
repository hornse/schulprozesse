<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * tests/phasen_loeschen_test.php – Phasen löschen nur für Admins (Z4)
 *
 * handleDeletePhase (backend/api/phasen.php) wird AUSGEFÜHRT, mit den echten
 * Klassen Guard, Response und Session, gegen eine SQLite-Datei aus
 * migrations/00*.sql – je Fall eine frische Datei, je Aufruf ein eigener
 * PHP-Prozess (diese Datei mit --kind), weil Response mit exit endet.
 *
 * Bis zu Z4 durfte jeder, der in IRGENDEINEM Prozess verantwortlich ist, JEDE
 * Phase löschen – mit ihren Vorlagen und den Schritten darin in allen
 * Prozessen. Die Regel stammt aus b45e84c (25.06.2026), als der
 * Vorlagen-Editor noch auf der Seite „Prozess verwalten“ lag; heute steht er
 * nur auf der Admin-Seite, und Verantwortliche ändern die Phasen ihres
 * Prozesses über /api/prozesse/{id}/instanz-phasen. Jetzt: nur Admins, wie
 * Anlegen, Ändern und Reihenfolge der Phasen.
 *
 * Gemessen wird der Zustand der Datenbank vorher und nachher, nicht nur der
 * Status. Alle Daten sind erfunden.
 *
 * Aufruf: php tests/phasen_loeschen_test.php   → Exit-Code 0 = alles grün
 */

declare(strict_types=1);

$W = dirname(__DIR__);

if (($argv[1] ?? '') === '--kind') {            // --kind <db> <user|-> <phase_id>
    [, , $dbPfad, $wer, $phase] = $argv;
    error_reporting(E_ALL);
    ini_set('display_errors', 'stderr');
    ini_set('log_errors', '0');
    spl_autoload_register(function (string $c) use ($W): void {
        if (str_starts_with($c, 'App\\')) require "$W/backend/src/" . str_replace('\\', '/', substr($c, 4)) . '.php';
    });
    require "$W/backend/api/phasen.php";
    $db = new PDO("sqlite:$dbPfad", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    if ($wer !== '-') $_SESSION['user'] = ['webuntis_user' => $wer, 'anzeigename' => 'x', 'rolle' => 'x'];
    register_shutdown_function(function (): void {
        $s = http_response_code();
        fwrite(STDERR, PHP_EOL . 'STATUS=' . ($s === false ? 200 : $s) . PHP_EOL);
    });
    handleDeletePhase($db, [], [], ['id' => $phase]);
    exit;
}

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

$DB = sys_get_temp_dir() . '/phasen_loeschen_' . getmypid() . '.sqlite';
register_shutdown_function(function () use ($DB): void { if (is_file($DB)) unlink($DB); });

// Aufbau (erfunden), Kennungen ab 41 bzw. 71:
//   P41: tn verantwortlich, mit mitarbeitend   P42: andere verantwortlich
//   Phase 71 „prozess“, Vorlagen 81–83, Schritte nur in P42 (3)
//   Phase 72 „prozess“, Vorlagen 84–85, Schritte nur in P41 (2)
//   Standardphase 1 aus migrations/002, Schritte in P42 (je Vorlage einer)
function aufbau(): array
{
    global $W, $DB;
    if (is_file($DB)) unlink($DB);
    $db = new PDO("sqlite:$DB", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (glob("$W/migrations/00[0-9]_*.sql") as $f) $db->exec((string)file_get_contents($f));
    $db->exec("UPDATE sqlite_sequence SET seq = 40 WHERE name = 'prozesse'");
    $db->exec("INSERT INTO prozesse (label, oeffentlich) VALUES ('Probe A', 0), ('Probe B', 0)");
    $db->exec("INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle) VALUES
        ('tn', 'Probe2', 'mitglied'), ('mit', 'Probe4', 'mitglied'), ('adm', 'Probe3', 'admin')");
    $db->exec("INSERT INTO prozess_teilnehmer VALUES (41, 'tn', 'verantwortlich'), (41, 'mit', 'mitarbeitend'), (42, 'andere', 'verantwortlich')");
    $db->exec("INSERT INTO phasen (id, name, reihenfolge, quelle) VALUES (71, 'Probe-Phase B', 9, 'prozess'), (72, 'Probe-Phase A', 10, 'prozess')");
    $db->exec("INSERT INTO schritt_vorlagen (id, phase_id, reihenfolge, titel) VALUES
        (81, 71, 1, 'a'), (82, 71, 2, 'b'), (83, 71, 3, 'c'), (84, 72, 1, 'd'), (85, 72, 2, 'e')");
    $db->exec("INSERT INTO schritt_instanzen (prozess_id, vorlage_id) VALUES (42, 81), (42, 82), (42, 83), (41, 84), (41, 85)");
    foreach ($db->query('SELECT id FROM schritt_vorlagen WHERE phase_id = 1')->fetchAll(PDO::FETCH_COLUMN) as $v)
        $db->exec("INSERT INTO schritt_instanzen (prozess_id, vorlage_id) VALUES (42, $v)");
    return stand();
}
/** Zählwerte: Schritte je Prozess, Phasen 1/71/72, Vorlagen je Phase. */
function stand(): array
{
    global $DB;
    $db = new PDO("sqlite:$DB");
    $z = fn(string $sql): int => (int)$db->query($sql)->fetchColumn();
    return [
        'p41' => $z('SELECT COUNT(*) FROM schritt_instanzen WHERE prozess_id = 41'),
        'p42' => $z('SELECT COUNT(*) FROM schritt_instanzen WHERE prozess_id = 42'),
        'phasen' => $z('SELECT COUNT(*) FROM phasen WHERE id IN (1, 71, 72)'),
        'v1' => $z('SELECT COUNT(*) FROM schritt_vorlagen WHERE phase_id = 1'),
        'v71' => $z('SELECT COUNT(*) FROM schritt_vorlagen WHERE phase_id = 71'),
        'v72' => $z('SELECT COUNT(*) FROM schritt_vorlagen WHERE phase_id = 72'),
    ];
}
function loesche(string $wer, int $phase): array
{
    global $DB;
    $vorher = aufbau();
    $p = proc_open([PHP_BINARY, __FILE__, '--kind', $DB, $wer, (string)$phase], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $r);
    $aus = (string)stream_get_contents($r[1]); $err = (string)stream_get_contents($r[2]); proc_close($p);
    $json = json_decode($aus, true);
    return ['status' => preg_match('/STATUS=(\d+)/', $err, $m) === 1 ? (int)$m[1] : -1,
            'antwort' => $json, 'vorher' => $vorher, 'nachher' => stand()];
}

const NUR_ADMIN = 'Diese Aktion ist nur für Administratoren möglich.';
$abgelehnt = fn(array $a): bool => $a['status'] === 403 && ($a['antwort']['error'] ?? null) === NUR_ADMIN
    && $a['nachher'] === $a['vorher'];

$v = aufbau();
$v1 = $v['v1'];
pruefe("Voraussetzung: Aufbau wie beschrieben (P41 2 Schritte, P42 " . (3 + $v1) . ", drei Phasen, Standardphase 1 mit $v1 Vorlagen)",
    $v1 > 0 && $v === ['p41' => 2, 'p42' => 3 + $v1, 'phasen' => 3, 'v1' => $v1, 'v71' => 3, 'v72' => 2]);

echo PHP_EOL . 'Verantwortliche löschen keine Phase' . PHP_EOL;
pruefe('Verantwortlicher von P41, Phase mit Schritten nur in P42: 403 „' . NUR_ADMIN . '“, nichts gelöscht',
    $abgelehnt(loesche('tn', 71)));
pruefe('Verantwortlicher von P41, Phase mit Schritten nur in P41: ebenso 403, nichts gelöscht',
    $abgelehnt(loesche('tn', 72)));
pruefe('Verantwortlicher von P41, Standardphase der Vorlage: 403, nichts gelöscht',
    $abgelehnt(loesche('tn', 1)));
pruefe('Mitarbeitender: 403, nichts gelöscht', $abgelehnt(loesche('mit', 71)));

echo PHP_EOL . 'Admin löscht' . PHP_EOL;
$a = loesche('adm', 71);
pruefe('Admin löscht Phase 71: 200, Phase, ihre 3 Vorlagen und die 3 Schritte in P42 sind weg, sonst nichts',
    $a['status'] === 200 && $a['antwort'] === ['ok' => true]
    && $a['nachher'] === array_merge($a['vorher'], ['p42' => $a['vorher']['p42'] - 3, 'phasen' => 2, 'v71' => 0]));

echo PHP_EOL . 'Ohne Anmeldung' . PHP_EOL;
$a = loesche('-', 71);
pruefe('ohne Anmeldung: 401, nichts gelöscht',
    $a['status'] === 401 && ($a['antwort']['error'] ?? null) === 'Nicht angemeldet.' && $a['nachher'] === $a['vorher']);

exit($fehler === 0 ? 0 : 1);
