<?php

require_once __DIR__.'/vendor/autoload.php';

$roots = [
    'index' => __DIR__,
    'media' => __DIR__.'/public/media',
];
if (getenv('APP_ENV') === 'testing') {
    $roots['logs'] = __DIR__.'/logs';
    $roots['cache'] = __DIR__.'/logs/cache';
}
if ($runtime = getenv('UTM_PANEL_TEST_ROOT')) {
    foreach (['accounts', 'sessions', 'cache', 'logs'] as $name) {
        $roots[$name] = $runtime.'/'.$name;
    }
}

return new Kirby(['roots' => $roots]);
