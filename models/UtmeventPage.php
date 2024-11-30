<?php

declare(strict_types=1);

use Kirby\Cms\Page;
use Kirby\Toolkit\A;

class UtmeventPage extends Page
{
    public function __construct(array $props)
    {
        $title = $props['content']['title'];
        $query = "SELECT * FROM utm WHERE id='$title'";

        // NOTE: adding a cache here does not help

        $data = \Bnomei\Utm::singleton()->database()->query($query);

        $props['content'] = array_merge(
            $props['content'],
            [
                'page_id' => $data->first()->page_id,
                'utm_source' => $data->first()->utm_source,
                'utm_campaign' => $data->first()->utm_campaign,
                'utm_medium' => $data->first()->utm_medium,
                'utm_term' => $data->first()->utm_term,
                'utm_content' => $data->first()->utm_content,
                'visited_at' => $data->first()->visited_at,
                'iphash' => $data->first()->iphash,
                'country_name' => $data->first()->country_name,
                'city' => $data->first()->city,
                'user_agent' => $data->first()->user_agent,
            ]
        );

        parent::__construct($props);
    }

    public function uuid(): ?\Kirby\Uuid\Uuid
    {
        return null;
    }
}
