<?php
/**
 * php tests/e2e/setup.php <whmcs-url> <gateway-url>
 * Creates the SQLite WHMCS, activates the module and connects it to the mock gateway.
 */

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
require $root.'/tests/stubs/whmcs.php';
require $root.'/tests/Support/FakeWhmcs.php';
require $root.'/modules/addons/netarz_ai/autoload.php';

use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;
use WHMCS\Database\Capsule;

$db = getenv('NTZ_E2E_DB') ?: sys_get_temp_dir().'/netarz-ai-whmcs-e2e.sqlite';
@unlink($db);
touch($db);
FakeWhmcs::boot($db);
FakeWhmcs::schema();
FakeWhmcs::seed();
Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->update(['value' => rtrim($argv[1], '/').'/']);

require $root.'/modules/addons/netarz_ai/netarz_ai.php';
$result = netarz_ai_activate();
if ($result['status'] !== 'success') {
    fwrite(STDERR, $result['description']."\n");
    exit(1);
}

Settings::set('api_key', 'sk-ntz-v1-e2etestkey0123456789ab');
Settings::set('base_url', rtrim($argv[2], '/').'/api/ai/v1');
Settings::set('ticket_mode', 'draft');
Settings::set('chat_burst_ms', 1500);
Settings::set('chat_handoff_wait', 1);
Settings::set('knowledge_custom', "Support hours: Saturday to Wednesday, 9:00 to 17:00 Tehran time.\nRefunds: shared hosting within 7 days; domains are never refunded.");

echo "ready\n";
