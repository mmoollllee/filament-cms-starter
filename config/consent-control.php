<?php

// Only what differs from mmoollllee/laravel-consent-control's defaults. Everything
// omitted (driver, cookie, links, banner, version) falls back to the package default.
return [
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
            ],
        ],
        'functional' => [
            'label' => 'consent-control::consent.categories.functional.label',
            'description' => 'consent-control::consent.categories.functional.description',
        ],
    ],
];
