<?php

declare(strict_types=1);

use Bnomei\Utm;
use Kirby\Cms\Page;
use Kirby\Cms\Pages;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Str;

class UtmcampaignPage extends Page
{
    private $children_cache;

    public function __construct(array $props)
    {
        $title = $props['content']['title'];
        if ($title === 'undefined') {
            $title = '';
        }
        $data = \Bnomei\Utm::singleton()->database()->query("SELECT count(*) as events_count, MAX(visited_at) as visited_at, count(distinct(iphash)) AS unique_visitors FROM utm WHERE utm_campaign='${title}'");

        $props['content'] = array_merge(
            $props['content'],
            [
                'events_count' => $data->first()->events_count,
                'unique_visitors' => $data->first()->unique_visitors,
                'visited_at' => $data->first()->visited_at,
            ]
        );

        parent::__construct($props);
    }

    public function uuid(): \Kirby\Uuid\Uuid|null
    {
        return null;
    }

    public function reports(string $group = 'stats'): array
    {
        // https://getkirby.com/docs/reference/panel/sections/stats
        $title = $this->title()->value() ? $this->title()->value() : 'undefined';
        $useragents = Utm::singleton()->database()->query("SELECT user_agent, count(*) AS count FROM utm WHERE utm_campaign='${title}' GROUP BY user_agent");
        $reports = [
            'stats' => [
                [
                    'label' => 'Events',
                    'value' => $this->events_count()->value(),
                    // TODO: percentage increase and theme
                ],
                [
                    'label' => 'Unique Visitors',
                    'value' => $this->unique_visitors()->value()
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

        return A::get($reports, $group);
    }

    public function children()
    {
        if ($this->children_cache) {
            return $this->children_cache;
        }

        $children = [];
        $db = Utm::singleton()->database();

        $results = $db->query("SELECT id as title FROM utm WHERE utm_campaign='" . $this->title()->value() . "'");
        if (!$results) return [];
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
                ]
            ];
        }

        $this->children_cache = Pages::factory($children, $this);
        return $this->children_cache;
    }
}
