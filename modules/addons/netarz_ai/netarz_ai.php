<?php
/**
 * NetArz AI for WHMCS — AI live chat, AI ticket replies and your NetArz AI
 * credit, inside WHMCS.
 *
 * @see https://github.com/netarz/netarz-ai-whmcs
 * @license MIT
 */

use NetArz\WhmcsAi\Admin\Controller;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\PublicApi;
use NetArz\WhmcsAi\Schema;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Whmcs;

if (! defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

require_once __DIR__.'/autoload.php';

function netarz_ai_config()
{
    return [
        'name' => 'NetArz AI',
        'description' => 'AI live chat and AI ticket replies for WHMCS, powered by the NetArz AI API (GPT, Claude, Gemini, DeepSeek). Shows your NetArz AI credit on the dashboard. | چت آنلاین و پاسخ تیکت با هوش مصنوعی، با اعتبار وب‌سرویس هوش مصنوعی نِت اَرز.',
        'author' => '<a href="https://netarz.ir/ai-api?utm_source=whmcs-plugin&amp;utm_medium=admin&amp;utm_campaign=netarz-ai-whmcs&amp;utm_content=addon-list" target="_blank" rel="noopener">NetArz</a>',
        'language' => 'english',
        'version' => Schema::VERSION,
        'fields' => [
            'note' => [
                'FriendlyName' => 'Setup',
                'Type' => '',
                'Description' => 'Open Addons → NetArz AI → Settings to enter your API key. Get a key at <a href="https://netarz.ir/ai?utm_source=whmcs-plugin&amp;utm_medium=admin&amp;utm_campaign=netarz-ai-whmcs&amp;utm_content=addon-config" target="_blank" rel="noopener">netarz.ir/ai</a>.',
            ],
        ],
    ];
}

function netarz_ai_activate()
{
    try {
        Schema::install();
        if (Settings::get('ticket_admin') === '') {
            $admins = array_keys(Whmcs::admins());
            if ($admins) {
                Settings::set('ticket_admin', $admins[0]);
            }
        }
        if (Lang::resolve(Whmcs::config('Language', 'english')) === 'farsi') {
            Settings::set('language', 'auto');
        }

        return ['status' => 'success', 'description' => 'NetArz AI is active. Open Addons → NetArz AI → Settings and paste your NetArz API key.'];
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Could not create the NetArz AI tables: '.$e->getMessage()];
    }
}

function netarz_ai_deactivate()
{
    // Conversations, drafts and settings are kept on purpose: deactivating to
    // update or troubleshoot must not wipe the owner's chat history. The
    // "Remove all data" button in Settings drops the tables.
    return ['status' => 'success', 'description' => 'NetArz AI is deactivated. Your settings and conversations are kept.'];
}

function netarz_ai_upgrade($vars)
{
    Schema::upgrade((string) ($vars['version'] ?? ''));
}

function netarz_ai_output($vars)
{
    Lang::use(netarz_ai_admin_language($vars));
    $controller = new Controller((string) ($vars['modulelink'] ?? 'addonmodules.php?module=netarz_ai'));

    if (isset($_GET['ajax'])) {
        PublicApi::emit($controller->ajax(preg_replace('/[^a-z_]/', '', (string) $_GET['ajax'])));
        exit;
    }

    if (isset($_POST['netarz_purge']) && \NetArz\WhmcsAi\Admin\Csrf::check((string) ($_POST['_ntz'] ?? '')) && ($_POST['confirm'] ?? '') === 'DELETE') {
        Schema::uninstall();
        Schema::install();
    }

    echo $controller->render();
}

/** Client area: index.php?m=netarz_ai&na=<action> answers the chat widget. */
function netarz_ai_clientarea($vars)
{
    $action = isset($_GET['na']) ? preg_replace('/[^a-z]/', '', (string) $_GET['na']) : '';
    if ($action !== '') {
        PublicApi::emit(PublicApi::handle($action));
        exit;
    }

    // A shareable "talk to us" link: the client area with the chat already open.
    header('Location: '.Whmcs::systemUrl().'clientarea.php#netarz-chat', true, 302);
    exit;
}

function netarz_ai_admin_language($vars)
{
    if (! empty($vars['_lang']['_language'])) {
        return (string) $vars['_lang']['_language'];
    }
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
