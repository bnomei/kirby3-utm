<?php

@include_once __DIR__ . '/vendor/autoload.php';

load([
    'UtmPage' => 'models/UtmPage.php',
    'UtmcampaignPage' => 'models/UtmcampaignPage.php',
    'UtmeventPage' => 'models/UtmeventPage.php',
], __DIR__);

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
        'stats' => [
            'range' => 14, // in days
        ],
        'cache' => true,
    ],
    'blueprints' => [
        'pages/utm' => __DIR__ . '/blueprints/pages/utm.yml',
        'pages/utm-campaign' => __DIR__ . '/blueprints/pages/campaign.yml',
        'pages/utm-event' => __DIR__ . '/blueprints/pages/event.yml',
    ],
    'pageModels' => [
        'utm' => 'UtmPage',
        'utm-campaign' => 'UtmcampaignPage',
        'utm-event' => 'UtmeventPage',
    ],
    'routes' => [
        [
            'pattern' => '(:all)',
            'language' => '*',
            'action' => function ($language, $id) {
                if (Str::contains(A::get($_SERVER, 'QUERY_STRING', ''), 'utm_')) {
                    // single lang setup
                    if(kirby()->multilang() === false) {
                        if (!$id) {
                            $id = $language;
                        }

                    }
                    if (empty($id)) {
                        $id = site()->homePage()->id();
                    }

                    \Bnomei\Utm::singleton()->track($id, [
                        'utm_source' => get('utm_source'),
                        'utm_medium' => get('utm_medium'),
                        'utm_campaign' => get('utm_campaign'),
                        'utm_term' => get('utm_term'),
                        'utm_content' => get('utm_content'),
                    ]);
                }

                return $this->next();
            },
        ],
    ],
    'translations' => [
        'en' => [
            // defined inline as fallbacks
        ],
        'de' => [

        ],
    ]
]);
