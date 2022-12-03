<?php

declare(strict_types=1);

use Bnomei\Utm;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;
use Kirby\Uuid\Uuid;

class UtmPage extends Page
{
    private $children_cache;

    public function uuid(): Uuid|null
    {
        return null;
    }

    public function reports(string $group = 'stats'): array
    {
        // https://getkirby.com/docs/reference/panel/sections/stats
        $useragents = Utm::singleton()->database()->query("SELECT user_agent, count(*) AS count FROM utm GROUP BY user_agent");
        $reports = [
            'stats' => [
                [
                    'label' => 'Campaigns',
                    'value' => Utm::singleton()->count("SELECT count(distinct(utm_campaign)) AS count FROM utm")
                ],
                [
                    'label' => 'Unique Visitors',
                    'value' => Utm::singleton()->count("SELECT count(distinct(iphash)) AS count FROM utm")
                    // TODO: percentage increase and theme
                ],
                [
                    'label' => 'Mobile',
                    'value' => $useragents->filterBy('user_agent', 'mobile')->first()?->count ?? 0,
                    // TODO: percentage increase and theme
                ],
                [
                    'label' => 'Tablet',
                    'value' => $useragents->filterBy('user_agent', 'tablet')->first()?->count ?? 0
                    // TODO: percentage increase and theme
                ],
                [
                    'label' => 'Desktop',
                    'value' => $useragents->filterBy('user_agent', 'desktop')->first()?->count ?? 0
                    // TODO: percentage increase and theme
                ],
            ]
        ];

        return  A::get($reports, $group);
    }

    public function children()
    {
        if ($this->children_cache) {
            return $this->children_cache;
        }

        $children = [];
        $db = Utm::singleton()->database();

        $results = $db->query('SELECT distinct(utm_campaign) as title FROM utm');
        if (!$results) {
            return [];
        }

        foreach ($results as $campaign) {
            $title = empty($campaign->title) ? 'undefined' : $campaign->title;
            $children[] = [
                'slug' => Str::slug($title),
                // 'num'      => 0,
                'template' => 'utm-campaign',
                'model' => 'utm-campaign',
                'content' => [
                    'title' => $title,
                    // other props are loaded on construction
                ]
            ];
        }

        $this->children_cache = Pages::factory($children, $this);
        return $this->children_cache;
    }
}
