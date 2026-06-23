<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use App\Response;

/**
 * GET /api/dashboard
 * Liefert alle öffentlichen Prozesse mit ihren Schritten.
 * Kein Login erforderlich – kein verantwortlich, kein Kommentar.
 */
function handleDashboard(PDO $db): void
{
    $prozesse = $db->query(
        'SELECT id, label, beschreibung FROM prozesse WHERE oeffentlich = 1 ORDER BY aktiv DESC, erstellt_am DESC'
    )->fetchAll();

    $ergebnis = [];
    $stmt = $db->prepare(
        'SELECT si.erledigt, si.start_datum, si.geplantes_datum, si.kann_parallel,
                p.name AS phase, p.farbe AS phase_farbe, p.reihenfolge AS phase_reihenfolge,
                sv.reihenfolge, sv.titel
         FROM schritt_instanzen si
         JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         JOIN phasen p ON p.id = sv.phase_id
         WHERE si.prozess_id = :pid
         ORDER BY p.reihenfolge, sv.reihenfolge'
    );

    foreach ($prozesse as $prozess) {
        $stmt->execute([':pid' => $prozess['id']]);
        $ergebnis[] = [
            'id'          => $prozess['id'],
            'label'       => $prozess['label'],
            'beschreibung' => $prozess['beschreibung'],
            'schritte'    => $stmt->fetchAll(),
        ];
    }

    Response::json($ergebnis);
}
