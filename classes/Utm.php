<?php

declare(strict_types=1);

namespace Bnomei;

use Exception;
use Kirby\Database\Database;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

final class Utm
{
    /** @var Database */
    private $database;

    /** @var array $options */
    private $options;

    /** @var array $count */
    private $count;

    public function __construct(array $options = [])
    {
        $defaults = [
            'debug' => option('debug'),
            'enabled' => option('bnomei.utm.enabled'),
            'file' => option('bnomei.utm.sqlite.file'),
            'ip' => null,
            'ipstack_access_key' => option('bnomei.utm.ipstack.access_key'),
            'ipstack_https' => option('bnomei.utm.ipstack.https') ? 'https' : 'http',
            'stats_range' => option('bnomei.utm.stats.range'),
            'ratelimit_duration' => option('bnomei.utm.ratelimit.duration'),
            'ratelimit_trials' => option('bnomei.utm.ratelimit.trials'),
        ];
        $this->options = array_merge($defaults, $options);

        foreach ($this->options as $key => $call) {
            if (!is_string($call) && is_callable($call) && in_array($key, ['ip', 'ipstack_access_key', 'enabled', 'file'])) {
                $this->options[$key] = $call();
            }
        }

        if ($this->option('debug')) {
            try {
                kirby()->cache('bnomei.utm')->flush();
            } catch (Exception $e) {
                //
            }
        }

        $target = $this->options['file'];
        if (!F::exists($target)) {
            $db = new \SQLite3($target);
            $db->exec("CREATE TABLE IF NOT EXISTS utm (ID INTEGER PRIMARY KEY AUTOINCREMENT, page_id TEXT NOT NULL, utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_term TEXT, utm_content TEXT, visited_at DATETIME DEFAULT CURRENT_TIMESTAMP, iphash TEXT, country_name TEXT, city TEXT, user_agent TEXT)");
            $db->close();
        }

        $this->database = new Database([
            'type' => 'sqlite',
            'database' => $target,
        ]);

        $this->count = [];
    }

    /**
     * @param string|null $key
     * @return array|mixed
     */
    public function option(?string $key = null)
    {
        if ($key) {
            return A::get($this->options, $key);
        }
        return $this->options;
    }

    public function databaseFile(): string
    {
        return $this->options['file'];
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function track(string $id, array $params): bool
    {
        $ip = $this->option('ip') ?? kirby()->visitor()->ip();
        $iphash = sha1(__DIR__ . $ip);

        // check rate limit
        if($this->ratelimit($iphash) === false) {
            return false;
        }

        $params = $this->sanitize($params);

        if (count($params) === 0) {
            return false; // no UTM params at all
        }

        $ipdata = $this->ipstack($ip, $iphash);
        $generated = [
            'visited_at' => date('Y-m-d H:i:s', time()),
            'iphash' => $iphash,
            'country' => A::get($ipdata, 'country_name', ''),
            'city' => A::get($ipdata, 'city', ''),
            'useragent' => $this->useragent(),
        ];

        // allow generated to be overwritten by input params (for testing etc)
        $params = array_merge($generated, $params);

        // retrieve again after merging
        $utm_source = A::get($params, 'utm_source', '');
        $utm_medium = A::get($params, 'utm_medium', '');
        $utm_campaign = A::get($params, 'utm_campaign', '');
        $utm_term = A::get($params, 'utm_term', '');
        $utm_content = A::get($params, 'utm_content', '');
        $visited_at = A::get($params, 'visited_at', '');
        $iphash = A::get($params, 'iphash', '');
        $country = A::get($params, 'country', '');
        $city = A::get($params, 'city', '');
        $useragent = A::get($params, 'useragent', '');

        $this->database()->query("INSERT INTO utm (page_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content, visited_at, iphash, country_name, city, user_agent) VALUES ('${id}', '${utm_source}', '${utm_medium}', '${utm_campaign}', '${utm_term}', '${utm_content}', '${visited_at}', '${iphash}', '${country}', '${city}', '${useragent}')");

        $this->count = []; // reset static counts cache

        return true;
    }

    public function count(string $query = 'SELECT count(*) AS count FROM utm'): int
    {
        $key = md5($query);
        $this->count[$key] = intval($this->database->query($query)->first()->count);
        return $this->count[$key];
    }

    public function useragent(): string
    {
        $ua = strtolower(A::get($_SERVER, "HTTP_USER_AGENT", ''));
        $isMob = is_numeric(strpos($ua, "mobile"));
        if ($isMob) {
            return 'mobile';
        }
        $isTab = is_numeric(strpos($ua, "tablet"));
        if ($isTab) {
            return 'tablet';
        }
        // $isDesk = !$isMob && !$isTab;

        return 'desktop';
    }

    public function ipstack(string $ip, string $iphash = null): array
    {
        $key = $this->option('ipstack_access_key');

        // ip could be empty on unittests
        if (empty($ip) || empty($key)) {
            return [];
        }

        $cache = kirby()->cache('bnomei.utm');
        $iphash ??= sha1(__DIR__ . $ip);
        if ($data = $cache->get($iphash)) {
            return $data;
        }

        $https = $this->option('ipstack_https');
        $url = $https . "://api.ipstack.com/" . $ip . "/?access_key=" . $key;
        try {
            $response = Remote::get($url);
            $ipdata = $response->code() === 200 ?
                @json_decode($response->content(), true) :
                null;
        } catch (\Exception $e) {
            $ipdata = [
                'ip' => $ip,
                'hostname' => $ip,
            ];
        }

        unset($ipdata['ip']); // remove the plain ip
        unset($ipdata['hostname']); // remove the plain host
        $cache->set($iphash, $ipdata, intval($this->option('expire')));

        return $ipdata;
    }

    /** @var Utm */
    private static $singleton;

    /**
     * @param array $options
     * @return Utm
     */
    public static function singleton(array $options = [])
    {
        if (!self::$singleton) {
            self::$singleton = new self($options);
        }

        return self::$singleton;
    }

    private function sanitize(array $params)
    {
        $params = array_map(fn ($param) => \SQLite3::escapeString(strip_tags($param ?? '')), $params);
        return array_filter($params, fn ($param) => !empty($param));
    }

    private function ratelimit(string $iphash): bool
    {
        $cache = kirby()->cache('bnomei.utm');
        $key = $iphash . '-limit';
        $limit = $cache->get($key);

        // none yet or time passed
        if (!$limit ||
            $limit['time'] + $this->option('ratelimit_duration') < time()) {
            $cache->set($key, [
                'time' => time(),
                'trials' => 1,
            ]);
            return true;
        }

        // below trial limit
        if($limit['trials'] < $this->option('ratelimit_trials')) {
            $cache->set($key, [
                'time' => time(),
                'trials' => $limit['trials'] + 1,
            ]);
            return true;
        }

        return false; // limit reached
    }
}
