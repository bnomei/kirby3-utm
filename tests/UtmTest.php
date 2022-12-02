<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;

final class UtmTest extends TestCase
{
    public function testInstance()
    {
        $utm = \Bnomei\Utm::singleton();

        $this->assertInstanceOf(\Bnomei\Utm::class, $utm);
    }

    public function testOption()
    {
        $utm = \Bnomei\Utm::singleton(['debug' => true]);

        $this->assertTrue($utm->option('debug'));
    }

    public function testTrack()
    {
        $id = page('home')->id();

        $utm = \Bnomei\Utm::singleton([
            'ip' => '169.150.197.101',
            'ipstack_access_key' => F::read(__DIR__ . '/.ipstackkey'),
        ]);

        $count = $utm->count();

        $utm->track($id, [
            'utm_source' => 'UTM_SOURCE',
            'utm_medium' => 'UTM_MEDIUM',
            'utm_campaign' => 'UTM_CAMPAIGN',
            'utm_term' => 'UTM_TERM',
            'utm_content' => 'UTM_CONTENT',
        ]);

        $this->assertEquals($count + 1, $utm->count());
    }

    // TODO: seed lots of events with faker
}
