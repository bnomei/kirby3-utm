<?php

use Bnomei\Utm;
use Bnomei\UtmbarField;
use Kirby\Content\Field;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

if (! class_exists(Utm::class)) {
    require_once __DIR__.'/vendor/autoload.php';
}

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
            'expire' => 60 * 24, // in minutes
            'allowInsecureHttp' => false, // true sends IP data and access key over cleartext HTTP
        ],
        'sqlite' => [
            'file' => function () {
                if (! Dir::exists(kirby()->roots()->logs())) {
                    Dir::make(kirby()->roots()->logs());
                }

                return kirby()->roots()->logs().'/utm.sqlite'; // @phpstan-ignore-line
            },
        ],
        'botDetection' => [
            'CrawlerDetect' => true, // almost no overhead, ~10ms
            'DeviceDetector' => true, // ~40ms
        ],
        'stats' => [
            'range' => 30, // in days
        ],
        'ratelimit' => [
            'enabled' => true,
            'expire' => 60, // 1h in minutes
            'trials' => 120, // within given duration
        ],
        'cache' => true,
        'cache.ipstack' => true,
        'cache.ratelimit' => true,
        'cache.queries' => true,
    ],
    'blueprints' => [
        'pages/utm' => __DIR__.'/blueprints/pages/utm.yml',
        'pages/utm-campaign' => __DIR__.'/blueprints/pages/campaign.yml',
        'pages/utm-event' => __DIR__.'/blueprints/pages/event.yml',
    ],
    'fieldMethods' => [
        'toStatsPercent' => function (Field $field): string {
            $val = $field->toInt();

            return ($val >= 0 ? '+' : '').$val.'%';
        },
        'toStatsTheme' => function (Field $field) {
            return match (true) {
                $field->toInt() > 0 => 'positive',
                $field->toInt() < 0 => 'negative',
                default => 'info',
            };
        },
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
            'action' => function ($language, $id = null) {
                if (Str::contains(A::get($_SERVER, 'QUERY_STRING', ''), 'utm_')) {
                    // single lang setup
                    if (kirby()->multilang() === false) {
                        if (! $id) {
                            $id = $language;
                        }
                    }
                    if (empty($id)) {
                        $id = site()->homePage()?->id();
                    }

                    Utm::singleton()->track($id, [
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
    'fields' => [
        'utmbar' => UtmbarField::class,
    ],
    'translations' => [
        'en' => [
            // defined inline as fallbacks
        ],
        'de' => [

        ],
    ],
]);
