<?php

namespace NetArz\WhmcsAi\Tests;

use NetArz\WhmcsAi\Clock;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\Schema;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tickets;
use NetArz\WhmcsAi\Tests\Support\FakeGateway;
use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;
use NetArz\WhmcsAi\Whmcs;

/**
 * Every test gets a fresh WHMCS (SQLite in memory), the module installed as
 * WHMCS would activate it, the hooks registered, and a fake NetArz gateway.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var bool */
    private static $hooksLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        FakeWhmcs::boot();
        FakeWhmcs::schema();
        FakeWhmcs::seed();
        FakeGateway::install();
        Whmcs::$localApi = null;
        Clock::$frozen = null;
        Tickets::$posting = false;
        Settings::flush();
        Lang::use('english');
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '203.0.113.9', 'HTTP_HOST' => 'my.parshost.test', 'REQUEST_URI' => '/clientarea.php', 'HTTP_USER_AGENT' => 'PHPUnit'];
        $_GET = $_POST = [];

        if (! self::$hooksLoaded) {
            require __DIR__.'/../modules/addons/netarz_ai/netarz_ai.php';
            require __DIR__.'/../modules/addons/netarz_ai/hooks.php';
            self::$hooksLoaded = true;
        }

        $result = netarz_ai_activate();
        $this->assertSame('success', $result['status'], $result['description']);
    }

    protected function tearDown(): void
    {
        FakeGateway::uninstall();
        Clock::$frozen = null;
        parent::tearDown();
    }

    /** Connect the module the way an owner does: key, model, instant tickets off (tests process jobs explicitly). */
    protected function connect(array $settings = []): void
    {
        Settings::set('api_key', 'sk-ntz-v1-testkey0123456789abcdef');
        Settings::set('ticket_instant', 0);
        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
        }
    }

    protected function loginClient(int $id): void
    {
        $_SESSION['uid'] = $id;
    }

    protected function loginAdmin(int $id = 1): void
    {
        $_SESSION['adminid'] = $id;
    }

    /** Move the frozen clock forward. */
    protected function travel(int $seconds): void
    {
        Clock::$frozen = (Clock::$frozen ?? time()) + $seconds;
    }
}
