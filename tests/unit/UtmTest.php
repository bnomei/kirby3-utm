<?php

declare(strict_types=1);

use Bnomei\Utm;
use Faker\Factory;

test('instance', function () {
    $utm = new Utm;

    expect($utm)->toBeInstanceOf(Utm::class);
});

test('option', function () {
    $utm = new Utm(['debug' => true]);

    expect($utm->option('debug'))->toBeTrue();
});

test('ipstack URL uses HTTPS by default and encodes inputs', function () {
    $utm = new Utm([
        'ipstack_access_key' => 'key with /?&',
    ]);

    expect($utm->ipstackUrl('2001:db8::1'))->toBe('https://api.ipstack.com/2001%3Adb8%3A%3A1/?access_key=key%20with%20%2F%3F%26');

    $insecure = new Utm([
        'ipstack_access_key' => 'key',
        'ipstack_allow_insecure_http' => true,
    ]);

    expect(substr($insecure->ipstackUrl('127.0.0.1'), 0, 7))->toBe('http://');
});

test('track', function () {
    $id = page('home')->id();

    $utm = new Utm([
        'ip' => '169.150.197.101',
    ]);

    $count = $utm->count();

    $utm->track($id, [
        'utm_source' => 'UTM_SOURCE',
        'utm_medium' => 'UTM_MEDIUM',
        'utm_campaign' => 'UTM_CAMPAIGN',
        'utm_term' => 'UTM_TERM',
        'utm_content' => 'UTM_CONTENT',
    ]);

    expect($utm->count())->toEqual($count + 1);
});

test('rate limit', function () {
    $utm = new Utm([
        'ratelimit_trials' => 5,
        'ip' => '123.123.123.123', // different ip than other tests because of ratelimit
    ]);

    flushUtmTestState($utm);

    for ($n = 0; $n < 5; $n++) {
        expect($utm->track('home', [
            'utm_source' => 'UTM_SOURCE',
            'utm_medium' => 'UTM_MEDIUM',
            'utm_campaign' => 'UTM_CAMPAIGN',
            'utm_term' => 'UTM_TERM',
            'utm_content' => 'UTM_CONTENT',
        ]))->toBeTrue();
    }

    expect($utm->track('home', [
        'utm_source' => 'UTM_SOURCE',
        'utm_medium' => 'UTM_MEDIUM',
        'utm_campaign' => 'UTM_CAMPAIGN',
        'utm_term' => 'UTM_TERM',
        'utm_content' => 'UTM_CONTENT',
    ]))->toBeFalse();
});

test('rate limit uses configured expire window', function () {
    $ip = '203.0.113.60';
    $utm = new Utm([
        'ratelimit_trials' => 1,
        'ip' => $ip,
    ]);

    flushUtmTestState($utm);

    expect($utm->option('ratelimit_expire'))->toBe(60);

    kirby()->cache('bnomei.utm.ratelimit')->set(utmTestIphash($ip), [
        'time' => time() - 2,
        'trials' => 1,
    ], 60);

    expect($utm->track('home', [
        'utm_source' => 'UTM_SOURCE',
    ]))->toBeFalse();
});

test('track stores SQL control strings as literal values', function () {
    $utm = new Utm([
        'ratelimit_trials' => 999999,
        'ip' => '203.0.113.61',
    ]);

    flushUtmTestState($utm);

    $id = "home', 'x', 'x', 'x', 'x', 'x', '2000-01-01 00:00:00', 'forged', 'Injected', 'City', 'desktop') --";
    $campaign = "launch' OR 1=1 --";

    expect($utm->track($id, [
        'utm_source' => "source' OR 1=1 --",
        'utm_campaign' => $campaign,
    ]))->toBeTrue();

    $row = $utm->database()->query('SELECT page_id, utm_source, utm_campaign, iphash, country_name FROM utm ORDER BY ID DESC LIMIT 1')->first();

    expect($row->page_id)->toBe($id);
    expect($row->utm_campaign)->toBe($campaign);
    expect($row->iphash)->not->toBe('forged');
    expect($row->country_name)->not->toBe('Injected');
});

test('many events', function () {
    $faker = Factory::create('en');
    $utm = new Utm([
        'ratelimit_trials' => 999999, // allow mass creation
    ]);

    // flush
    $utm->database()->execute('DELETE FROM utm WHERE id > 0');

    $count = $utm->count();

    $days90to60 = new DatePeriod(new DateTime('now - 90 days'), new DateInterval('P1D'), new DateTime('now - 60 days'));
    foreach ($days90to60 as $day) {
        for ($c = 0; $c < 100; $c++) {
            createEvent($utm, $faker, $day);
        }
    }

    $days60to30 = new DatePeriod(new DateTime('now - 60 days'), new DateInterval('P1D'), new DateTime('now - 30 days'));
    foreach ($days60to30 as $day) {
        for ($c = 0; $c < 100; $c++) {
            createEvent($utm, $faker, $day);
        }
    }

    $days30to0 = new DatePeriod(new DateTime('now - 30 days'), new DateInterval('P1D'), new DateTime('now'));
    foreach ($days30to0 as $day) {
        for ($c = 0; $c < 300; $c++) {
            createEvent($utm, $faker, $day);
        }
    }

    expect($count < $utm->count())->toBeTrue();
});
function createEvent($utm, $faker, $day)
{
    $utm->track('home', [
        'visited_at' => $day->format('Y-m-d').' '.$faker->time('H:i:s', '23:59:59'),
        'iphash' => sha1(__DIR__.$faker->numberBetween(0, 200)),
        'country' => $faker->randomElement([
            'England',
            'France',
            'Germany',
            'Switzerland',
            'USA',
        ]),
        'city' => $faker->randomElement([
            'London',
            'Paris',
            'Berlin',
            'Zurich',
            'New York',
        ]),
        'useragent' => $faker->randomElement([
            'mobile',
            'tablet',
            'desktop',
        ]),
        'utm_source' => $faker->randomElement([
            'Destructiod',
            'Games Radar',
            'Metacritic',
            'GameSpot',
        ]),
        'utm_medium' => $faker->randomElement([
            'cpc',
            'email',
            'newsletter',
        ]),
        'utm_campaign' => $faker->randomElement([
            'Tunic', 'Tunic', 'Tunic',
            'Sifu', 'Sifu', 'Sifu',
            'Neon White', 'Neon White', 'Neon White',
            'Call Of Duty: Modern Warfare 2', 'Call Of Duty: Modern Warfare 2', 'Call Of Duty: Modern Warfare 2',
            'Immortality', 'Immortality', 'Immortality',
            'Xenoblade Chronicles 3', 'Xenoblade Chronicles 3', 'Xenoblade Chronicles 3',
            'A Plague Tale: Requiem', 'A Plague Tale: Requiem', 'A Plague Tale: Requiem', 'A Plague Tale: Requiem', 'A Plague Tale: Requiem',
            'Stray', 'Stray', 'Stray', 'Stray', 'Stray', 'Stray',
            'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West', 'Horizon Forbidden West',
            'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring', 'Elden Ring',
            'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök', 'God of War Ragnarök']),
        'utm_term' => $faker->word,
        'utm_content' => '',
    ]);
}

function flushUtmTestState($utm): void
{
    $utm->database()->execute('DELETE FROM utm WHERE id > 0');
    kirby()->cache('bnomei.utm.ratelimit')->flush();
    kirby()->cache('bnomei.utm.queries')->flush();
}

function utmTestIphash(string $ip): string
{
    return sha1(dirname(__DIR__, 2).'/classes'.$ip);
}
