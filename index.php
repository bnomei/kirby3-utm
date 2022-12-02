<?php

@include_once __DIR__ . '/vendor/autoload.php';

Kirby::plugin('bnomei/utm', [
    'options' => [
        'enabled' => true, // or callback
        'ipstack' => [
            'access_key' => fn () => null, // free key from https://ipstack.com/
            'https' => false, // only premium accounts can do that
        ],
        'expire' => 60*24, // in minutes
        'sqlite' => [
            'file' => function () {
                if (!Dir::exists(kirby()->roots()->logs())) {
                    Dir::make(kirby()->roots()->logs());
                }
                return kirby()->roots()->logs() . '/utm.sqlite';
            },
        ],
        'cache' => true,
    ],
    'routes' => [
        [
            'pattern' => '(:all)',
            'language' => '*',
            'action' => function ($language, $id) {
                // single lang setup
                if (!$id) {
                    $id = $language;
                }

                \Bnomei\Utm::singleton()->track($id, [
                    'utm_source' => get('utm_source'),
                    'utm_medium' => get('utm_medium'),
                    'utm_campaign' => get('utm_campaign'),
                    'utm_term' => get('utm_term'),
                    'utm_content' => get('utm_content'),
                ]);

                return $this->next();
            },
        ],
        // TODO: add virtual pages for UTM, per filter drill down
        // utm => link to lists
        // utm/source => list of all sources ==> utm/source/(:any) slug of source
        // utm/term => list
        // etc...
        // utm/utm_source, utm_medium, utm_campaign, utm_term, utm_content, visited_at, iphash, country, city
    ],
    'translations' => [
        'en' => [
            // defined inline as fallbacks
        ],
        'de' => [

        ],
    ]
]);
