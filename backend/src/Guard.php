<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace App;

use PDO;

/**
 * Zugriffsprüfungen für die Prozess-basierte Instanz.
 *
 * Rollenmodell:
 *   App-Ebene:     admin | mitglied  (in benutzer_rollen)
 *   Prozess-Ebene: verantwortlich | mitarbeitend  (in prozess_teilnehmer)
 *
 * Admins dürfen alles. Verantwortliche dürfen ihren Prozess vollständig
 * verwalten. Mitarbeitende dürfen Instanzen bearbeiten (Häkchen, Felder).
 */
final class Guard
{
    /** @return array{webuntis_user: string, anzeigename: string, rolle: string} */
    public static function requireLogin(PDO $db): array
    {
        $session = Session::currentUser();
        if ($session === null) {
            Response::error('Nicht angemeldet.', 401);
        }

        $stmt = $db->prepare('SELECT anzeigename, rolle FROM benutzer_rollen WHERE webuntis_user = :u');
        $stmt->execute([':u' => $session['webuntis_user']]);
        $aktuell = $stmt->fetch();

        if ($aktuell === false) {
            Session::logout();
            Response::error('Nicht angemeldet.', 401);
        }

        return [
            'webuntis_user' => $session['webuntis_user'],
            'anzeigename'   => $aktuell['anzeigename'],
            'rolle'         => $aktuell['rolle'],
        ];
    }

    /** @return array{webuntis_user: string, anzeigename: string, rolle: string} */
    public static function requireAdmin(PDO $db): array
    {
        $user = self::requireLogin($db);
        if ($user['rolle'] !== 'admin') {
            Response::error('Diese Aktion ist nur für Administratoren möglich.', 403);
        }
        return $user;
    }

    /**
     * Prüft ob der eingeloggte Nutzer Zugriff auf einen Prozess hat.
     * Admins haben immer Zugriff.
     * Gibt die Prozess-Rolle zurück ('verantwortlich'|'mitarbeitend'|'admin').
     */
    public static function requireProzessZugriff(PDO $db, int $prozessId): array
    {
        $user = self::requireLogin($db);

        if ($user['rolle'] === 'admin') {
            return array_merge($user, ['prozess_rolle' => 'admin']);
        }

        $stmt = $db->prepare(
            'SELECT rolle FROM prozess_teilnehmer WHERE prozess_id = :p AND webuntis_user = :u'
        );
        $stmt->execute([':p' => $prozessId, ':u' => $user['webuntis_user']]);
        $teilnehmer = $stmt->fetch();

        if (!$teilnehmer) {
            Response::error('Kein Zugriff auf diesen Prozess.', 403);
        }

        return array_merge($user, ['prozess_rolle' => $teilnehmer['rolle']]);
    }

    /**
     * Prüft ob der Nutzer Verantwortlicher oder Admin ist.
     * Verantwortliche dürfen: Teilnehmer verwalten, öffentlich/privat schalten,
     * Phasen/Schritte verwalten.
     */
    public static function requireProzessVerantwortlich(PDO $db, int $prozessId): array
    {
        $user = self::requireProzessZugriff($db, $prozessId);
        if (!in_array($user['prozess_rolle'], ['verantwortlich', 'admin'], true)) {
            Response::error('Diese Aktion erfordert die Rolle "Verantwortlich" für diesen Prozess.', 403);
        }
        return $user;
    }
}
