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
    private ?Pages $children_cache = null;

    public function uuid(): ?Uuid
    {
        return null;
    }

    public function reports(string $group = 'stats'): array
    {
        $key = md5(__FILE__).'-reports';
        $cache = kirby()->cache('bnomei.utm.queries');
        if ($reports = $cache->get($key)) {
            // will call return later
        } else {
            // https://getkirby.com/docs/reference/panel/sections/stats
            $useragents = Utm::singleton()->database()->query('SELECT user_agent, count(*) AS count FROM utm GROUP BY user_agent');
            $reports = [
                'stats' => [
                    [
                        'label' => 'Campaigns',
                        'value' => Utm::singleton()->count('SELECT count(distinct(utm_campaign)) AS count FROM utm'),
                    ],
                    [
                        'label' => 'Unique Visitors',
                        'value' => Utm::singleton()->count('SELECT count(distinct(iphash)) AS count FROM utm'),
                    ],
                    [
                        'label' => 'Mobile',
                        'value' => $useragents->filterBy('user_agent', 'mobile')->first()?->count ?? 0, // @phpstan-ignore-line
                    ],
                    [
                        'label' => 'Tablet',
                        'value' => $useragents->filterBy('user_agent', 'tablet')->first()?->count ?? 0, // @phpstan-ignore-line
                    ],
                    [
                        'label' => 'Desktop',
                        'value' => $useragents->filterBy('user_agent', 'desktop')->first()?->count ?? 0, // @phpstan-ignore-line
                    ],
                ],
            ];
        }

        return A::get($reports, $group);
    }

    public function children(): Pages
    {
        if ($this->children_cache) {
            return $this->children_cache;
        }

        $db = Utm::singleton()->database();

        $query = 'SELECT distinct(utm_campaign) as title FROM utm';
        $key = md5($query).'-page-children';
        $cache = kirby()->cache('bnomei.utm.queries');
        if ($children = $cache->get($key)) {
            // will call Pages::factory later
        } else {
            $results = $db->query($query);
            if (! $results) {
                return new Pages;
            }

            $children = [];
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
                    ],
                ];
            }
            $cache->set($key, $children);
        }

        $this->children_cache = Pages::factory($children, $this);

        return $this->children_cache;
    }
}
