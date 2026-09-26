<?php
/**
 * NetArz AI hooks. WHMCS loads this file on every page once the module is active.
 */

use NetArz\WhmcsAi\Admin\Csrf;
use NetArz\WhmcsAi\Balance;
use NetArz\WhmcsAi\Chat;
use NetArz\WhmcsAi\Clock;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tickets;
use NetArz\WhmcsAi\Text;
use NetArz\WhmcsAi\View;
use NetArz\WhmcsAi\Whmcs;

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once __DIR__.'/autoload.php';

/** Is the module installed (tables present)? Hooks stay silent until it is. */
function netarz_ai_ready()
{
    static $ready = null;
    if ($ready === null) {
        try {
            $ready = \WHMCS\Database\Capsule::schema()->hasTable(Settings::TABLE);
        } catch (\Throwable $e) {
            $ready = false;
        }
    }

    return $ready;
}

/* ------------------------------------------------------------------ chat */

add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    if (! netarz_ai_ready() || ! Settings::bool('chat_enabled')) {
        return '';
    }

    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    foreach (preg_split('/\R/', (string) Settings::get('chat_hide_paths')) as $path) {
        $path = trim($path);
        if ($path !== '' && strpos($uri, $path) !== false) {
            return '';
        }
    }

    $endpoint = Whmcs::systemUrl().'index.php?m=netarz_ai';
    $rtl = \NetArz\WhmcsAi\PublicApi::language() === 'fa';

    return '<link rel="stylesheet" href="'.Text::e(View::asset('chat.css')).'">'
        .'<script src="'.Text::e(View::asset('chat.js')).'" defer data-netarz-chat data-endpoint="'.Text::e($endpoint).'"'
        .' data-color="'.Text::e(Settings::get('chat_color')).'" data-text-color="'.Text::e(Settings::get('chat_text_color')).'"'
        .' data-position="'.Text::e(Settings::get('chat_position')).'" data-rtl="'.($rtl ? '1' : '0').'"></script>';
});

add_hook('AdminAreaFooterOutput', 1, function ($vars) {
    if (! netarz_ai_ready() || ! Settings::bool('chat_enabled') || ! Settings::bool('chat_admin_alerts') || Whmcs::adminId() <= 0) {
        return '';
    }

    Lang::use(netarz_ai_admin_lang());

    return '<script src="'.Text::e(View::asset('admin-alerts.js')).'" defer data-netarz-alerts'
        .' data-endpoint="addonmodules.php?module=netarz_ai&amp;ajax=alerts"'
        .' data-inbox="addonmodules.php?module=netarz_ai&amp;tab=inbox"'
        .' data-title="'.Text::e(Lang::get('alert_toast_title')).'" data-open="'.Text::e(Lang::get('alert_toast_open')).'"'
        .' data-rtl="'.(Lang::isRtl() ? '1' : '0').'"></script>';
});

/* --------------------------------------------------------------- tickets */

add_hook('TicketOpen', 1, function ($vars) {
    if (netarz_ai_ready()) {
        Tickets::queue((int) ($vars['ticketid'] ?? 0), 0, 'open', (int) ($vars['deptid'] ?? 0));
    }
});

add_hook('TicketUserReply', 1, function ($vars) {
    if (netarz_ai_ready()) {
        Tickets::queue((int) ($vars['ticketid'] ?? 0), (int) ($vars['replyid'] ?? 0), 'reply', (int) ($vars['deptid'] ?? 0));
    }
});

add_hook('TicketAdminReply', 1, function ($vars) {
    if (netarz_ai_ready()) {
        Tickets::staffReplied((int) ($vars['ticketid'] ?? 0));
    }
});

add_hook('AdminAreaViewTicketPage', 1, function ($vars) {
    if (! netarz_ai_ready() || Settings::get('ticket_mode') === 'off') {
        return '';
    }

    Lang::use(netarz_ai_admin_lang());
    $ticketId = (int) ($vars['ticketid'] ?? 0);
    $draft = Tickets::pendingDraft($ticketId);
    $state = Tickets::state($ticketId);

    return View::render('admin/ticket-panel', [
        'ticketId' => $ticketId,
        'draft' => $draft,
        'last' => Tickets::lastJob($ticketId),
        'paused' => $state && (int) $state->ai_paused === 1,
        'csrf' => Csrf::token(),
        'endpoint' => 'addonmodules.php?module=netarz_ai&ajax=',
    ]);
});

/* ------------------------------------------------------------------ cron */

add_hook('AfterCronJob', 1, function ($vars) {
    if (! netarz_ai_ready()) {
        return;
    }

    try {
        Tickets::processDue(20);
        Balance::alertIfLow();

        $today = date('Y-m-d', Clock::time());
        if (\NetArz\WhmcsAi\Cache::get('pruned_on') !== $today) {
            Chat::prune();
            \NetArz\WhmcsAi\Cache::put('pruned_on', $today, 172800);
        }
    } catch (\Throwable $e) {
        Whmcs::log('cron: '.$e->getMessage());
    }
});

/* --------------------------------------------------------------- widget */

add_hook('AdminHomeWidgets', 1, function () {
    if (! netarz_ai_ready() || ! class_exists('\WHMCS\Module\AbstractWidget')) {
        return null;
    }
    require_once __DIR__.'/lib/Widget/BalanceWidget.php';

    return new \NetArz\WhmcsAi\Widget\BalanceWidget();
});

function netarz_ai_admin_lang()
{
    $adminId = Whmcs::adminId();
    if ($adminId > 0) {
        try {
            $language = \WHMCS\Database\Capsule::table('tbladmins')->where('id', $adminId)->value('language');
            if ($language) {
                return (string) $language;
            }
        } catch (\Throwable $e) {
        }
    }

    return 'english';
}
