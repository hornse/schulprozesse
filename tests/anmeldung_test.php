<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * tests/anmeldung_test.php – die Antworten der Anmeldung (S74, koordination
 * AUFTRAG-doku-hilfe-S74.md, A2 und A3)
 *
 * handleLogin wird AUSGEFÜHRT, gegen SQLite mit login_log und benutzer_rollen
 * aus migrations/001_init.sql. Response und Session sind ersetzt wie in
 * tests/anmeldebremse_test.php.
 *
 * WebUntis ist eine ATTRAPPE: Die echte Klasse verlangt https://, ein
 * Ersatz-Server über php -S kann das nicht. Die Attrappe antwortet je
 * Benutzername so, wie WebUntisAuth es tut (null plus grund(), oder das
 * Ergebnis); dass es diese Gründe in der echten Klasse gibt, prüft die
 * Voraussetzung. Den Weg „WebUntis antwortet nicht“ mit der ECHTEN Klasse
 * prüft tests/anmeldebremse_test.php.
 *
 * FALLSTRICKE 8: Solange ein Passwort entschied, ist jede Ablehnung derselbe
 * Satz mit 401 – falsches Passwort, falscher Kontotyp, keine Freigabe, leere
 * Eingabe. Nur 429 (warten) und 503 (Ausfall) unterscheiden sich.
 *
 * Aufruf: php tests/anmeldung_test.php   → Exit-Code 0 = alles grün
 */

declare(strict_types=1);

namespace App\Auth {
    /** Attrappe. Benutzer „ho“, „eltern“, „frei“; Passwort „richtig“; „tot“ = keine Antwort. */
    final class WebUntisAuth
    {
        private ?string $grund = null;
        public function __construct(array $config, \PDO $db) {}
        public function grund(): ?string { return $this->grund; }
        public function authenticate(string $u, string $p): ?array
        {
            $this->grund = null;
            if ($u === 'tot' || \str_starts_with($u, 'tot')) { $this->grund = 'ohne_entscheid'; return null; }
            if ($p !== 'richtig') { $this->grund = 'falsches_passwort'; return null; }
            if ($u === 'eltern') { $this->grund = 'falsche_rolle'; return null; }
            return ['username' => $u, 'personType' => 2, 'personId' => 7];
        }
    }
}

namespace App {
    final class Response
    {
        public static function json(mixed $d, int $s = 200): never { throw new \Antwort($s, $d); }
        public static function error(string $m, int $s = 400): never { throw new \Antwort($s, ['error' => $m]); }
    }
    final class Session { public static ?array $wer = null; public static function login(array $u): void { self::$wer = $u; } }
}

namespace {
    final class Antwort extends Exception
    {
        public function __construct(public int $status, public mixed $daten) { parent::__construct('antwort'); }
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

    // header() (Retry-After) warnt ohne Webserver; die Warnung gehört nicht
    // zwischen die Prüfzeilen.
    ini_set('display_errors', 'stderr');

    $W = dirname(__DIR__);
    require_once "$W/backend/src/Auth/Anmeldebremse.php";
    require_once "$W/backend/api/auth.php";
    use App\Auth\Anmeldebremse;

    // Der Wortlaut der Reihe (lernzeiten, kurse, signage, projektstunden,
    // nachgelesen am 03.11.2026) und der heutige Ablehnungssatz.
    const ABGELEHNT = 'Anmeldung nicht möglich. Bitte Zugangsdaten prüfen.';
    const AUSFALL   = 'WebUntis ist gerade nicht erreichbar. Bitte in ein paar Minuten erneut versuchen.';
    $schwelle = defined(Anmeldebremse::class . '::SCHWELLE') ? Anmeldebremse::SCHWELLE : 5;

    $ddl = (string)file_get_contents("$W/migrations/001_init.sql");
    $tab = fn(string $n): string => preg_match('/CREATE TABLE IF NOT EXISTS ' . $n . ' \(.*?\);/s', $ddl, $m) === 1 ? $m[0] : '';
    $echt = (string)file_get_contents("$W/backend/src/Auth/WebUntisAuth.php");
    $gruende = ['ohne_entscheid', 'falsches_passwort', 'falsche_rolle'];
    pruefe('Voraussetzung: Tabellen gelesen, und die Gründe der Attrappe gibt es in WebUntisAuth (' . implode(', ', $gruende) . ')',
        $tab('login_log') !== '' && $tab('benutzer_rollen') !== ''
        && count(array_filter($gruende, fn($g) => str_contains($echt, "\$this->grund = '$g'"))) === count($gruende));

    function db(): PDO
    {
        global $tab;
        $p = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $p->exec($tab('login_log'));
        $p->exec($tab('benutzer_rollen'));
        $p->exec("INSERT INTO sqlite_sequence (name, seq) VALUES ('login_log', 4710)");
        // „ho“ ist freigegeben; „frei“ und „eltern“ nicht.
        $p->exec("INSERT INTO benutzer_rollen (webuntis_user, anzeigename, rolle) VALUES ('ho', 'Probe', 'mitglied')");
        return $p;
    }
    // Die Konfiguration sagt absichtlich 5: Gilt die Konstante, wirkt das nicht.
    $config = ['webuntis' => ['base_url' => 'https://127.0.0.1:9', 'school' => 'probe', 'client' => 'probe',
                              'allowed_person_types' => [2, 16]],
               'security' => ['max_failed_logins' => 5, 'lockout_minutes' => 15]];
    function login(PDO $p, string $wer, string $pw): array
    {
        global $config;
        try { handleLogin($p, $config, ['username' => $wer, 'password' => $pw]); return ['status' => -1, 'daten' => null]; }
        catch (Antwort $a) { return ['status' => $a->status, 'daten' => $a->daten]; }
    }
    $zaehlt = fn(PDO $p): int => (int)$p->query('SELECT COUNT(*) FROM login_log WHERE erfolgreich = 0')->fetchColumn();

    echo PHP_EOL . 'Ablehnung: ein Satz, ein Status (FALLSTRICKE 8)' . PHP_EOL;
    $p = db(); $falsch = login($p, 'ho', 'falsch');
    pruefe('falsches Passwort: 401 „' . ABGELEHNT . '“, und der Versuch zählt',
        $falsch['status'] === 401 && ($falsch['daten']['error'] ?? null) === ABGELEHNT && $zaehlt($p) === 1);
    $p = db(); $frei = login($p, 'frei', 'richtig');
    pruefe('Passwort richtig, nicht freigegeben: dieselbe Antwort wie ein falsches Passwort, und der Versuch zählt',
        $frei === $falsch && $zaehlt($p) === 1);
    $p = db(); $eltern = login($p, 'eltern', 'richtig');
    pruefe('Passwort richtig, Kontotyp nicht zugelassen (Eltern): dieselbe Antwort wie ein falsches Passwort',
        $eltern === $falsch);
    $p = db(); $leer = login($p, 'ho', '');
    pruefe('leere Eingabe: dieselbe Antwort wie ein falsches Passwort', $leer === $falsch);

    echo PHP_EOL . 'Die Sätze stehen an einer Stelle' . PHP_EOL;
    $literale = [];
    foreach (PhpToken::tokenize((string)file_get_contents("$W/backend/api/auth.php")) as $t) {
        if ($t->is(T_CONSTANT_ENCAPSED_STRING) && preg_match('/Zugangsdaten|nicht erreichbar/u', $t->text)) $literale[] = $t->text;
    }
    pruefe('Ablehnungs- und Ausfallsatz sind Konstanten in Anmeldebremse, mit dem Wortlaut der Reihe; in auth.php steht keiner als Zeichenkette'
        . ($literale ? ' (' . count($literale) . ' gefunden)' : ''),
        defined(Anmeldebremse::class . '::SATZ_ABGELEHNT') && defined(Anmeldebremse::class . '::SATZ_AUSFALL')
        && constant(Anmeldebremse::class . '::SATZ_ABGELEHNT') === ABGELEHNT
        && constant(Anmeldebremse::class . '::SATZ_AUSFALL') === AUSFALL && $literale === []);

    echo PHP_EOL . 'Die Schwelle: acht, als Konstante (M56)' . PHP_EOL;
    pruefe('Anmeldebremse::SCHWELLE ist 8', defined(Anmeldebremse::class . '::SCHWELLE') && Anmeldebremse::SCHWELLE === 8);
    $p = db();
    $status = [];
    for ($i = 1; $i <= 9; $i++) $status[$i] = login($p, 'ho', 'falsch-' . $i)['status'];
    pruefe('Konfiguration sagt max_failed_logins 5: der sechste bis achte Versuch werden noch geprüft (401), der neunte wartet (429)',
        $status[6] === 401 && $status[8] === 401 && $status[9] === 429);
    $vorlage = [];
    foreach (PhpToken::tokenize((string)file_get_contents("$W/config/config.example.php")) as $t) {
        if ($t->is(T_CONSTANT_ENCAPSED_STRING) && trim($t->text, "'\"") === 'max_failed_logins') $vorlage[] = $t;
    }
    pruefe('config.example.php führt keinen Schlüssel max_failed_logins mehr (Kommentare zählen nicht)', $vorlage === []);

    echo PHP_EOL . ($fehler === 0 ? 'ALLE TESTS GRÜN' : "$fehler ROT") . PHP_EOL;
    exit($fehler === 0 ? 0 : 1);
}
