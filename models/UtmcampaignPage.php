<?php

declare(strict_types=1);

use Bnomei\Utm;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class UtmcampaignPage extends Page
{
    private ?Pages $children_cache = null;

    public function __construct(array $props)
    {
        $title = $props['content']['title'];
        if ($title === 'undefined') {
            $title = '';
        }
        $query = "SELECT count(*) as events_count, MAX(visited_at) as visited_at, count(distinct(iphash)) AS unique_visitors FROM utm WHERE utm_campaign='$title'";
        $key = md5($query).'-campaign';
        $cache = kirby()->cache('bnomei.utm.queries');
        if ($propsCache = $cache->get($key)) {
            // will call parent::__construct($props) later
        } else {
            $utm = \Bnomei\Utm::singleton();
            $data = $utm->database()->query($query);
            $dataRecent = $utm->database()->query($query.' AND '.Utm::sqliteDateRange(
                intval($utm->option('stats_range')),
                0,
                'visited_at'
            ));
            $dataCompare = $utm->database()->query($query.' AND '.Utm::sqliteDateRange(
                intval($utm->option('stats_range')) * 2,
                intval($utm->option('stats_range')),
                'visited_at'
            ));

            $ua_query = "SELECT user_agent, count(*) AS count FROM utm WHERE utm_campaign='$title'";
            $ua = $utm->database()->query($ua_query.' GROUP BY user_agent');
            $ua_queryRecent = $utm->database()->query($ua_query.' AND '.Utm::sqliteDateRange(
                intval($utm->option('stats_range')),
                0,
                'visited_at'
            ).' GROUP BY user_agent');
            $ua_queryCompare = $utm->database()->query($ua_query.' AND '.Utm::sqliteDateRange(
                intval($utm->option('stats_range')) * 2,
                intval($utm->option('stats_range')),
                'visited_at'
            ).' GROUP BY user_agent');

            $propsCache = [
                'events_count' => $data->first()->events_count,
                'events_count_change' => Utm::percentChange($dataRecent->first()->events_count, $dataCompare->first()->events_count),
                'unique_visitors' => $data->first()->unique_visitors,
                'unique_visitors_change' => Utm::percentChange($dataRecent->first()->unique_visitors, $dataCompare->first()->unique_visitors),
                'visited_at' => $data->first()->visited_at,
                'mobile' => $ua->filterBy('user_agent', 'mobile')->first()?->count ?? 0,
                'mobile_change' => Utm::percentChange(($ua_queryRecent->filterBy('user_agent', 'mobile')->first()?->count ?? 0), ($ua_queryCompare->filterBy('user_agent', 'mobile')->first()?->count ?? 0)),
                'tablet' => $ua->filterBy('user_agent', 'tablet')->first()?->count ?? 0,
                'tablet_change' => Utm::percentChange(($ua_queryRecent->filterBy('user_agent', 'tablet')->first()?->count ?? 0), ($ua_queryCompare->filterBy('user_agent', 'tablet')->first()?->count ?? 0)),
                'desktop' => $ua->filterBy('user_agent', 'desktop')->first()?->count ?? 0,
                'desktop_change' => Utm::percentChange(($ua_queryRecent->filterBy('user_agent', 'desktop')->first()?->count ?? 0), ($ua_queryCompare->filterBy('user_agent', 'desktop')->first()?->count ?? 0)),
            ];

            $cache->set($key, $propsCache);
        }

        $props['content'] = array_merge(
            $props['content'],
            $propsCache
        );

        parent::__construct($props);
    }

    public function uuid(): ?\Kirby\Uuid\Uuid
    {
        return null;
    }

    public function reports(string $group = 'stats'): array
    {
        $title = $this->title()->value() === 'undefined' ? '' : $this->title()->value();

        $key = md5($title).'-reports';
        $cache = kirby()->cache('bnomei.utm.queries');
        if ($reports = $cache->get($key)) {
            // will call Pages::factory later
        } else {
            // https://getkirby.com/docs/reference/panel/sections/stats
            $reports['stats'] = [
                [
                    'label' => 'Events',
                    'value' => $this->events_count()->toInt(),
                    'info' => $this->events_count_change()->toStatsPercent(),
                    'theme' => $this->events_count_change()->toStatsTheme(),
                ],
                [
                    'label' => 'Unique Visitors',
                    'value' => $this->unique_visitors()->value(),
                    'info' => $this->unique_visitors_change()->toStatsPercent(),
                    'theme' => $this->unique_visitors_change()->toStatsTheme(),
                ],
                [
                    'label' => 'Mobile',
                    'value' => $this->mobile()->value(),
                    'info' => $this->mobile_change()->toStatsPercent(),
                    'theme' => $this->mobile_change()->toStatsTheme(),
                ],
                [
                    'label' => 'Tablet',
                    'value' => $this->tablet()->value(),
                    'info' => $this->tablet_change()->toStatsPercent(),
                    'theme' => $this->tablet_change()->toStatsTheme(),
                ],
                [
                    'label' => 'Desktop',
                    'value' => $this->desktop()->value(),
                    'info' => $this->desktop_change()->toStatsPercent(),
                    'theme' => $this->desktop_change()->toStatsTheme(),
                ],
            ];

            $db = \Bnomei\Utm::singleton()->database();

            $sources = [];
            $data = $db->query("SELECT distinct(utm_source) AS title, count(*) as count FROM utm WHERE utm_campaign='$title' GROUP BY utm_source ORDER BY count desc LIMIT 5");
            foreach ($data as $source) {
                if (empty($source->title)) {
                    continue;
                }
                $sources[] = [
                    'label' => $source->title,
                    'value' => $source->count,
                ];
            }
            $reports['source'] = $sources;

            $mediums = [];
            $data = $db->query("SELECT distinct(utm_medium) AS title, count(*) as count FROM utm WHERE utm_campaign='$title' GROUP BY utm_medium ORDER BY count desc LIMIT 5");
            foreach ($data as $medium) {
                if (empty($medium->title)) {
                    continue;
                }
                $mediums[] = [
                    'label' => $medium->title,
                    'value' => $medium->count,
                ];
            }
            $reports['medium'] = $mediums;

            $countrys = [];
            $data = $db->query("SELECT distinct(country_name) AS title, count(*) as count FROM utm WHERE utm_campaign='$title' GROUP BY country_name ORDER BY count desc LIMIT 5");
            foreach ($data as $country) {
                if (empty($country->title)) {
                    continue;
                }
                $countrys[] = [
                    'label' => $country->title,
                    'value' => $country->count,
                ];
            }
            $reports['country'] = $countrys;

            $citys = [];
            $data = $db->query("SELECT distinct(city) AS title, count(*) as count FROM utm WHERE utm_campaign='$title' GROUP BY city ORDER BY count desc LIMIT 5");
            foreach ($data as $city) {
                if (empty($city->title)) {
                    continue;
                }
                $citys[] = [
                    'label' => $city->title,
                    'value' => $city->count,
                ];
            }
            $reports['city'] = $citys;

            $cache->set($key, $reports);
        }

        return A::get($reports, $group);
    }

    public function children(): Pages
    {
        if ($this->children_cache) {
            return $this->children_cache;
        }

        $children = [];
        $db = Utm::singleton()->database();

        $results = $db->query("SELECT id as title FROM utm WHERE utm_campaign='".$this->title()->value()."'");
        if (! $results) {
            return [];
        }
        foreach ($results as $event) {
            $title = $event->title;
            $children[] = [
                'slug' => Str::slug($title),
                // 'num'      => 0,
                'template' => 'utm-event',
                'model' => 'utm-event',
                'content' => [
                    'title' => $title,
                    // other props are loaded on construction
                ],
            ];
        }

        $this->children_cache = Pages::factory($children, $this);

        return $this->children_cache;
    }
}
