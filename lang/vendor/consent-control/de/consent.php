<?php

// App override of the consent-control German strings. The only consent gate on
// the Pernes frontend is the Google Map, so the generic "Inhalte laden" button
// reads as "Karte laden".

return [

    'banner' => [
        'title' => 'Cookies & Dienste',
        'description' => 'Diese Webseite nutzt Cookies und externe Dienste.',
        'settings_button' => 'Weitere Informationen',
        'reset_button' => 'Alle Cookies löschen',
        'reset_message' => 'Alle Cookies wurden erfolgreich gelöscht.',
        'close_button' => 'Schließen',
        'ok_button' => 'OK',
        'all_button' => 'Alle erlauben',
    ],

    'message' => [
        'text' => 'Diese Inhalte werden extern geladen von :source.<br />Durch das Aktivieren dieses Inhalts werden Daten wie Ihre IP-Adresse an den externen Server übertragen. Weitere Informationen entnehmen Sie bitte unserer <a href=":privacy_url" title="Datenschutzerklärung lesen">Datenschutzerklärung</a>.',
        'button' => 'Karte laden',
    ],

    'categories' => [
        'necessary' => [
            'label' => 'Notwendige',
            'description' => 'Stellt die Funktionalität der Website sicher.',
            'children' => [
                'settings' => [
                    'label' => 'Seiten-Einstellungen',
                    'description' => 'Speichert Ihre Einstellungen in diesem Banner.',
                ],
            ],
        ],
        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Erlauben Sie dem Website-Betreiber, das Angebot auf dieser Webseite zu bewerten und zu verbessern.',
            'children' => [
                'gtm' => [
                    'label' => 'Google Tag Manager',
                    'description' => 'Cookie _ga, Speicherdauer 2 Jahre',
                ],
            ],
        ],
        'functional' => [
            'label' => 'Funktionell',
            'description' => 'Funktionen für die Darstellung der Inhalte.',
        ],
    ],

];
