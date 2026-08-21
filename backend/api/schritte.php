<?php
/*
 * Schulprozesse – prozesse.hornse.de
 * Copyright (C) 2026 Sebastian Horn, Friedrich-Rückert-Gymnasium Düsseldorf
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use App\Guard;
use App\Response;

function handleListSchritte(PDO $db, array $config, array $input): void
{
    $user = Guard::requireLogin($db);

    $prozessId = isset($input['prozess_id']) ? (int) $input['prozess_id'] : null;

    if (!$prozessId) {
        // Fallback: ersten Prozess des Nutzers nehmen (kein aktiv-Flag mehr)
        if ($user['rolle'] === 'admin') {
            $row = $db->query('SELECT id FROM prozesse ORDER BY erstellt_am DESC LIMIT 1')->fetch();
        } else {
            $row = $db->prepare(
                'SELECT p.id FROM prozesse p
                 JOIN prozess_teilnehmer pt ON pt.prozess_id = p.id
                 WHERE pt.webuntis_user = :u
                 ORDER BY p.erstellt_am DESC LIMIT 1'
            );
            $row->execute([':u' => $user['webuntis_user']]);
            $row = $row->fetch();
        }
        $prozessId = $row ? (int) $row['id'] : null;
    }

    if (!$prozessId) {
        Response::json(['prozess_id' => null, 'schritte' => []]);
    }

    // Zugriff prüfen
    Guard::requireProzessZugriff($db, $prozessId);

    $nurAktive = empty($input['alle']); // alle=1 liefert auch deaktivierte (für Verwaltung)

    // Vorlage-basierte Schritte – mit prozessspezifischen Phasen-Überschreibungen.
    // COALESCE(si.instanz_phase_name, ...): ein per Drag-and-drop verschobener
    // Schritt (Migration 006) überschreibt Name UND Farbe der Phase vollständig
    // und hängt sich damit von ip/p ab – siehe docs/ENTSCHEIDUNGEN.md.
    $stmt = $db->prepare(
        'SELECT si.id, si.erledigt, si.verantwortlich_user, si.verantwortlich_anzeigename,
                si.start_datum, si.geplantes_datum, si.erledigt_am, si.erledigt_von,
                si.kommentar, si.kann_parallel, si.deaktiviert,
                si.instanz_titel, si.instanz_reihenfolge,
                COALESCE(si.instanz_phase_name,  ip.instanz_name,  p.name)  AS phase,
                COALESCE(si.instanz_phase_farbe, ip.instanz_farbe, p.farbe) AS phase_farbe,
                CASE WHEN si.instanz_phase_name IS NULL THEN p.reihenfolge END AS phase_reihenfolge,
                CASE WHEN si.instanz_phase_name IS NULL THEN p.id END AS phase_id,
                sv.reihenfolge AS vorlage_reihenfolge, sv.titel AS vorlage_titel,
                sv.beschreibung,
                COALESCE(si.instanz_titel, sv.titel)             AS titel,
                COALESCE(si.instanz_reihenfolge, sv.reihenfolge) AS reihenfolge,
                "vorlage" AS quelle,
                (sv.prozess_id IS NOT NULL) AS nur_dieser_prozess
         FROM schritt_instanzen si
         JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         JOIN phasen p ON p.id = sv.phase_id
         LEFT JOIN instanz_phasen ip
               ON ip.prozess_id = si.prozess_id AND ip.phase_id = p.id
         WHERE si.prozess_id = :pid' .
        ($nurAktive ? ' AND si.deaktiviert = 0' : '')
    );
    $stmt->execute([':pid' => $prozessId]);
    $vorlagenSchritte = $stmt->fetchAll();

    // Prozessspezifische Schritte (ohne Vorlage) – Phase ist hier immer schon
    // ein freies Namens-/Farbfeld, kein COALESCE nötig.
    $stmtEigen = $db->prepare(
        'SELECT id, erledigt,
                verantwortlich_anzeigename, verantwortlich_anzeigename AS verantwortlich_user,
                start_datum, geplantes_datum,
                erledigt_am, erledigt_von,
                kommentar, 0 AS kann_parallel, deaktiviert,
                NULL AS instanz_titel, NULL AS instanz_reihenfolge,
                phase_name AS phase, phase_farbe, NULL AS phase_reihenfolge,
                reihenfolge AS vorlage_reihenfolge, titel AS vorlage_titel,
                beschreibung,
                titel, reihenfolge,
                "eigen" AS quelle
         FROM instanz_schritte
         WHERE prozess_id = :pid' .
        ($nurAktive ? ' AND deaktiviert = 0' : '')
    );
    $stmtEigen->execute([':pid' => $prozessId]);
    $eigenSchritte = $stmtEigen->fetchAll();

    $alle = array_merge($vorlagenSchritte, $eigenSchritte);

    // phase_reihenfolge korrigieren: für Schritte, deren Phase heute per
    // instanz_phase_name/-farbe (oder als "eigen" schon immer) frei ist, statt
    // an eine echte phasen-Zeile gebunden zu sein, gilt vorher weder p.reihenfolge
    // (das wäre die HERKUNFTS-Phase, nicht die aktuelle) noch der alte feste
    // Platzhalter 999 – stattdessen wird nach dem Namen der tatsächlich
    // angezeigten Phase in diesem Prozess gesucht (auch eigene Phasen zählen),
    // erst danach fällt es auf den Platzhalter zurück. Ohne diese Korrektur
    // würde ein Schritt, der per Drag-and-drop in eine bestehende Phase
    // verschoben wurde, dort mit falscher phase_reihenfolge einsortiert und
    // könnte eine zweite Phasenüberschrift auslösen – dieselbe Fehlerklasse
    // wie in tests/gruppierung.test.js.
    $phasenReihenfolge = [];
    foreach ($alle as $s) {
        if ($s['phase_reihenfolge'] !== null && !array_key_exists($s['phase'], $phasenReihenfolge)) {
            $phasenReihenfolge[$s['phase']] = (int) $s['phase_reihenfolge'];
        }
    }
    $naechsterPlatzhalter = (empty($phasenReihenfolge) ? 0 : max($phasenReihenfolge)) + 1000;
    foreach ($alle as &$s) {
        if (array_key_exists($s['phase'], $phasenReihenfolge)) {
            $s['phase_reihenfolge'] = $phasenReihenfolge[$s['phase']];
        } elseif ($s['phase_reihenfolge'] === null) {
            $s['phase_reihenfolge'] = $naechsterPlatzhalter;
        }
    }
    unset($s);

    // Sortierung, die zuvor komplett fehlte (weder ORDER BY in den Abfragen
    // oben, noch beim Zusammenführen): nach Phase, dann nach Reihenfolge
    // innerhalb der Phase, id als letzter, stabiler Tiebreaker. Ohne das blieb
    // instanz_reihenfolge zwar speicherbar, wirkte sich aber in keiner der vier
    // Ansichten sichtbar aus (siehe Bericht Schritt 2).
    usort($alle, function ($a, $b) {
        return $a['phase_reihenfolge'] <=> $b['phase_reihenfolge']
            ?: strcmp((string) $a['phase'], (string) $b['phase'])
            ?: ((int) $a['reihenfolge'] <=> (int) $b['reihenfolge'])
            ?: ((int) $a['id'] <=> (int) $b['id']);
    });

    Response::json(['prozess_id' => $prozessId, 'schritte' => $alle]);
}

/**
 * Speichert die neue Reihenfolge (und ggf. den Phasenwechsel) einer Gruppe
 * von Schritten innerhalb EINER Phase eines Prozesses – wird nach jeder
 * Drag-and-drop- oder Pfeiltasten-Aktion in "Prozesse verwalten" aufgerufen.
 *
 * Bekommt die komplette neue Reihenfolge dieser einen Phase (voller Ersatz,
 * wie schon bei handleReihenfolgeVorlagen/handleReihenfolgePhasen – siehe
 * docs/ENTSCHEIDUNGEN.md zur Begründung "je Phase gezählt").
 *
 * Ändert die Phasen-Zuordnung eines Eintrags NUR, wenn seine aktuelle
 * (aus der DB gelesene, nicht die vom Client behauptete) Phase von ziel_phase
 * abweicht – reines Umsortieren innerhalb der angestammten Phase rührt
 * instanz_phase_name/instanz_phase_farbe (bzw. bei eigenen Schritten
 * phase_name/phase_farbe) also nicht an, ein Schritt bleibt so lange wie
 * möglich an seiner Vorlage-Phase "dran" (folgt z. B. späteren Umbenennungen).
 *
 * Kein Versions-/Zeitstempel-Abgleich für gleichzeitige Bearbeitung –
 * bewusst wie die beiden bestehenden Bulk-Endpunkte, siehe ENTSCHEIDUNGEN.md.
 * Stattdessen ein struktureller Schutz: jeder Eintrag muss existieren und zu
 * diesem Prozess gehören, sonst wird die GESAMTE Anfrage abgelehnt (409) statt
 * teilweise übernommen – damit kann ein Schritt durch diesen Endpunkt nicht
 * verschwinden.
 *
 * POST /api/prozesse/{prozess_id}/schritte/reihenfolge
 * Body: { ziel_phase, ziel_phase_farbe, eintraege: [{id, typ: 'vorlage'|'eigen'}, ...] }
 */
function handleSchritteReihenfolge(PDO $db, array $config, array $input, array $params): void
{
    $prozessId = (int) $params['prozess_id'];
    Guard::requireProzessVerantwortlich($db, $prozessId);

    $zielPhase  = trim((string) ($input['ziel_phase'] ?? ''));
    $zielFarbe  = (string) ($input['ziel_phase_farbe'] ?? '');
    $eintraege  = $input['eintraege'] ?? null;

    if ($zielPhase === '') Response::error('ziel_phase ist erforderlich.', 400);
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $zielFarbe)) {
        Response::error('ziel_phase_farbe muss #RRGGBB sein.', 400);
    }
    if (!is_array($eintraege) || empty($eintraege)) {
        Response::error('eintraege (nicht-leeres Array) ist erforderlich.', 400);
    }

    $gesehen = [];
    foreach ($eintraege as $e) {
        $id  = (int) ($e['id']  ?? 0);
        $typ = (string) ($e['typ'] ?? '');
        if ($id <= 0 || !in_array($typ, ['vorlage', 'eigen'], true)) {
            Response::error('Jeder Eintrag braucht id und typ ("vorlage"|"eigen").', 400);
        }
        $schluessel = $typ . ':' . $id;
        if (isset($gesehen[$schluessel])) {
            Response::error("Schritt $id ($typ) kommt mehrfach vor.", 400);
        }
        $gesehen[$schluessel] = true;
    }

    $vorlageAktuell = $db->prepare(
        'SELECT si.prozess_id,
                COALESCE(si.instanz_phase_name, ip.instanz_name, p.name) AS aktuelle_phase
         FROM schritt_instanzen si
         JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         JOIN phasen p ON p.id = sv.phase_id
         LEFT JOIN instanz_phasen ip
               ON ip.prozess_id = si.prozess_id AND ip.phase_id = p.id
         WHERE si.id = :id'
    );
    $eigenAktuell = $db->prepare(
        'SELECT prozess_id, phase_name AS aktuelle_phase FROM instanz_schritte WHERE id = :id'
    );

    // Erst validieren (jeder Eintrag existiert, gehört zu diesem Prozess) und
    // dabei die jeweils aktuelle Phase merken – erst danach schreiben. So
    // bleibt eine abgelehnte Anfrage ohne offene Transaktion, und kein
    // Eintrag wird teilweise übernommen, bevor der Rest geprüft ist.
    $geplant = [];
    foreach (array_values($eintraege) as $index => $e) {
        $id  = (int) $e['id'];
        $typ = (string) $e['typ'];

        $stmt = $typ === 'vorlage' ? $vorlageAktuell : $eigenAktuell;
        $stmt->execute([':id' => $id]);
        $aktuell = $stmt->fetch();

        if (!$aktuell || (int) $aktuell['prozess_id'] !== $prozessId) {
            Response::error("Schritt $id ($typ) gehört nicht zu diesem Prozess.", 409);
        }

        $geplant[] = [
            'id' => $id, 'typ' => $typ, 'reihenfolge' => $index + 1,
            'phasenwechsel' => $aktuell['aktuelle_phase'] !== $zielPhase,
        ];
    }

    $updateVorlagePosition = $db->prepare(
        'UPDATE schritt_instanzen SET instanz_reihenfolge = :r WHERE id = :id'
    );
    $updateVorlagePhase = $db->prepare(
        'UPDATE schritt_instanzen
            SET instanz_reihenfolge = :r, instanz_phase_name = :name, instanz_phase_farbe = :farbe
          WHERE id = :id'
    );
    $updateEigenPosition = $db->prepare(
        'UPDATE instanz_schritte SET reihenfolge = :r WHERE id = :id'
    );
    $updateEigenPhase = $db->prepare(
        'UPDATE instanz_schritte
            SET reihenfolge = :r, phase_name = :name, phase_farbe = :farbe
          WHERE id = :id'
    );

    $db->beginTransaction();
    try {
        foreach ($geplant as $g) {
            $werte = [':r' => $g['reihenfolge'], ':id' => $g['id']];
            if ($g['phasenwechsel']) {
                $werte[':name'] = $zielPhase;
                $werte[':farbe'] = $zielFarbe;
                ($g['typ'] === 'vorlage' ? $updateVorlagePhase : $updateEigenPhase)->execute($werte);
            } else {
                ($g['typ'] === 'vorlage' ? $updateVorlagePosition : $updateEigenPosition)->execute($werte);
            }
        }
        $db->commit();
    } catch (\Throwable $ex) {
        $db->rollBack();
        throw $ex;
    }

    Response::json(['ok' => true]);
}

function handleUpdateSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    // Prozess-Zugehörigkeit und Zugriff prüfen
    $infoStmt = $db->prepare(
        'SELECT si.prozess_id, sv.titel, si.vorlage_id
         FROM schritt_instanzen si JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
         WHERE si.id = :id'
    );
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch();
    if (!$info) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessZugriff($db, (int) $info['prozess_id']);

    $textfelder = ['verantwortlich_user', 'verantwortlich_anzeigename',
                   'start_datum', 'geplantes_datum', 'kommentar',
                   'instanz_titel', 'instanz_reihenfolge'];
    $sets  = [];
    $werte = [':id' => $id];

    foreach ($textfelder as $feld) {
        if (array_key_exists($feld, $input)) {
            $sets[]          = "$feld = :$feld";
            $werte[":$feld"] = $input[$feld];
        }
    }

    // deaktiviert – nur Verantwortliche und Admins dürfen Schritte deaktivieren
    if (array_key_exists('deaktiviert', $input)) {
        $prozessRolle = Guard::requireProzessZugriff($db, (int) $info['prozess_id']);
        if (in_array($prozessRolle['prozess_rolle'], ['verantwortlich', 'admin'], true)) {
            $sets[]               = 'deaktiviert = :deaktiviert';
            $werte[':deaktiviert'] = $input['deaktiviert'] ? 1 : 0;
        }
    }

    if (array_key_exists('kann_parallel', $input)) {
        $sets[]                  = 'kann_parallel = :kann_parallel';
        $werte[':kann_parallel'] = $input['kann_parallel'] ? 1 : 0;
    }

    if (array_key_exists('erledigt', $input)) {
        $erledigt       = (bool) $input['erledigt'];
        $sets[]         = 'erledigt = :erledigt';
        $werte[':erledigt'] = $erledigt ? 1 : 0;

        if ($erledigt) {
            $sets[] = 'erledigt_am = :erledigt_am';
            $sets[] = 'erledigt_von = :erledigt_von';
            $werte[':erledigt_am']  = (new DateTime())->format(DATE_ATOM);
            $werte[':erledigt_von'] = $user['webuntis_user'];
        } else {
            $sets[] = 'erledigt_am = NULL';
            $sets[] = 'erledigt_von = NULL';
        }
    }

    if (empty($sets)) Response::error('Keine gültigen Felder übergeben.', 400);

    $db->prepare('UPDATE schritt_instanzen SET ' . implode(', ', $sets) . ' WHERE id = :id')
       ->execute($werte);

    // Aktivität protokollieren
    $logStmt = $db->prepare(
        'INSERT INTO aktivitaeten (prozess_id, vorlage_id, schritt_titel, ereignis, wert_neu, benutzer, anzeigename)
         VALUES (:p, :v, :titel, :ereignis, :wert, :benutzer, :name)'
    );

    $ereignisse = [];
    if (array_key_exists('erledigt', $input)) {
        $ereignisse[] = [(bool)$input['erledigt'] ? 'schritt_erledigt' : 'schritt_rueckgaengig', null];
    }
    if (!empty($input['verantwortlich_anzeigename'])) {
        $ereignisse[] = ['verantwortlich_gesetzt', $input['verantwortlich_anzeigename']];
    }
    if (!empty($input['geplantes_datum'])) {
        $ereignisse[] = ['datum_gesetzt', $input['geplantes_datum']];
    }
    if (!empty($input['start_datum'])) {
        $ereignisse[] = ['startdatum_gesetzt', $input['start_datum']];
    }
    if (array_key_exists('kommentar', $input) && $input['kommentar']) {
        $ereignisse[] = ['kommentar_gesetzt', null];
    }

    foreach ($ereignisse as [$ereignis, $wertNeu]) {
        $logStmt->execute([
            ':p'        => $info['prozess_id'],
            ':v'        => $info['vorlage_id'],
            ':titel'    => $info['titel'],
            ':ereignis' => $ereignis,
            ':wert'     => $wertNeu,
            ':benutzer' => $user['webuntis_user'],
            ':name'     => $user['anzeigename'],
        ]);
    }

    Response::json(['ok' => true]);
}

// ============================================================================
// Prozessspezifische Schritte (instanz_schritte)
// Ein Verantwortlicher kann eigene Schritte anlegen die nur in seinem Prozess
// erscheinen – ohne die globale Vorlage zu berühren.
// ============================================================================

function handleListInstanzSchritte(PDO $db, array $config, array $input, array $params): void
{
    $user      = Guard::requireLogin($db);
    // prozess_id kann als URL-Parameter (/api/prozesse/{id}/instanz-schritte)
    // oder als Query-Parameter (?prozess_id=X) kommen
    $prozessId = isset($params['prozess_id']) ? (int) $params['prozess_id']
               : (isset($input['prozess_id'])  ? (int) $input['prozess_id'] : null);
    if (!$prozessId) Response::error('prozess_id erforderlich.', 400);

    Guard::requireProzessZugriff($db, $prozessId);

    $stmt = $db->prepare(
        'SELECT id, phase_name, phase_farbe, reihenfolge, titel, beschreibung,
                erledigt, erledigt_am, erledigt_von, kommentar, deaktiviert
         FROM instanz_schritte
         WHERE prozess_id = :pid AND deaktiviert = 0
         ORDER BY reihenfolge, id'
    );
    $stmt->execute([':pid' => $prozessId]);
    Response::json($stmt->fetchAll());
}

function handleCreateInstanzSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user      = Guard::requireLogin($db);
    $prozessId = (int) $params['prozess_id'];

    // Nur Verantwortliche und Admins dürfen neue prozessspezifische Schritte anlegen
    $zugangsDaten = Guard::requireProzessVerantwortlich($db, $prozessId);

    $titel     = trim((string) ($input['titel'] ?? ''));
    $phaseName = trim((string) ($input['phase_name'] ?? 'Eigene Schritte'));
    $phaseFarbe = $input['phase_farbe'] ?? '#7F8C8D';
    if ($titel === '') Response::error('titel erforderlich.', 400);
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $phaseFarbe)) $phaseFarbe = '#7F8C8D';

    $maxR = (int) $db->query(
        "SELECT COALESCE(MAX(reihenfolge), 0) FROM instanz_schritte
         WHERE prozess_id = $prozessId"
    )->fetchColumn();

    $db->prepare(
        'INSERT INTO instanz_schritte
         (prozess_id, phase_name, phase_farbe, reihenfolge, titel, beschreibung, erstellt_von)
         VALUES (:pid, :pname, :pfarbe, :r, :titel, :beschreibung, :von)'
    )->execute([
        ':pid'         => $prozessId,
        ':pname'       => $phaseName,
        ':pfarbe'      => $phaseFarbe,
        ':r'           => $maxR + 1,
        ':titel'       => $titel,
        ':beschreibung' => $input['beschreibung'] ?? null,
        ':von'         => $user['webuntis_user'],
    ]);

    Response::json(['id' => (int) $db->lastInsertId()], 201);
}

function handleUpdateInstanzSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    $infoStmt = $db->prepare(
        'SELECT prozess_id FROM instanz_schritte WHERE id = :id'
    );
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch();
    if (!$info) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessZugriff($db, (int) $info['prozess_id']);

    $sets = []; $werte = [':id' => $id];
    $felder = ['titel', 'beschreibung', 'phase_name', 'phase_farbe',
               'reihenfolge', 'kommentar',
               'verantwortlich_anzeigename', 'start_datum', 'geplantes_datum'];
    foreach ($felder as $f) {
        if (array_key_exists($f, $input)) {
            $sets[] = "$f = :$f"; $werte[":$f"] = $input[$f];
        }
    }
    if (array_key_exists('erledigt', $input)) {
        $sets[] = 'erledigt = :erledigt';
        $werte[':erledigt'] = $input['erledigt'] ? 1 : 0;
        if ($input['erledigt']) {
            $sets[] = 'erledigt_am = :ea'; $sets[] = 'erledigt_von = :ev';
            $werte[':ea'] = (new DateTime())->format(DATE_ATOM);
            $werte[':ev'] = $user['webuntis_user'];
        } else {
            $sets[] = 'erledigt_am = NULL'; $sets[] = 'erledigt_von = NULL';
        }
    }
    if (array_key_exists('deaktiviert', $input)) {
        $sets[] = 'deaktiviert = :deaktiviert';
        $werte[':deaktiviert'] = $input['deaktiviert'] ? 1 : 0;
    }
    if (empty($sets)) Response::error('Keine Felder übergeben.', 400);

    $db->prepare('UPDATE instanz_schritte SET ' . implode(', ', $sets) .
        ' WHERE id = :id')->execute($werte);
    Response::json(['ok' => true]);
}

function handleDeleteInstanzSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    $infoStmt = $db->prepare('SELECT prozess_id FROM instanz_schritte WHERE id = :id');
    $infoStmt->execute([':id' => $id]);
    $info = $infoStmt->fetch();
    if (!$info) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessVerantwortlich($db, (int) $info['prozess_id']);
    $db->prepare('DELETE FROM instanz_schritte WHERE id = :id')->execute([':id' => $id]);
    Response::json(['ok' => true]);
}

// ============================================================================
// Prozessspezifische Phasen-Anpassungen (instanz_phasen)
// Verantwortliche können Name und Farbe einer Vorlage-Phase für ihren
// Prozess überschreiben ohne die globale phasen-Tabelle zu berühren.
// ============================================================================

function handleUpsertInstanzPhase(PDO $db, array $config, array $input, array $params): void
{
    $user      = Guard::requireLogin($db);
    $prozessId = (int) $params['prozess_id'];
    $phaseId   = (int) $params['phase_id'];

    Guard::requireProzessVerantwortlich($db, $prozessId);

    $sets = []; $werte = [
        ':prozess' => $prozessId,
        ':phase'   => $phaseId,
        ':von'     => $user['webuntis_user'],
    ];

    if (array_key_exists('instanz_name', $input)) {
        $werte[':name']  = $input['instanz_name'] ?: null;
    } else {
        $werte[':name'] = null;
    }
    if (array_key_exists('instanz_farbe', $input)) {
        $farbe = $input['instanz_farbe'];
        if ($farbe && !preg_match('/^#[0-9A-Fa-f]{6}$/', $farbe)) {
            Response::error('Ungültiger Farbwert. Erwartet: #RRGGBB', 400);
        }
        $werte[':farbe'] = $farbe ?: null;
    } else {
        $werte[':farbe'] = null;
    }

    // Bestehende Werte laden um nur geänderte Felder zu überschreiben
    $bestehend = $db->prepare(
        'SELECT instanz_name, instanz_farbe FROM instanz_phasen
         WHERE prozess_id = :p AND phase_id = :ph'
    );
    $bestehend->execute([':p' => $prozessId, ':ph' => $phaseId]);
    $alt = $bestehend->fetch();

    $neuName  = array_key_exists('instanz_name',  $input) ? $werte[':name']  : ($alt['instanz_name']  ?? null);
    $neuFarbe = array_key_exists('instanz_farbe', $input) ? $werte[':farbe'] : ($alt['instanz_farbe'] ?? null);

    $db->prepare(
        'INSERT INTO instanz_phasen
             (prozess_id, phase_id, instanz_name, instanz_farbe, geaendert_am, geaendert_von)
         VALUES (:p, :ph, :name, :farbe, datetime(\'now\'), :von)
         ON CONFLICT(prozess_id, phase_id)
         DO UPDATE SET instanz_name  = :name,
                       instanz_farbe = :farbe,
                       geaendert_am  = datetime(\'now\'),
                       geaendert_von = :von'
    )->execute([
        ':p'    => $prozessId,
        ':ph'   => $phaseId,
        ':name' => $neuName,
        ':farbe' => $neuFarbe,
        ':von'  => $user['webuntis_user'],
    ]);

    Response::json(['ok' => true]);
}

/**
 * Setzt die Anpassungen einer Vorlage-Phase für einen Prozess zurück.
 *
 * umfang = 'phase' (Standard): nur Phasenname und -farbe
 * umfang = 'alles':            zusätzlich Schritt-Umbenennungen dieser Phase
 *                              zurücksetzen und ausgeblendete Schritte wieder
 *                              einblenden. Selbst hinzugefügte Schritte bleiben.
 */
function handleDeleteInstanzPhase(PDO $db, array $config, array $input, array $params): void
{
    $user      = Guard::requireLogin($db);
    $prozessId = (int) $params['prozess_id'];
    $phaseId   = (int) $params['phase_id'];
    $umfang    = ($input['umfang'] ?? 'phase') === 'alles' ? 'alles' : 'phase';

    Guard::requireProzessVerantwortlich($db, $prozessId);

    $db->beginTransaction();
    try {
        // Immer: überschriebenen Phasennamen/-farbe entfernen
        $db->prepare(
            'DELETE FROM instanz_phasen WHERE prozess_id = :p AND phase_id = :ph'
        )->execute([':p' => $prozessId, ':ph' => $phaseId]);

        $zurueckgesetzt = 0;
        if ($umfang === 'alles') {
            // Schritt-Umbenennungen zurücksetzen und Ausgeblendete einblenden –
            // nur für Schritt-Instanzen deren Vorlage zu dieser Phase gehört.
            $stmt = $db->prepare(
                'UPDATE schritt_instanzen
                    SET instanz_titel = NULL,
                        instanz_reihenfolge = NULL,
                        deaktiviert = 0
                  WHERE prozess_id = :p
                    AND vorlage_id IN (SELECT id FROM schritt_vorlagen WHERE phase_id = :ph)'
            );
            $stmt->execute([':p' => $prozessId, ':ph' => $phaseId]);
            $zurueckgesetzt = $stmt->rowCount();
        }

        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    Response::json(['ok' => true, 'umfang' => $umfang, 'schritte' => $zurueckgesetzt]);
}

// ============================================================================
// Schritt duplizieren
// Kopiert einen Vorlage-Schritt oder eigenen Schritt in eine Zielphase.
// Admins können in die Standard-Vorlage duplizieren,
// Verantwortliche in ihre Prozess-Instanz.
// ============================================================================

function handleDuplizierenSchritt(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    $zielPhaseId  = isset($input['ziel_phase_id'])  ? (int) $input['ziel_phase_id']  : null;
    $zielProzessId = isset($input['ziel_prozess_id']) ? (int) $input['ziel_prozess_id'] : null;
    $neuerTitel   = trim((string) ($input['titel'] ?? ''));

    // Quell-Schritt laden
    $stmt = $db->prepare(
        'SELECT sv.titel, sv.beschreibung, sv.kann_parallel, sv.phase_id, sv.reihenfolge
         FROM schritt_vorlagen sv WHERE sv.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $quelle = $stmt->fetch();
    if (!$quelle) Response::error('Schritt nicht gefunden.', 404);

    $titel = $neuerTitel ?: $quelle['titel'] . ' (Kopie)';

    if ($zielProzessId) {
        // In Prozess-Instanz duplizieren (als instanz_schritt)
        Guard::requireProzessVerantwortlich($db, $zielProzessId);

        // Phasenname der Zielphase ermitteln
        $phaseStmt = $db->prepare('SELECT name, farbe FROM phasen WHERE id = :id');
        $phaseStmt->execute([':id' => $zielPhaseId ?? $quelle['phase_id']]);
        $phase = $phaseStmt->fetch();

        $maxR = (int) $db->query(
            "SELECT COALESCE(MAX(reihenfolge), 0) FROM instanz_schritte WHERE prozess_id = $zielProzessId"
        )->fetchColumn();

        $db->prepare(
            'INSERT INTO instanz_schritte
             (prozess_id, phase_name, phase_farbe, reihenfolge, titel, beschreibung, erstellt_von)
             VALUES (:pid, :pname, :pfarbe, :r, :titel, :beschreibung, :von)'
        )->execute([
            ':pid'          => $zielProzessId,
            ':pname'        => $phase['name'] ?? 'Eigene Schritte',
            ':pfarbe'       => $phase['farbe'] ?? '#7F8C8D',
            ':r'            => $maxR + 1,
            ':titel'        => $titel,
            ':beschreibung' => $quelle['beschreibung'],
            ':von'          => $user['webuntis_user'],
        ]);
        Response::json(['id' => (int) $db->lastInsertId(), 'typ' => 'instanz'], 201);

    } else {
        // In Standard-Vorlage duplizieren (als schritt_vorlage)
        Guard::requireAdmin($db);
        $phaseId = $zielPhaseId ?? $quelle['phase_id'];

        $maxR = (int) $db->query(
            "SELECT COALESCE(MAX(reihenfolge), 0) FROM schritt_vorlagen WHERE phase_id = $phaseId"
        )->fetchColumn();

        $db->prepare(
            'INSERT INTO schritt_vorlagen (phase_id, reihenfolge, titel, beschreibung, kann_parallel)
             VALUES (:phase_id, :r, :titel, :beschreibung, :kp)'
        )->execute([
            ':phase_id'     => $phaseId,
            ':r'            => $maxR + 1,
            ':titel'        => $titel,
            ':beschreibung' => $quelle['beschreibung'],
            ':kp'           => $quelle['kann_parallel'],
        ]);
        $neueVorlageId = (int) $db->lastInsertId();

        // Instanz für alle Prozesse anlegen
        $prozesse = $db->query('SELECT id FROM prozesse WHERE aktiv = 1')->fetchAll();
        $ins = $db->prepare(
            'INSERT INTO schritt_instanzen (prozess_id, vorlage_id, kann_parallel) VALUES (:p, :v, :kp)'
        );
        foreach ($prozesse as $p) {
            $ins->execute([':p' => $p['id'], ':v' => $neueVorlageId, ':kp' => $quelle['kann_parallel']]);
        }
        Response::json(['id' => $neueVorlageId, 'typ' => 'vorlage'], 201);
    }
}

/**
 * Löscht einen Vorlage-Schritt der ausdrücklich für einen einzelnen Prozess
 * angelegt wurde (über "+ Weiterer Schritt" unter "Prozess verwalten").
 * Erkennbar an schritt_vorlagen.prozess_id (siehe Migration 005).
 *
 * Schutz: Schritte aus der Standard-Vorlage oder aus einem Snapshot
 * (prozess_id IS NULL) werden abgelehnt – die können in der Prozessansicht
 * nur ausgeblendet werden.
 *
 * DELETE /api/schritte/{id}
 */
function handleDeleteSchrittInstanz(PDO $db, array $config, array $input, array $params): void
{
    $user = Guard::requireLogin($db);
    $id   = (int) $params['id'];

    $stmt = $db->prepare(
        'SELECT si.prozess_id, si.vorlage_id, sv.titel, sv.prozess_id AS vorlage_prozess_id
           FROM schritt_instanzen si
           JOIN schritt_vorlagen sv ON sv.id = si.vorlage_id
          WHERE si.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $schritt = $stmt->fetch();
    if (!$schritt) Response::error('Schritt nicht gefunden.', 404);

    Guard::requireProzessVerantwortlich($db, (int) $schritt['prozess_id']);

    // Nur Schritte die ausdrücklich für diesen Prozess angelegt wurden.
    // Alles andere gehört zur Standard-Vorlage oder einem Snapshot und darf
    // hier nicht verschwinden – dort ist nur Ausblenden vorgesehen.
    if ($schritt['vorlage_prozess_id'] === null
        || (int) $schritt['vorlage_prozess_id'] !== (int) $schritt['prozess_id']) {
        Response::error(
            'Dieser Schritt stammt aus der Vorlage und kann hier nur ausgeblendet '
            . 'werden. Löschen ist nur in der Vorlagenverwaltung möglich.',
            409
        );
    }

    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM schritt_instanzen WHERE id = :id')
           ->execute([':id' => $id]);
        $db->prepare('DELETE FROM schritt_vorlagen WHERE id = :vid')
           ->execute([':vid' => $schritt['vorlage_id']]);
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    Response::json(['ok' => true, 'titel' => $schritt['titel']]);
}
