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
            'range' => 30, // in days
        ],
        'ratelimit' => [
            'duration' => 60*60, // 1h in seconds
            'trials' => 120, // within given duration
        ],
        'cache' => true,
    ],
    'blueprints' => [
        'pages/utm' => __DIR__ . '/blueprints/pages/utm.yml',
        'pages/utm-campaign' => __DIR__ . '/blueprints/pages/campaign.yml',
        'pages/utm-event' => __DIR__ . '/blueprints/pages/event.yml',
    ],
    'fieldMethods' => [
        'toStatsPercent' => function ($field) {
            $val = $field->toInt();
            return ($val >= 0 ? '+' : '') . $val . '%';
        },
        'toStatsTheme' => function ($field) {
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
            'action' => function ($language, $id) {
                if (Str::contains(A::get($_SERVER, 'QUERY_STRING', ''), 'utm_')) {
                    // single lang setup
                    if (kirby()->multilang() === false) {
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
    'fields' => [
        'utmbar' => [
            'props' => [
                'data' => function () {
                    $model = $this->model();
                    $title = $model->title()->value() === 'undefined' ? '' : $model->title()->value();
                    $key = md5($title) . '-bar';
                    if($data = kirby()->cache('bnomei.utm')->get($key)) {
                        return $data;
                    }
                    $utm = \Bnomei\Utm::singleton();
                    $query = "SELECT count(*) AS events_count, strftime('%Y/%m/%d', visited_at) AS event_day FROM utm WHERE utm_campaign='${title}' AND " . \Bnomei\Utm::sqliteDateRange(
                        $utm->option('stats_range') * 2,
                        0,
                        'visited_at'
                    ). ' GROUP BY event_day ORDER BY event_day asc';

                    $events = $utm->database()->query($query);
                    $max = $events->sortBy('events_count', 'desc')->first()?->events_count ?? 0;
                    $avg = array_sum($events->values(fn ($item) => $item->events_count)) / $events->count();

                    $days = new DatePeriod(new DateTime('now - ' . $utm->option('stats_range') * 2 . ' days'), new DateInterval('P1D'), new DateTime('now'));
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
                            'amount' => $amount,
                            'date' => $day->format(option('bnomei.utm.bar.format', 'Y-m-d')),
                            'style' => "width: ${width}%; height: ${height}px;",
                            'theme' => $theme,
                        ];
                    }
                    if (!option('debug')) {
                        kirby()->cache('bnomei.utm')->set($key, $data, 1);
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
    ]
]);
