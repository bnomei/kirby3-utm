<?php

declare(strict_types=1);

namespace Bnomei;

use DateTimeImmutable;
use Kirby\Form\Field\DisplayField;

final class UtmbarField extends DisplayField
{
    public function props(): array
    {
        $title = $this->model()->content()->get('title')->toString();

        return [...parent::props(), 'data' => $this->bars($title === 'undefined' ? '' : $title)];
    }

    private function bars(string $campaign): array
    {
        $key = md5($campaign).'-bar';
        $cache = $this->kirby()->cache('bnomei.utm.queries');
        if (option('debug') !== true && is_array($data = $cache->get($key))) {
            return $data;
        }

        $utm = Utm::singleton();
        $days = max(1, 2 * intval($utm->option('stats_range')));
        $start = new DateTimeImmutable('today -'.($days - 1).' days');
        $rows = $utm->database()->query("SELECT date(visited_at) AS day, count(*) AS amount FROM utm WHERE utm_campaign = ? AND visited_at BETWEEN ? AND datetime('now', 'localtime') GROUP BY day", [$campaign, $start->format('Y-m-d')]);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->day] = (int) $row->amount;
        }
        if ($counts === []) {
            return [];
        }

        $max = max($counts);
        $avg = array_sum($counts) / count($counts);
        $width = 100 / $days;
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify("+$i days");
            $amount = $counts[$day->format('Y-m-d')] ?? 0;
            $height = (int) ($amount / $max * 100);
            $theme = $amount > $avg ? 'positive' : ($amount && $amount < $avg ? 'negative' : 'info');
            $data[] = [
                'date' => $day->format(strval(option('bnomei.utm.bar.format', 'Y-m-d'))),
                'amount' => $amount,
                'style' => "width: {$width}%; height: {$height}px;",
                'theme' => $theme.($amount > 100 ? ' rotate' : ''),
            ];
        }
        if (option('debug') !== true) {
            $cache->set($key, $data, 1);
        }

        return $data;
    }
}
