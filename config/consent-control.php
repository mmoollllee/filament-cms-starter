<?php

// Only what differs from mmoollllee/laravel-consent-control's defaults. Everything
// omitted (driver, links, banner) falls back to the package default.
// mergeConfigFrom() is a flat top-level merge, so `categories` replaces the default
// list wholesale: every category the banner should offer must be declared here.
return [
    // Bump this whenever a category is added or its meaning changes — visitors
    // who already consented are then asked again instead of silently carrying a
    // cookie that never mentioned the new category.
    'version' => 2,

    /*
    | The vendor default derives an explicit cookie domain from APP_URL, which on
    | a multi-tenant engine would share one consent across tenants (parent-domain
    | cookie) or break the write entirely on a host that is not a subdomain of it.
    | null keeps the cookie host-only, i.e. one consent per tenant domain.
    */
    'cookie' => [
        'name' => env('CONSENT_COOKIE_NAME', 'consentcontrol'),
        'days' => env('CONSENT_COOKIE_DAYS', 365),
        'domain' => env('CONSENT_COOKIE_DOMAIN'),
        'path' => '/',
        'same_site' => 'lax',
        'secure' => env('CONSENT_COOKIE_SECURE', false),
    ],

    'categories' => [
        'necessary' => [
            'label' => 'consent-control::consent.categories.necessary.label',
            'description' => 'consent-control::consent.categories.necessary.description',
            'checked' => true,
            'disabled' => true,
            'children' => [
                [
                    'label' => 'consent-control::consent.categories.necessary.children.settings.label',
                    'description' => 'consent-control::consent.categories.necessary.children.settings.description',
                ],
                // Listed rather than gated: Umami counts pageviews without cookies
                // and stores nothing on the device, so there is nothing to consent
                // to — but visitors should still see that it happens. Remove this
                // entry together with the filami wiring if a project drops Umami.
                [
                    'label' => 'Reichweitenmessung',
                    'description' => 'Zählt Seitenaufrufe, ohne Cookies und ohne IP-Adressen zu speichern und ohne externe Dienste.',
                ],
            ],
        ],
        'functional' => [
            'label' => 'consent-control::consent.categories.functional.label',
            'description' => 'consent-control::consent.categories.functional.description',
        ],
        // Gates only Umami's recorder script (session replay + heatmaps), which
        // filami emits as an inert script[type="text/plain"][data-consent="analytics"]
        // tag — see UMAMI_RECORDER_CONSENT_CATEGORY in .env. The plain pageview
        // tracker is never gated; it is listed under "necessary" instead.
        'analytics' => [
            'label' => 'Auswertung',
            'description' => 'Zeichnet auf, wie Seiten bedient werden (Klicks, Mausbewegungen, Scrollen) um unser Angebot zu verbessern.',
            'checked' => false,
        ],
    ],
];
