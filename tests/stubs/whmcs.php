<?php
/**
 * A stand-in for the parts of WHMCS the module touches, so the suite runs the
 * real module code without a WHMCS licence:
 *  - WHMCS\Database\Capsule is Illuminate's Capsule, exactly as in WHMCS;
 *  - add_hook / run_hook keep a registry the tests can fire;
 *  - localAPI is answered by FakeWhmcs against the same SQLite database.
 */

namespace WHMCS\Database {
    if (! class_exists(Capsule::class)) {
        class Capsule extends \Illuminate\Database\Capsule\Manager
        {
        }
    }
}

namespace WHMCS\Module {
    if (! class_exists(AbstractWidget::class)) {
        abstract class AbstractWidget
        {
            protected $title = '';
            protected $description = '';
            protected $weight = 100;
            protected $columns = 1;
            protected $cache = false;
            protected $requiredPermission = '';

            abstract public function getData();

            abstract public function generateOutput($data);
        }
    }
}

namespace {
    if (! defined('WHMCS')) {
        define('WHMCS', true);
    }

    $GLOBALS['__whmcs_hooks'] = [];
    $GLOBALS['__whmcs_activity'] = [];
    $GLOBALS['__whmcs_modulelog'] = [];

    function add_hook($name, $priority, $callback)
    {
        $GLOBALS['__whmcs_hooks'][$name][] = $callback;
    }

    /** Fire a hook the way WHMCS does: every callback, results collected. */
    function run_hook($name, array $vars)
    {
        $out = [];
        foreach ($GLOBALS['__whmcs_hooks'][$name] ?? [] as $cb) {
            $out[] = $cb($vars);
        }

        return $out;
    }

    function localAPI($command, $params = [], $adminUser = '')
    {
        return \NetArz\WhmcsAi\Tests\Support\FakeWhmcs::api((string) $command, (array) $params, (string) $adminUser);
    }

    function logActivity($message, $userId = 0)
    {
        $GLOBALS['__whmcs_activity'][] = $message;
    }

    function logModuleCall($module, $action, $request, $response, $processed = '', $replace = [])
    {
        $GLOBALS['__whmcs_modulelog'][] = compact('module', 'action', 'request', 'response', 'processed', 'replace');
    }

    function encrypt($value)
    {
        return 'enc:'.strrev(base64_encode((string) $value));
    }

    function decrypt($value)
    {
        return strpos((string) $value, 'enc:') === 0 ? base64_decode(strrev(substr((string) $value, 4))) : '';
    }
}
