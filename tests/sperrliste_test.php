<?php
/**
 * Schulprozesse – tests/sperrliste_test.php
 * Die Sperrliste im Router: was Apache sperrt, liefert auch php -S nicht aus
 *
 * Anlass: S62 (koordination, BEFUNDE-SAMMLUNG-2026-11). Der Router reichte
 * jede vorhandene Datei unter backend/public an php -S zurück (return false),
 * ohne Liste der Endungen; gemessen am 03.11.2026 auf dem Server:
 * /.htaccess 200, 826 Bytes. Auftrag: koordination
 * docs/AUFTRAG-router-sperrliste.md, Lauf 2.
 *
 * DIE LISTE IST ZWEIMAL DA – in der Apache-Vorlage und in
 * backend/src/sperrliste.php. Gemessen wird die zweite gegen die erste: Die
 * Endungen und die Punktregel werden aus der BEIGELEGTEN KOPIE
 * tests/daten/webroot.htaccess gelesen, nicht hier aufgezählt. Ob die Kopie
 * noch der Quelle entspricht, misst diese Prüfung nicht.
 *
 * Fünf Teile:
 *   A  die Sperrfunktion, je Endung aus der Kopie, klein und GROSS
 *   B  die Punktregel, je Probe gegen den Ausdruck aus der Kopie
 *   C  die WIRKUNG: der Router (byte-gleiche Kopie in einem Probebaum) unter
 *      php -S, Arbeitsverzeichnis backend/public wie im Dienst. Je Art eine
 *      Testdatei – erwartet 404 ohne Inhalt; Kontrollen 200. Dazu: /api/ geht
 *      VOR der Sperre an den API-Router (Entscheidung 2, Lauf 2)
 *   D  was nicht verlorengehen darf: jede verfolgte Datei unter backend/public
 *      durch den echten Router, 200 und Größe gleich; die verfolgte .htaccess
 *      (Apache-Rückfall, bleibt) 404 ohne Inhalt
 *   E  Aufrufstelle und Reihenfolge im Quelltext (Tokenizer) – nur Rückfall
 *      zu C, misst Quelltext, nicht Wirkung
 *
 * Die Testdateinamen in C, die Routen in C und die Kontrollen in A sind
 * ERFUNDEN.
 *
 * Aufruf: php tests/sperrliste_test.php   → Exit-Code 0 = alles grün
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

ini_set('display_errors', 'stderr');

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

$WURZEL = dirname(__DIR__);
$KOPIE  = __DIR__ . '/daten/webroot.htaccess';
$SPERR  = $WURZEL . '/backend/src/sperrliste.php';
$ROUTER = $WURZEL . '/backend/public/dev-router.php';

// ------------------------------------------------------------------
// Die Kopie lesen
// ------------------------------------------------------------------
if (!is_file($KOPIE)) {
    pruefe('beigelegte Kopie tests/daten/webroot.htaccess vorhanden', false);
    exit(1);
}
$vorlage = (string)file_get_contents($KOPIE);

// <FilesMatch "(?i)(\.(save(\.[0-9]+)?|swp|…|sh)|~)$">
$endungen = [];
$unbekannt = [];
if (preg_match('#<FilesMatch "\(\?i\)\(\\\\\.\((.+)\)\|~\)\$">#', $vorlage, $m) === 1) {
    foreach (explode('|', $m[1]) as $teil) {
        if (preg_match('/^[a-z0-9]+$/', $teil) === 1) {
            $endungen[] = '.' . $teil;
        } elseif (preg_match('/^([a-z0-9]+)\(\\\\\.\[0-9\]\+\)\?$/', $teil, $n) === 1) {
            $endungen[] = '.' . $n[1];
            $endungen[] = '.' . $n[1] . '.7';      // .save.N, N erfunden
        } else {
            $unbekannt[] = $teil;
        }
    }
    $endungen[] = '~';
}
pruefe('Kopie: ' . count($endungen) . ' Endungen gelesen (null wäre ein Fehler)'
    . ($unbekannt ? ', unbekannte Form: ' . implode(' ', $unbekannt) : ''),
    count($endungen) > 0 && $unbekannt === []);

// <If "%{REQUEST_URI} =~ m#/\.(?!well-known(/|$))#">
$punktRegel = null;
if (preg_match('/<If "%\{REQUEST_URI\} =~ m#(.+)#">/', $vorlage, $m) === 1) {
    $punktRegel = '#' . $m[1] . '#';
}
pruefe('Kopie: Punktregel gelesen' . ($punktRegel !== null ? " ($punktRegel)" : ''),
    $punktRegel !== null && @preg_match($punktRegel, '') !== false);

// ------------------------------------------------------------------
// A – die Sperrfunktion
// ------------------------------------------------------------------
if (is_file($SPERR)) require_once $SPERR;
$hatFunktion = is_callable('sp_pfad_gesperrt');
pruefe('backend/src/sperrliste.php definiert sp_pfad_gesperrt()', $hatFunktion);
$gesperrt = fn(string $p): bool => $hatFunktion ? sp_pfad_gesperrt($p) : false;

foreach ($endungen as $e) {
    pruefe("Sperrfunktion: $e gesperrt (klein und GROSS)",
        $gesperrt('/probe' . $e) && $gesperrt('/PROBE' . strtoupper($e)));
}
foreach (['/js/app.js', '/index.html', '/vendor/ci-css/ci-tokens.css',
          '/probe.bak.css', '/bak', '/endung-sh'] as $p) {
    pruefe("Sperrfunktion: $p frei", $hatFunktion && !$gesperrt($p));
}

// ------------------------------------------------------------------
// B – die Punktregel, gegen den Ausdruck der Kopie
// ------------------------------------------------------------------
$proben = ['/.htaccess', '/a/.git/config', '/x/.DS_Store', '/..',
           '/.well-known/probe.css', '/.well-known', '/.well-knownx/y',
           '/a/b.css', '/a.b/c.css'];
$ausgaenge = [];
foreach ($proben as $p) {
    $soll = $punktRegel !== null && preg_match($punktRegel, $p) === 1;
    $ausgaenge[$soll ? 'gesperrt' : 'frei'] = true;
    pruefe("Punktregel wie Vorlage: $p " . ($soll ? 'gesperrt' : 'frei'),
        $punktRegel !== null && $hatFunktion && $gesperrt($p) === $soll);
}
// Ein Vergleich prüft vorher seine Operanden: beide Ausgänge kommen vor.
pruefe('Punktregel: die Proben enthalten gesperrte und freie Pfade',
    isset($ausgaenge['gesperrt'], $ausgaenge['frei']));

// ------------------------------------------------------------------
// php -S starten und abfragen
// ------------------------------------------------------------------
function freier_port(): int
{
    $s = stream_socket_server('tcp://127.0.0.1:0', $en, $es);
    if ($s === false) throw new RuntimeException("kein freier Port: $es");
    $name = (string)stream_socket_get_name($s, false);
    fclose($s);
    return (int)substr($name, strrpos($name, ':') + 1);
}
/**
 * Wie im Dienst (~/etc/services.d, gelesen am 03.11.2026): php -S ohne -t,
 * Router mit vollem Pfad, Arbeitsverzeichnis backend/public.
 * @return array{0: resource, 1: int}
 */
function server_starten(string $public): array
{
    $port = freier_port();
    $p = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$public/dev-router.php"],
        [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'],
         2 => ['file', '/dev/null', 'w']], $rohre, $public);
    if (!is_resource($p)) throw new RuntimeException("php -S in $public nicht gestartet");
    for ($i = 0; $i < 50; $i++) {
        $f = @fsockopen('127.0.0.1', $port, $en, $es, 0.2);
        if ($f !== false) { fclose($f); return [$p, $port]; }
        usleep(100000);
    }
    proc_terminate($p);
    throw new RuntimeException("php -S in $public antwortet nicht auf Port $port");
}
/** Rohe Anfrage: die URI geht unverändert hinaus. @return array{0:int,1:string} */
function abrufen(int $port, string $uri): array
{
    $f = fsockopen('127.0.0.1', $port, $en, $es, 5);
    if ($f === false) throw new RuntimeException("Abruf $uri: $es");
    fwrite($f, "GET $uri HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $roh = (string)stream_get_contents($f);
    fclose($f);
    [$kopf, $rumpf] = array_pad(explode("\r\n\r\n", $roh, 2), 2, '');
    preg_match('#^HTTP/\S+ (\d{3})#', $kopf, $m);
    return [(int)($m[1] ?? 0), $rumpf];
}
function baum_loeschen(string $d): void
{
    if (!is_dir($d)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $x) { $x->isDir() ? rmdir((string)$x) : unlink((string)$x); }
    rmdir($d);
}

// ------------------------------------------------------------------
// C – die Wirkung: der Router im Probebaum unter php -S
// ------------------------------------------------------------------
// Der Probebaum hat denselben Aufbau wie das Projekt (backend/public,
// backend/src); Router und Sperrdatei sind byte-gleiche Kopien. index.html
// und api-router.php sind ERFUNDENE Stellvertreter mit Kennung, damit
// sichtbar ist, welcher Zweig geantwortet hat.
$T = sys_get_temp_dir() . '/sp-sperrliste-' . getmypid();
baum_loeschen($T);
mkdir("$T/backend/public", 0700, true);
mkdir("$T/backend/src", 0700, true);
copy($ROUTER, "$T/backend/public/dev-router.php");
if (is_file($SPERR)) copy($SPERR, "$T/backend/src/sperrliste.php");
file_put_contents("$T/backend/public/index.html", 'STARTSEITE-PROBE');
file_put_contents("$T/backend/public/api-router.php",
    "<?php echo 'API-ZWEIG:' . (\$_GET['route'] ?? '');\n");

$dateien = [];          // Pfad unter backend/public => Kennung im Inhalt
$nr = 0;
foreach ($endungen as $e) {
    $dateien['klein' . $e] = 'SPERRPROBE-' . (++$nr);
    $dateien['GROSS' . strtoupper($e)] = 'SPERRPROBE-' . (++$nr);
}
foreach (['.htaccess', 'a/.git/config', 'unter/.versteckt/datei.css',
          '.well-known/probe.css', 'kontrolle.css'] as $p) {
    $dateien[$p] = 'SPERRPROBE-' . (++$nr);
}
foreach ($dateien as $p => $kennung) {
    if (!is_dir(dirname("$T/backend/public/$p"))) mkdir(dirname("$T/backend/public/$p"), 0700, true);
    file_put_contents("$T/backend/public/$p", $kennung);
}

/** 404 und keine Zeichenfolge aus der Datei (REIHENREGELN 6: Inhalt, nicht nur Code). */
$verweigert = function (int $port, string $uri, string $kennung): bool {
    [$code, $rumpf] = abrufen($port, $uri);
    return $code === 404 && !str_contains($rumpf, $kennung);
};
$geliefert = function (int $port, string $uri, string $erwartet): bool {
    [$code, $rumpf] = abrufen($port, $uri);
    return $code === 200 && $rumpf === $erwartet;
};

$server = null;
try {
    [$server, $port] = server_starten("$T/backend/public");
    pruefe('Probebaum: php -S gestartet', true);
    foreach ($endungen as $e) {
        $k = 'klein' . $e;
        $g = 'GROSS' . strtoupper($e);
        pruefe("Router: $e → 404 ohne Inhalt (klein und GROSS)",
            $verweigert($port, "/$k", $dateien[$k]) && $verweigert($port, "/$g", $dateien[$g]));
    }
    foreach (['.htaccess', 'a/.git/config', 'unter/.versteckt/datei.css'] as $p) {
        pruefe("Router: /$p → 404 ohne Inhalt", $verweigert($port, "/$p", $dateien[$p]));
    }
    // Kodiert: Dieser Router dekodiert die URI selbst (urldecode), bevor er
    // entscheidet. Ein kodierter Punkt trifft deshalb dieselbe Sperre.
    pruefe('Router: /%2Ehtaccess (kodierter Punkt) → 404 ohne Inhalt',
        $verweigert($port, '/%2Ehtaccess', $dateien['.htaccess']));
    pruefe('Router: /klein.%62ak (kodierte Endung) → 404 ohne Inhalt',
        $verweigert($port, '/klein.%62ak', $dateien['klein.bak']));
    // Ob die Datei existiert, ändert die Antwort nicht – sonst verriete sie es.
    pruefe('Router: /probe-0000.bak (gibt es nicht) → 404, nicht die Startseite',
        abrufen($port, '/probe-0000.bak')[0] === 404);
    pruefe('Router: /gibt-es-nicht → 200 mit der Startseite (Kontrolle: SPA-Rückfall bleibt)',
        $geliefert($port, '/gibt-es-nicht', 'STARTSEITE-PROBE'));
    pruefe('Router: /.well-known/probe.css → 200 mit Inhalt (Ausnahme)',
        $geliefert($port, '/.well-known/probe.css', $dateien['.well-known/probe.css']));
    pruefe('Router: /kontrolle.css → 200 mit Inhalt (Kontrolle)',
        $geliefert($port, '/kontrolle.css', $dateien['kontrolle.css']));
    // Entscheidung 2 (Lauf 2): Der API-Zweig steht VOR der Sperre. Zwei Routen
    // haben ein freies Segment ([^/]+); ein Wert mit einer Endung der Liste
    // muss den API-Router erreichen.
    pruefe('Router: /api/probe.bak → API-Router, nicht die Sperre',
        $geliefert($port, '/api/probe.bak', 'API-ZWEIG:api/probe.bak'));
    pruefe('Router: /api/rollen/m.old → API-Router, nicht die Sperre',
        $geliefert($port, '/api/rollen/m.old', 'API-ZWEIG:api/rollen/m.old'));
} catch (RuntimeException $e) {
    pruefe('Probebaum: ' . $e->getMessage(), false);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    baum_loeschen($T);
}

// ------------------------------------------------------------------
// D – was nicht verlorengehen darf: der echte Router, die echten Dateien
// ------------------------------------------------------------------
// .php wird von php -S ausgeführt, nicht ausgeliefert – nicht Teil dieser
// Liste. Punktdateien sind gesperrt; die eine verfolgte (.htaccess, der
// Apache-Rückfall, Entscheidung 1) wird eigens gemessen.
$verfolgt = array_values(array_filter(explode("\n", (string)shell_exec(
    'git -C ' . escapeshellarg($WURZEL) . ' ls-files backend/public 2>/dev/null'))));
$auslieferbar = array_values(array_filter($verfolgt,
    fn(string $f): bool => !str_ends_with($f, '.php') && !str_starts_with(basename($f), '.')));
$server = null;
try {
    [$server, $port] = server_starten("$WURZEL/backend/public");
    $falsch = [];
    foreach ($auslieferbar as $f) {
        [$code, $rumpf] = abrufen($port, substr($f, strlen('backend/public')));
        if ($code !== 200 || strlen($rumpf) !== filesize("$WURZEL/$f")) {
            $falsch[] = "$f: $code, " . strlen($rumpf) . ' Bytes';
        }
    }
    pruefe('echter Router: alle ' . count($auslieferbar) . ' auslieferbaren verfolgten Dateien unter'
        . ' backend/public 200, Größe gleich der Datei (null wäre ein Fehler)',
        count($auslieferbar) > 0 && $falsch === []);
    foreach ($falsch as $z) echo "      $z" . PHP_EOL;
    $ht = "$WURZEL/backend/public/.htaccess";
    $htKennung = is_file($ht) ? 'RewriteRule ^api/' : null;
    pruefe('echter Router: /.htaccess (verfolgt, Apache-Rückfall) → 404 ohne Inhalt',
        $htKennung !== null && str_contains((string)file_get_contents($ht), $htKennung)
        && $verweigert($port, '/.htaccess', $htKennung));
} catch (RuntimeException $e) {
    pruefe('echter Router: ' . $e->getMessage(), false);
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
}

// ------------------------------------------------------------------
// E – Aufrufstelle und Reihenfolge (Quelltext; die Wirkung misst C)
// ------------------------------------------------------------------
$code = [];
foreach (token_get_all((string)file_get_contents($ROUTER)) as $t) {
    if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) continue;
    $code[] = is_array($t) ? $t[1] : $t;
}
$aufrufe = [];
$apiZweig = $ersteDatei = null;
foreach ($code as $i => $w) {
    $folgt = $code[$i + 1] ?? '';
    if ($w === 'sp_pfad_gesperrt' && $folgt === '(') $aufrufe[] = $i;
    if ($w === "'/api-router.php'" && $apiZweig === null) $apiZweig = $i;
    if (in_array($w, ['file_exists', 'readfile'], true) && $folgt === '(' && $ersteDatei === null) $ersteDatei = $i;
}
pruefe('dev-router.php ruft sp_pfad_gesperrt() genau einmal (Code, nicht Kommentar/Zeichenkette): '
    . count($aufrufe), count($aufrufe) === 1);
pruefe('dev-router.php: die Sperre steht nach dem API-Zweig und vor dem ersten file_exists()/readfile()'
    . ' (Reihenfolge im Quelltext)',
    count($aufrufe) === 1 && $apiZweig !== null && $ersteDatei !== null
    && $apiZweig < $aufrufe[0] && $aufrufe[0] < $ersteDatei);

exit($fehler === 0 ? 0 : 1);
