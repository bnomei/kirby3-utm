<?php

use Kirby\Content\Field;
use Kirby\Filesystem\Dir;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

@include_once __DIR__.'/vendor/autoload.php';

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
            'https' => false, // only premium accounts can do that
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
            $val = $field->toInt(); // @phpstan-ignore-line

            return ($val >= 0 ? '+' : '').$val.'%';
        },
        'toStatsTheme' => function (Field $field) {
            return match (true) {
                $field->toInt() > 0 => 'positive', // @phpstan-ignore-line
                $field->toInt() < 0 => 'negative', // @phpstan-ignore-line
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
    'fields' => [
        'utmbar' => [
            'props' => [
                'data' => function () {
                    $model = $this->model();
                    $title = $model->title()->value() === 'undefined' ? '' : $model->title()->value();
                    $key = md5($title).'-bar';
                    if (option('debug') !== true && $data = kirby()->cache('bnomei.utm.queries')->get($key)) {
                        return $data;
                    }
                    $utm = \Bnomei\Utm::singleton();
                    $query = "SELECT count(*) AS events_count, strftime('%Y/%m/%d', visited_at) AS event_day FROM utm WHERE utm_campaign='$title' AND ".\Bnomei\Utm::sqliteDateRange(
                        intval($utm->option('stats_range')) * 2,
                        0,
                        'visited_at'
                    ).' GROUP BY event_day ORDER BY event_day asc';

                    $events = $utm->database()->query($query);
                    if ($events->count() === 0) {
                        return [];
                    }
                    $max = $events->sortBy('events_count', 'desc')->first()?->events_count ?? 0; // @phpstan-ignore-line
                    $avg = array_sum($events->values(fn ($item) => $item->events_count)) / $events->count();

                    $days = new DatePeriod(new DateTime('now - '.intval($utm->option('stats_range')) * 2 - 1 .' days'), new DateInterval('P1D'), new DateTime('now + 1 day'));
                    $num = 0;
                    foreach ($days as $day) {
                        $num++;
                    }
                    $width = round(100 / $num, 2);

                    $data = [];
                    foreach ($days as $day) {
                        $amount = 0;
                        $height = 0;
                        $theme = 'info';
                        if ($event = $events->filterBy('event_day', $day->format('Y/m/d'))->first()) {
                            $amount = $event->events_count;
                            $height = intval($amount / $max * 100.0);
                            if ($amount > $avg) {
                                $theme = 'positive';
                            } elseif ($amount < $avg) {
                                $theme = 'negative';
                            }
                        }
                        $data[] = [
                            'amount' => $amount, // $amount > 1000 ? ($amount / 1000) . 'k' : $amount,
                            'date' => $day->format(strval(option('bnomei.utm.bar.format', 'Y-m-d'))),
                            'style' => "width: {$width}%; height: {$height}px;",
                            'theme' => $theme.($amount > 100 ? ' rotate' : ''),
                        ];
                    }
                    if (option('debug') !== true) {
                        kirby()->cache('bnomei.utm.queries')->set($key, $data, 1);
                    }

                    return $data;
                },
            ],
        ],
    ],
    'translations' => [
        'en' => [
            // defined inline as fallbacks
        ],
        'de' => [

        ],
    ],
]);
