<?php
/**
 * Schulprozesse – Sperrliste für den Router
 *
 * Der Dienst läuft hinter php -S; die Apache-Sperrliste
 * (koordination docs/betrieb/apache/webroot.htaccess) erreicht ihn nicht.
 * Ohne diese Datei reichte der Router jede vorhandene Datei unter
 * backend/public an php -S zurück – gemessen am 03.11.2026: /.htaccess 200,
 * 826 Bytes (S62, koordination).
 *
 * Dieselbe Bedeutung wie die Vorlage. Die Liste steht damit zweimal da;
 * tests/sperrliste_test.php misst diese Fassung gegen die beigelegte Kopie
 * tests/daten/webroot.htaccess, je Endung, klein und groß.
 *
 * Liegt unter backend/src, nicht unter backend/public: Dort würde php -S
 * eine .php-Datei auf Anfrage ausführen (Lauf 2, Entscheidung 3).
 *
 * Gesperrt heißt im Router 404, nicht 403: Er verrät nicht, dass es die
 * Datei gibt.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Ist der angefragte Pfad gesperrt?
 *
 * $pfad ist der Pfadteil der URI, wie der Router ihn entscheidet
 * (dekodiert, "/a/b.css"). Gesperrt ist
 *   - jeder Pfad mit einem Teil, der mit einem Punkt beginnt, außer
 *     .well-known (auch "..");
 *   - jeder Basisname, der – ohne Rücksicht auf Groß- und Kleinschreibung –
 *     auf eine Endung der Liste oder auf "~" endet.
 */
function sp_pfad_gesperrt(string $pfad): bool
{
    if (preg_match('#(^|/)\.(?!well-known(/|$))#', $pfad) === 1) {
        return true;
    }
    $name = basename($pfad);
    return preg_match(
        '/(\.(save(\.[0-9]+)?|swp|swo|orig|old|bak|tmp|sql|sqlite|db|log|env|ini|dist|inc|md|sh)|~)$/i',
        $name
    ) === 1;
}
