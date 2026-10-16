<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace App\Auth;

use DateTimeImmutable;
use Exception;
use PDO;

/**
 * Die Anmeldebremse – Wartezeit statt Sperre.
 *
 * Bauart der Reihe (Auftrag koordination 16.10.2026; Vorbild lernzeiten
 * E406, sprechtag E35/E37). Bis hier sperrte WebUntisAuth nach
 * `security.max_failed_logins` Fehlversuchen `security.lockout_minutes`
 * Minuten lang – auch den Inhaber mit dem richtigen Passwort, und jeder
 * abgewiesene Versuch verlängerte die Sperre. Das lokale Notfallpasswort
 * wurde vor der Bremse geprüft und war gar nicht gebremst.
 *
 * Jetzt, an EINER Stelle für jeden Weg, der ein Passwort annimmt:
 *   - zuerst den Versuch eintragen, dann zählen, dann erst prüfen – so
 *     sehen gleichzeitige Anfragen einander;
 *   - ab der Schwelle eine Wartezeit von 60 Sekunden nach dem letzten
 *     Fehlversuch, ohne Staffel, nie eine Sperre;
 *   - abgewiesene Versuche zählen nicht, ihre Zeile wird wieder entfernt;
 *   - eine gelungene Anmeldung setzt zurück: gezählt wird ab ihrer
 *     Kennung, nicht ab ihrer Uhrzeit;
 *   - scheitert die Zählung, wird abgewiesen, mit eigenem Satz (503);
 *   - ein Versuch, bei dem WebUntis nicht antwortete, zählt nicht.
 *
 * `security.lockout_minutes` heißt weiter so, weil config.php nicht
 * versioniert ist und auf dem Server so steht; es ist jetzt das ZÄHLFENSTER.
 *
 * BEKANNTE GRENZE: Ein Skript, das einmal pro Minute fehlschlägt, hält ein
 * Konto in der Wartezeit – mühsamer als die Sperre, nicht ausgeschlossen.
 */
final class Anmeldebremse
{
    public const WARTEN_SEKUNDEN = 60;
    /** Grund eines Versuchs, der gerade läuft. */
    public const LAEUFT = '(läuft)';

    /**
     * Das Urteil. `$lage` = ['fehlversuche' => n, 'seit' => Sekunden seit dem
     * jüngsten gezählten Versuch]; `null` heißt: die Zählung scheiterte.
     *
     * @return array{durch:bool,status:int,meldung:string}
     */
    public static function urteil(?array $lage, int $schwelle): array
    {
        if ($lage === null) {
            return ['durch' => false, 'status' => 503,
                    'meldung' => 'Die Anmeldung ist gerade nicht möglich – die Prüfung der '
                               . 'Anmeldeversuche antwortet nicht. Bitte in ein paar Minuten noch einmal.'];
        }
        $seit = $lage['seit'];
        if ($lage['fehlversuche'] >= $schwelle && $seit !== null && $seit < self::WARTEN_SEKUNDEN) {
            $rest = self::WARTEN_SEKUNDEN - max(0, $seit);
            return ['durch' => false, 'status' => 429,
                    'meldung' => 'Zu viele Fehlversuche. Erneut versuchen in noch ' . $rest
                               . ($rest === 1 ? ' Sekunde.' : ' Sekunden.')];
        }
        return ['durch' => true, 'status' => 200, 'meldung' => ''];
    }

    /**
     * Ein Anmeldeversuch beginnt. Die Zeit rechnet PHP aus den Zeitstempeln
     * der Datenbank. Abgewiesen: die eigene Zeile wird wieder entfernt.
     *
     * @return array{id:int,urteil:array{durch:bool,status:int,meldung:string}}
     */
    public static function beginnen(PDO $db, string $benutzer, string $ip, int $schwelle, int $fensterMinuten): array
    {
        $id = 0;
        // NUR BETRIEBSFEHLER (FALLSTRICKE 3): Ein Programmierfehler endet als 500.
        try {
            $db->prepare('INSERT INTO login_log (webuntis_user, erfolgreich, grund, ip) VALUES (?, 0, ?, ?)')
               ->execute([$benutzer, self::LAEUFT, $ip]);
            $id = (int) $db->lastInsertId();
            $st = $db->prepare('SELECT zeitpunkt FROM login_log WHERE id = ?');
            $st->execute([$id]);
            $jetzt = new DateTimeImmutable((string) $st->fetchColumn());
            $ab = $jetzt->modify('-' . max(1, $fensterMinuten) . ' minutes')->format('Y-m-d H:i:s');
            $st = $db->prepare('SELECT COALESCE(MAX(id), 0) FROM login_log WHERE webuntis_user = ? AND erfolgreich = 1');
            $st->execute([$benutzer]);
            $zurueck = (int) $st->fetchColumn();
            $st = $db->prepare('SELECT COUNT(*) AS n, MAX(zeitpunkt) AS letzter FROM login_log
                                 WHERE webuntis_user = ? AND erfolgreich = 0 AND id <> ? AND id > ? AND zeitpunkt >= ?');
            $st->execute([$benutzer, $id, $zurueck, $ab]);
            $z = $st->fetch(PDO::FETCH_ASSOC) ?: ['n' => 0, 'letzter' => null];
            $seit = $z['letzter'] === null ? null
                  : $jetzt->getTimestamp() - (new DateTimeImmutable((string) $z['letzter']))->getTimestamp();
            $lage = ['fehlversuche' => (int) $z['n'], 'seit' => $seit];
        } catch (Exception $e) {
            // Der Grund ins Server-Log, ohne Benutzernamen.
            error_log('schulprozesse: Bremse – Zählung gescheitert, Anmeldung abgewiesen ('
                . get_class($e) . ' ' . $e->getCode() . ')');
            $lage = null;
        }
        $urteil = self::urteil($lage, $schwelle);
        if (!$urteil['durch'] && $id > 0) {
            try {
                $db->prepare('DELETE FROM login_log WHERE id = ?')->execute([$id]);
            } catch (Exception $e) {
                error_log('schulprozesse: Bremse – abgewiesener Versuch nicht entfernt (' . get_class($e) . ')');
            }
        }
        return ['id' => $id, 'urteil' => $urteil];
    }

    /** Der Versuch endet: Erfolg (setzt zurück) oder Fehlschlag mit Grund, in derselben Zeile. */
    public static function abschliessen(PDO $db, int $id, bool $erfolg, ?string $grund): void
    {
        $db->prepare('UPDATE login_log SET erfolgreich = ?, grund = ? WHERE id = ?')
           ->execute([$erfolg ? 1 : 0, $grund, $id]);
    }

    /** Der Versuch endet ohne Entscheid (WebUntis antwortete nicht): Er zählt nicht. */
    public static function ohneEntscheid(PDO $db, int $id): void
    {
        $db->prepare('DELETE FROM login_log WHERE id = ? AND grund = ?')->execute([$id, self::LAEUFT]);
    }
}
