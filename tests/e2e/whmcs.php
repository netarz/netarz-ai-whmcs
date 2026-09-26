<?php
/**
 * Boots the WHMCS stand-in for one HTTP request of the end-to-end run:
 * one SQLite file shared by every request, the stubs, the module and its hooks.
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
require $root.'/tests/stubs/whmcs.php';
require $root.'/tests/Support/FakeWhmcs.php';
require $root.'/modules/addons/netarz_ai/autoload.php';

use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;

date_default_timezone_set('Asia/Tehran');
session_name('WHMCSe2e');
session_start();

$db = getenv('NTZ_E2E_DB') ?: sys_get_temp_dir().'/netarz-ai-whmcs-e2e.sqlite';
$capsule = new \WHMCS\Database\Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => $db, 'prefix' => '']);
$capsule->setAsGlobal();

// FakeWhmcs::reset() would drop the session; keep only the call log clean.
FakeWhmcs::$calls = [];

require $root.'/modules/addons/netarz_ai/netarz_ai.php';
require $root.'/modules/addons/netarz_ai/hooks.php';
