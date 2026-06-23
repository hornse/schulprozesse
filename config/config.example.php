<?php
/**
 * Beispiel-Konfiguration.
 *
 * Kopiere diese Datei nach config.php und trage die echten Werte ein.
 * config.php ist in .gitignore eingetragen und wird NIE eingecheckt -
 * sie enthält zwar kein Passwort (das speichern wir nie), aber den
 * WebUntis-Servernamen und die Schulkennung, die nicht öffentlich im
 * Repository stehen müssen.
 */

return [

    // Zugangsdaten / Endpunkt für die WebUntis-JSON-RPC-Authentifizierung.
    // Dieselbe Schnittstelle, die auch das MRBS-Auth-Modul der Schule nutzt.
    'webuntis' => [
        'base_url' => 'https://SERVER.webuntis.com',   // ohne Slash am Ende
        'school'   => 'SCHULNAME',                      // wie in der WebUntis-URL
        // Eigener Client-Name, damit sich Anfragen dieser App von denen aus
        // MRBS unterscheiden lassen (rein informativ, kein Geheimnis).
        'client'   => 'SchuljahreswechselApp',
        // Nur diese personType-Werte dürfen sich in dieser App anmelden.
        // 2 = Lehrkraft. Weitere Typen je nach Schule/Konfiguration möglich,
        // z. B. 16 für WebUntis-Administratoren.
        //
        // Eigenen personType herausfinden (auf dem Server ausführen):
        //   php -r "\$c=require 'config/config.php';\$u=\$c['webuntis']['base_url'].
        //   '/WebUntis/jsonrpc.do?school='.\$c['webuntis']['school'];
        //   \$p=json_encode(['id'=>1,'method'=>'authenticate','params'=>
        //   ['user'=>'KUERZEL','password'=>'PASSWORT','client'=>'test'],
        //   'jsonrpc'=>'2.0']);\$ctx=stream_context_create(['http'=>['method'=>
        //   'POST','header'=>'Content-Type: application/json','content'=>\$p]]);
        //   \$r=json_decode(file_get_contents(\$u,false,\$ctx),true);
        //   echo \$r['result']['personType']??'n/a';"
        //
        // Beispiel für Lehrkräfte + WebUntis-Admins: [2, 16]
        'allowed_person_types' => [2],
        // Timeouts in Sekunden, damit ein langsames/ausgefallenes WebUntis
        // nicht den PHP-Prozess blockiert.
        'connect_timeout' => 5,
        'timeout'         => 10,
    ],

    // SQLite-Datenbankdatei. Liegt außerhalb von public/, damit sie über
    // den Webserver nicht direkt heruntergeladen werden kann.
    'db' => [
        'sqlite_path' => __DIR__ . '/../data/app.sqlite',
    ],

    'session' => [
        'name'            => 'swj_session',
        'lifetime_minutes' => 480, // 8 Stunden
    ],

    // Einfache Brute-Force-Bremse: ab dieser Anzahl Fehlversuche innerhalb
    // von 15 Minuten wird ein Login für denselben Benutzernamen kurz gesperrt,
    // BEVOR überhaupt eine Anfrage an WebUntis geschickt wird.
    'security' => [
        'max_failed_logins' => 5,
        'lockout_minutes'   => 15,
    ],

];
