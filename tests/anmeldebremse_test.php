<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * tests/anmeldebremse_test.php – die Anmeldebremse: Wartezeit statt Sperre
 *
 * Auftrag aus koordination (AUFTRAG-anmeldebremse-reihe.md, 16.10.2026):
 * die Bauart der Reihe, Vorbild lernzeiten (E406) und sprechtag (E35/E37).
 * Bis hier sperrte WebUntisAuth nach 5 Fehlversuchen 15 Minuten lang – auch
 * den Inhaber mit dem richtigen Passwort – und das lokale Notfallpasswort
 * wurde vor der Bremse geprüft, also gar nicht gebremst.
 *
 * AUSGEFÜHRT gegen SQLite, mit der Tabelle login_log aus
 * migrations/001_init.sql – nicht mit einer nachgebildeten.
 *
 * Aufruf: php tests/anmeldebremse_test.php   → Exit-Code 0 = alles grün
 */

declare(strict_types=1);

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

$W = dirname(__DIR__);
foreach (['backend/src/Auth/Anmeldebremse.php', 'backend/src/Auth/WebUntisAuth.php'] as $d) {
    if (is_file("$W/$d")) require_once "$W/$d";
}
use App\Auth\Anmeldebremse;
use App\Auth\WebUntisAuth;

$da = class_exists(Anmeldebremse::class);
pruefe('Voraussetzung: App\Auth\Anmeldebremse vorhanden', $da);

const SCHWELLE = 5;     // config.example.php, security.max_failed_logins
const FENSTER  = 15;    // config.example.php, security.lockout_minutes

// ---- das Urteil, rein ------------------------------------------------------
echo PHP_EOL . 'Das Urteil: ab der Schwelle 60 Sekunden, ohne Staffel' . PHP_EOL;
$u = fn(?array $lage): array => $da ? Anmeldebremse::urteil($lage, SCHWELLE) : ['durch' => null, 'status' => 0, 'meldung' => ''];
$l = fn(int $n, ?int $seit): array => ['fehlversuche' => $n, 'seit' => $seit];
pruefe('unter der Schwelle (4): durch', $u($l(4, 0))['durch'] === true);
pruefe('an der Schwelle (5), gerade eben: 429, „60 Sekunden“',
    $u($l(5, 0))['status'] === 429 && str_contains($u($l(5, 0))['meldung'], '60 Sekunden'));
pruefe('auf der Grenze: 59 s nach dem letzten noch „1 Sekunde“, nach 60 s durch',
    $u($l(5, 59))['status'] === 429 && str_contains($u($l(5, 59))['meldung'], '1 Sekunde.')
    && $u($l(5, 60))['durch'] === true);
pruefe('ohne Staffel: 30 Fehlversuche warten wie 5 – 60 Sekunden', str_contains($u($l(30, 0))['meldung'], '60 Sekunden'));
pruefe('Zählung gescheitert: 503 mit eigenem Satz, nicht „Zugangsdaten prüfen“',
    $u(null)['status'] === 503 && $u(null)['durch'] === false
    && str_contains($u(null)['meldung'], 'Anmeldeversuche') && !str_contains($u(null)['meldung'], 'Zugangsdaten'));

// ---- ausgeführt gegen SQLite ----------------------------------------------
$ddl = (string)file_get_contents("$W/migrations/001_init.sql");
$tabelle = preg_match('/CREATE TABLE IF NOT EXISTS login_log \(.*?\);/s', $ddl, $m) === 1 ? $m[0] : '';
pruefe('Voraussetzung: login_log aus migrations/001_init.sql gelesen', $tabelle !== '');
function neue_db(): PDO
{
    global $tabelle;
    $p = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $p->exec($tabelle);
    // KENNUNGEN BEGINNEN NICHT BEI 0 – in lernzeiten war eine Prüfung blind,
    // weil sie bei 1 begannen.
    $p->exec("INSERT INTO sqlite_sequence (name, seq) VALUES ('login_log', 4710)");
    return $p;
}
function fehl(PDO $p, string $wer, array $alter): void
{
    foreach ($alter as $a) {
        $p->prepare("INSERT INTO login_log (webuntis_user, erfolgreich, grund, ip, zeitpunkt)
                     VALUES (?, 0, 'falsches_passwort', '', datetime('now', ?))")->execute([$wer, '-' . $a . ' seconds']);
    }
}
$zeilen = fn(PDO $p): int => (int)$p->query('SELECT COUNT(*) FROM login_log')->fetchColumn();
$beginnen = function (PDO $p, string $wer) use ($da): array {
    if (!$da) return ['id' => 0, 'status' => 0, 'meldung' => ''];
    // Jede Ausnahme wird an der Prüfung rot, die sie auslöst – nicht als
    // Abbruch der Suite (gefunden mit Mutation S5).
    try { $r = Anmeldebremse::beginnen($p, $wer, '', SCHWELLE, FENSTER); }
    catch (Throwable $e) { echo '    (Ausnahme: ' . get_class($e) . ' ' . $e->getMessage() . ')' . PHP_EOL;
                           return ['id' => 0, 'status' => -1, 'meldung' => '']; }
    return ['id' => $r['id'], 'status' => $r['urteil']['status'], 'meldung' => $r['urteil']['meldung']];
};
$anmelden = function (PDO $p, string $wer) use ($beginnen, $da): void {
    $r = $beginnen($p, $wer);
    if ($da && $r['status'] === 200) Anmeldebremse::abschliessen($p, $r['id'], true, null);
};

echo PHP_EOL . 'Zuerst eintragen, dann zählen' . PHP_EOL;
$p = neue_db();
$r = $beginnen($p, 'ho');
$z = $r['status'] === 200 ? $p->query('SELECT * FROM login_log WHERE id = ' . (int)$r['id'])->fetch() : false;
pruefe('ohne Vorgeschichte: durch, der Versuch steht schon da – laufend, Kennung ab 4711',
    $r['status'] === 200 && is_array($z) && $z['webuntis_user'] === 'ho' && (int)$z['erfolgreich'] === 0
    && $z['grund'] === '(läuft)' && (int)$z['id'] >= 4711);
$p = neue_db();
fehl($p, 'ho', [30, 30, 30, 30]);
$r1 = $beginnen($p, 'ho');
$r2 = $beginnen($p, 'ho');
pruefe('gleichzeitig: 4 Fehlversuche, zwei Anfragen – die erste läuft, die zweite sieht sie und wartet',
    $r1['status'] === 200 && $r2['status'] === 429);
pruefe('der abgewiesene Versuch zählt nicht: seine Zeile ist wieder fort', $zeilen($p) === 5);

echo PHP_EOL . 'Im Tempo einer Person' . PHP_EOL;
$p = neue_db();
fehl($p, 'ho', [75, 60, 45, 30, 15]);
$r = $beginnen($p, 'ho');
pruefe('fünf Fehlversuche im Abstand von 15 s, der letzte vor 15 s: der sechste wartet, 44–45 s, mit Satz',
    $r['status'] === 429 && preg_match('/noch (44|45) Sekunden\./', $r['meldung']) === 1);
$p = neue_db();
fehl($p, 'ho', [600, 540, 480, 420, 61]);
pruefe('… der letzte vor 61 s: durch – keine Sperre über 15 Minuten', $beginnen($p, 'ho')['status'] === 200);

echo PHP_EOL . 'Die Grenze, ausgeführt' . PHP_EOL;
$p = neue_db();
fehl($p, 'ho', [300, 290, 280, 270, 58]);
pruefe('der letzte von 5 vor 58 s: abgewiesen, 1–2 s Rest',
    preg_match('/noch [12] Sekunden?\./', $beginnen($p, 'ho')['meldung']) === 1);
$p = neue_db();
fehl($p, 'ho', [300, 290, 280, 270, 60]);
pruefe('der letzte von 5 vor 60 s: durch', $beginnen($p, 'ho')['status'] === 200);
$p = neue_db();
fehl($p, 'ho', [17 * 60, 17 * 60, 17 * 60, 17 * 60, 1]);
pruefe('Fenster: 4 Fehlversuche vor 17 Minuten und einer vor 1 s – nur der eine zählt, durch',
    $beginnen($p, 'ho')['status'] === 200);
$p = neue_db();
fehl($p, 'andere', [1, 1, 1, 1, 1, 1]);
pruefe('je Benutzer: 6 fremde Fehlversuche gerade eben zählen nicht', $beginnen($p, 'ho')['status'] === 200);

echo PHP_EOL . 'Eine gelungene Anmeldung setzt zurück' . PHP_EOL;
$p = neue_db();
fehl($p, 'ho', [400, 380, 360, 340, 320]);
$anmelden($p, 'ho');
fehl($p, 'ho', [1]);
pruefe('Rücksetzen: 5 Fehlversuche, dann angemeldet, dann einer vor 1 s – durch', $beginnen($p, 'ho')['status'] === 200);
$p = neue_db();
fehl($p, 'ho', [400, 380, 360, 340, 320, 1]);
pruefe('Gegenprobe: dieselben sechs ohne Anmeldung dazwischen – es wird gewartet', $beginnen($p, 'ho')['status'] === 429);
$p = neue_db();
fehl($p, 'ho', [400, 380, 360, 340]);
$anmelden($p, 'ho');
$zp = $p->query('SELECT zeitpunkt FROM login_log WHERE erfolgreich = 1')->fetchColumn();
for ($i = 0; $i < 5; $i++) {
    $p->prepare("INSERT INTO login_log (webuntis_user, erfolgreich, grund, ip, zeitpunkt) VALUES ('ho', 0, 'falsches_passwort', '', ?)")
      ->execute([(string)$zp]);
}
pruefe('nach der Nummer: fünf Fehlversuche in derselben Sekunde wie die Anmeldung, danach eingetragen – sie zählen',
    $zp !== false && $beginnen($p, 'ho')['status'] === 429);
$p = neue_db();
fehl($p, 'andere', [400, 380, 360, 340, 320]);
$anmelden($p, 'ho');
fehl($p, 'andere', [1]);
pruefe('je Benutzer: die Anmeldung eines anderen setzt nicht zurück', $beginnen($p, 'andere')['status'] === 429);

echo PHP_EOL . 'Die bekannte Grenze: ein Skript im Minutentakt' . PHP_EOL;
$p = neue_db();
fehl($p, 'ho', [241, 181, 121, 61, 1]);
$r = $beginnen($p, 'ho');
pruefe('Skript im Minutentakt, der letzte vor 1 s: der Inhaber muss warten (58–59 s)',
    $r['status'] === 429 && preg_match('/noch (58|59) Sekunden\./', $r['meldung']) === 1);
$p = neue_db();
fehl($p, 'ho', [300, 240, 180, 120, 60]);
pruefe('… und der nächste Versuch des Skripts nach der Minute ist wieder durch', $beginnen($p, 'ho')['status'] === 200);

echo PHP_EOL . 'Die Datenbank antwortet nicht' . PHP_EOL;
$p = neue_db();
$p->exec('DROP TABLE login_log');
$r = $beginnen($p, 'ho');
pruefe('ohne Tabelle: abgewiesen mit 503 und eigenem Satz, nicht „Zugangsdaten prüfen“',
    $r['status'] === 503 && str_contains($r['meldung'], 'Anmeldeversuche') && !str_contains($r['meldung'], 'Zugangsdaten'));

echo PHP_EOL . 'Ohne Entscheid: WebUntis nicht erreichbar' . PHP_EOL;
$p = neue_db();
$r = $beginnen($p, 'ho');
if ($da && $r['status'] === 200) Anmeldebremse::ohneEntscheid($p, $r['id']);
pruefe('ohne Entscheid: der laufende Eintrag verschwindet, nichts bleibt', $da && $zeilen($p) === 0);
$cfg = ['webuntis' => ['base_url' => 'https://127.0.0.1:9', 'school' => 'probe', 'client' => 'probe',
                       'allowed_person_types' => [2]], 'security' => []];
$wu = class_exists(WebUntisAuth::class) ? new WebUntisAuth($cfg, neue_db()) : null;
$erg = $wu?->authenticate('ho', 'irgendwas');
pruefe('WebUntisAuth: WebUntis antwortet nicht – null, Grund „ohne_entscheid“, und es schreibt nichts mehr selbst',
    $wu !== null && $erg === null && method_exists($wu, 'grund') && $wu->grund() === 'ohne_entscheid');

// ---- der ganze Weg: handleLogin ausgeführt ----------------------------------
// Response und Session enden im Betrieb mit exit bzw. setzen eine Sitzung.
// Hier ersetzt: Response wirft die Antwort, Session merkt sich nur den Namen.
// WebUntis zeigt auf einen Port, an dem niemand antwortet – der Notfall.
echo PHP_EOL . 'Der ganze Weg: handleLogin, WebUntis nicht erreichbar' . PHP_EOL;
final class Antwort extends Exception
{
    public function __construct(public int $status, public mixed $daten) { parent::__construct('antwort'); }
}
eval('namespace App; final class Response {
    public static function json(mixed $d, int $s = 200): never { throw new \Antwort($s, $d); }
    public static function error(string $m, int $s = 400): never { throw new \Antwort($s, ["error" => $m]); }
} final class Session { public static ?array $wer = null; public static function login(array $u): void { self::$wer = $u; } }');
require_once "$W/backend/api/auth.php";
$rollen = preg_match('/CREATE TABLE IF NOT EXISTS benutzer_rollen \(.*?\);/s', $ddl, $m) === 1 ? $m[0] : '';
$config = ['webuntis' => ['base_url' => 'https://127.0.0.1:9', 'school' => 'probe', 'client' => 'probe',
                          'allowed_person_types' => [2]],
           'security' => ['max_failed_logins' => SCHWELLE, 'lockout_minutes' => FENSTER]];
$notfall = function () use ($rollen): PDO {
    $p = neue_db();
    $p->exec($rollen);
    $p->prepare("INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle, passwort_hash) VALUES ('notfalladmin', 'Notfall', 'admin', ?)")
      ->execute([password_hash('Der Notfall ist heute', PASSWORD_BCRYPT)]);
    return $p;
};
$login = function (PDO $p, string $wer, string $pw) use ($config): array {
    try { handleLogin($p, $config, ['username' => $wer, 'password' => $pw]); return ['status' => -1, 'daten' => null]; }
    catch (Antwort $a) { return ['status' => $a->status, 'daten' => $a->daten]; }
};
$p = $notfall();
$r = $login($p, 'notfalladmin', 'falsch-1');
$alle = $p->query('SELECT erfolgreich, grund FROM login_log')->fetchAll();
pruefe('Notfallpasswort falsch, WebUntis tot: 401 – und der Versuch zählt, denn das lokale Passwort hat entschieden',
    $r['status'] === 401 && count($alle) === 1 && (int)$alle[0]['erfolgreich'] === 0);
$p = $notfall();
for ($i = 0; $i < SCHWELLE; $i++) $login($p, 'notfalladmin', 'falsch-' . $i);
$r = $login($p, 'notfalladmin', 'Der Notfall ist heute');
pruefe('fünf falsche Notfallpasswörter gerade eben: auch das richtige wartet – 429 mit Sekunden',
    $r['status'] === 429 && preg_match('/noch \d+ Sekunden?\./', (string)($r['daten']['error'] ?? '')) === 1);
$p = $notfall();
$r = $login($p, 'notfalladmin', 'Der Notfall ist heute');
$alle = $p->query('SELECT erfolgreich, grund FROM login_log')->fetchAll();
pruefe('Notfallpasswort richtig: angemeldet, eine Zeile – erfolgreich, „lokal“',
    $r['status'] === 200 && count($alle) === 1 && (int)$alle[0]['erfolgreich'] === 1 && $alle[0]['grund'] === 'lokal');
$p = $notfall();
$r = $login($p, 'ho', 'irgendwas');
pruefe('ohne Notfallpasswort, WebUntis tot: 401, und es bleibt keine Zeile – kein Passwort hat entschieden',
    $r['status'] === 401 && $zeilen($p) === 0);

// ---- die Aufrufstelle -------------------------------------------------------
// Rückfall, gekennzeichnet: handleLogin endet mit Response::…(exit) und wird
// hier nicht ausgeführt. Geprüft wird die Reihenfolge im Quelltext.
echo PHP_EOL . 'Die Aufrufstelle (Quelltext, Rückfall)' . PHP_EOL;
$auth = (string)file_get_contents("$W/backend/api/auth.php");
$rumpf = preg_match('/^function handleLogin\(.*?^\}/ms', $auth, $m) === 1 ? $m[0] : '';
$b = strpos($rumpf, 'bremseBeginnen(');
$v = strpos($rumpf, 'password_verify(');
$w = strpos($rumpf, '->authenticate(');
pruefe('handleLogin fragt die Bremse vor dem Notfallpasswort und vor WebUntis',
    $rumpf !== '' && $b !== false && $v !== false && $w !== false && $b < $v && $b < $w);
pruefe('in login_log schreibt nur noch die Anmeldebremse – kein INSERT in auth.php und WebUntisAuth.php',
    !str_contains($auth, 'INSERT INTO login_log')
    && !str_contains((string)file_get_contents("$W/backend/src/Auth/WebUntisAuth.php"), 'INSERT INTO login_log'));

echo PHP_EOL . ($fehler === 0 ? 'ALLE TESTS GRÜN' : "$fehler ROT") . PHP_EOL;
exit($fehler === 0 ? 0 : 1);
