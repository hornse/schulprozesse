<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * tests/handbuch_test.php – docs/BENUTZERHANDBUCH.md zitiert die Anwendung
 * (S74, koordination AUFTRAG-doku-hilfe-S74.md, C)
 *
 * Die Sätze und die Schwelle kommen aus backend/src/Auth/Anmeldebremse.php.
 * Ändert sich einer der beiden – Code oder Handbuch –, wird es hier rot.
 * HTML-Kommentare zählen nicht (REIHENREGELN 2: Beschreibung und Sache
 * trennen); Zeilenumbrüche des Markdown werden zu Leerzeichen.
 *
 * Aufruf: php tests/handbuch_test.php   → Exit-Code 0 = alles grün
 */

declare(strict_types=1);

$fehler = 0;
function pruefe(string $name, bool $ok, string $warum = ''): void
{
    global $fehler;
    echo ($ok ? '  ✓ ' : '  ✗ ') . $name . ($ok || $warum === '' ? '' : ' – ' . $warum) . PHP_EOL;
    if (!$ok) $fehler++;
}

$W = dirname(__DIR__);
require_once "$W/backend/src/Auth/Anmeldebremse.php";
$k = App\Auth\Anmeldebremse::class;
$c = fn(string $n) => defined("$k::$n") ? constant("$k::$n") : null;
$schwelle = $c('SCHWELLE');
$warten = preg_replace('/\d+/', '…', App\Auth\Anmeldebremse::urteil(['fehlversuche' => $schwelle ?? 5, 'seit' => 0], $schwelle ?? 5)['meldung'], 1);

$roh = (string)@file_get_contents("$W/docs/BENUTZERHANDBUCH.md");
$text = preg_replace('/\s+/u', ' ', preg_replace('/<!--.*?-->/s', '', $roh));
pruefe('Voraussetzung: Handbuch gelesen, ohne Kommentare, mit Abschnitt zur Hilfe-Seite',
    strlen($text) > 2000 && str_contains($text, '## Hilfe-Seite'));

$zitiert = fn(?string $s): bool => $s !== null && str_contains($text, '„' . $s . '“');
pruefe('Handbuch zitiert den Ablehnungssatz der Anwendung wörtlich', $zitiert($c('SATZ_ABGELEHNT')));
pruefe('Handbuch zitiert den Ausfallsatz der Anwendung wörtlich', $zitiert($c('SATZ_AUSFALL')));
pruefe("Handbuch zitiert den Wartesatz der Bremse („{$warten}“)", $zitiert($warten));

$woerter = ['null', 'einem', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun', 'zehn'];
$wort = $woerter[$schwelle ?? 5];
$alle = preg_match_all('/Nach (null|einem|zwei|drei|vier|fünf|sechs|sieben|acht|neun|zehn|\d+) Fehlversuchen/u', $text, $m);
pruefe("Handbuch nennt die Schwelle der Bremse („Nach {$wort} Fehlversuchen“) und keine andere",
    $schwelle !== null && $alle >= 1 && array_unique($m[1]) === [$wort],
    $alle ? 'gefunden: ' . implode(', ', array_unique($m[1])) : 'keine Angabe');

$verboten = preg_match('/Passwort (stimmte|war richtig)|Kontotyp|nicht freigeschaltet|Datensatz|nicht zugelassen/iu', $text, $v);
pruefe('Handbuch: kein Satz, den es nach FALLSTRICKE 8 nicht geben darf', $verboten === 0, $v[0] ?? '');

// Die Hilfe-Seite wird nicht gezählt: Zahlen der Reiter, Karten und Fragen
// veralteten still („zwei Bereiche … 10 Fragen“ bei drei Reitern und elf
// Fragen, Befund 03.11.2026).
$zaehl = preg_match_all('/\b(\d+|zwei|drei|vier|fünf|sechs|sieben|acht|neun|zehn|elf|zwölf)\s+(?:\p{L}+\s+)?(Bereiche|Reiter|Karten|Fragen|Tabs|Kapitel)\b/iu', $text, $z);
pruefe('Handbuch zählt Reiter, Karten und Fragen der Hilfe nicht', $zaehl === 0, implode(', ', $z[0]));

echo PHP_EOL . ($fehler === 0 ? 'ALLE TESTS GRÜN' : "$fehler ROT") . PHP_EOL;
exit($fehler === 0 ? 0 : 1);
