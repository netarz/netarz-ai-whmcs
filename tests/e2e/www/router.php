<?php
/**
 * PHP built-in server router playing WHMCS:
 *   /index.php?m=netarz_ai&na=…      the module's client-area JSON routes
 *   /clientarea.php, /                 a client-area page with the footer hook output
 *   /admin/addonmodules.php            the module's admin page (and its AJAX)
 *   /admin/supporttickets.php?id=      a ticket page with the AdminAreaViewTicketPage panel
 *   /admin/index.php                   the admin dashboard widget
 *   /modules/addons/netarz_ai/assets/* static files
 *   /_login?as=1  /_logout  /_admin?id=1  /_ticket  test helpers
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__, 3);

if (strpos($path, '/modules/addons/netarz_ai/assets/') === 0) {
    $file = $root.$path;
    if (! is_file($file)) {
        http_response_code(404);
        return true;
    }
    $types = ['css' => 'text/css', 'js' => 'application/javascript'];
    header('Content-Type: '.($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}

require dirname(__DIR__).'/whmcs.php';

use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;

$lang = $_GET['lang'] ?? ($_SESSION['Language'] ?? 'english');
if (isset($_GET['lang'])) {
    $_SESSION['Language'] = $lang;
}
$rtl = $lang === 'farsi';

switch ($path) {
    case '/_login':
        $_SESSION['uid'] = (int) ($_GET['as'] ?? 1);
        header('Location: /clientarea.php');
        return true;

    case '/_logout':
        unset($_SESSION['uid']);
        header('Location: /clientarea.php');
        return true;

    case '/_admin':
        $_SESSION['adminid'] = (int) ($_GET['id'] ?? 1);
        \WHMCS\Database\Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->update(['language' => $_GET['lang'] ?? 'english']);
        header('Location: /admin/addonmodules.php?module=netarz_ai');
        return true;

    case '/_ticket':
        // A customer opens a ticket through WHMCS (fires TicketOpen like the real thing).
        $id = FakeWhmcs::customerOpensTicket((int) ($_GET['client'] ?? 1), (int) ($_GET['dept'] ?? 2), (string) ($_GET['subject'] ?? 'Help'), (string) ($_GET['message'] ?? 'Hello'));
        header('Content-Type: application/json');
        echo json_encode(['id' => $id]);
        return true;

    case '/_age':
        // Pretend ten minutes have passed: every timestamp of every chat moves back.
        $back = function ($v) { return $v === null ? null : date('Y-m-d H:i:s', strtotime($v) - 600); };
        foreach (\WHMCS\Database\Capsule::table('mod_netarz_ai_messages')->get(['id', 'created_at']) as $m) {
            \WHMCS\Database\Capsule::table('mod_netarz_ai_messages')->where('id', $m->id)->update(['created_at' => $back($m->created_at)]);
        }
        foreach (\WHMCS\Database\Capsule::table('mod_netarz_ai_threads')->get(['id', 'handoff_at', 'last_message_at']) as $t) {
            \WHMCS\Database\Capsule::table('mod_netarz_ai_threads')->where('id', $t->id)->update(['handoff_at' => $back($t->handoff_at), 'last_message_at' => $back($t->last_message_at)]);
        }
        echo 'ok';
        return true;

    case '/_cron':
        run_hook('AfterCronJob', []);
        echo 'ok';
        return true;

    case '/index.php':
        if (($_GET['m'] ?? '') === 'netarz_ai') {
            netarz_ai_clientarea([]);
            return true;
        }
        // fall through to the client area page
    case '/':
    case '/clientarea.php':
        $name = isset($_SESSION['uid']) ? \WHMCS\Database\Capsule::table('tblclients')->where('id', $_SESSION['uid'])->value('firstname') : '';
        ?><!doctype html>
<html lang="<?= $rtl ? 'fa' : 'en' ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Client Area - Pars Host</title>
<style>
body{margin:0;font-family:Tahoma,system-ui,sans-serif;background:#f4f5f7;color:#222}
header{background:#1d3557;color:#fff;padding:16px 24px;display:flex;justify-content:space-between;align-items:center}
header a{color:#fff;margin-inline-start:14px}
main{max-width:980px;margin:28px auto;padding:0 16px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.box{background:#fff;border-radius:8px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.box h3{margin:0 0 8px;font-size:15px}.box p{margin:0;color:#666;font-size:13px}
@media(max-width:700px){main{grid-template-columns:1fr}}
</style></head>
<body>
<header><strong>Pars Host</strong><nav><?php if ($name): ?><span><?= htmlspecialchars($name) ?></span><a href="/_logout">Logout</a><?php else: ?><a href="/_login?as=1">Login</a><?php endif; ?></nav></header>
<main>
<div class="box"><h3><?= $rtl ? 'سرویس‌ها' : 'Services' ?></h3><p><?= $rtl ? '۱ سرویس فعال' : '1 active service' ?></p></div>
<div class="box"><h3><?= $rtl ? 'دامنه‌ها' : 'Domains' ?></h3><p>rezashop.test</p></div>
<div class="box"><h3><?= $rtl ? 'فاکتورها' : 'Invoices' ?></h3><p><?= $rtl ? '۱ فاکتور پرداخت‌نشده' : '1 unpaid invoice' ?></p></div>
</main>
<?= implode('', run_hook('ClientAreaFooterOutput', [])) ?>
</body></html>
<?php
        return true;

    case '/admin/addonmodules.php':
        ob_start(); // WHMCS renders its admin template around the module; AJAX output clears it.
        ?><!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Addons - WHMCS</title>
<style>body{margin:0;font-family:"Open Sans",Tahoma,sans-serif;background:#eef1f5}.bar{background:#2b3e50;color:#fff;padding:12px 20px}.content{padding:20px}</style></head>
<body><div class="bar">WHMCS Admin · Addons</div><div class="content">
<?php netarz_ai_output(['modulelink' => 'addonmodules.php?module=netarz_ai']); ?>
</div><?= implode('', run_hook('AdminAreaFooterOutput', [])) ?></body></html>
<?php
        ob_end_flush();
        return true;

    case '/admin/supporttickets.php':
        $id = (int) ($_GET['id'] ?? 0);
        $ticket = FakeWhmcs::api('GetTicket', ['ticketid' => $id], '');
        ?><!doctype html>
<html><head><meta charset="utf-8"><title>Ticket #<?= $id ?> - WHMCS</title>
<style>body{margin:0;font-family:Tahoma,sans-serif;background:#eef1f5}.content{max-width:900px;margin:20px auto;padding:0 16px}.msg{background:#fff;padding:14px;border-radius:6px;margin-bottom:10px}textarea{width:100%;min-height:160px}</style></head>
<body><div class="content">
<h2><?= htmlspecialchars((string) ($ticket['subject'] ?? '')) ?></h2>
<?= implode('', run_hook('AdminAreaViewTicketPage', ['ticketid' => $id])) ?>
<?php foreach ($ticket['replies']['reply'] ?? [] as $r): ?><div class="msg"><b><?= htmlspecialchars($r['admin'] ?: $r['name']) ?></b><p><?= nl2br(htmlspecialchars($r['message'])) ?></p></div><?php endforeach; ?>
<form id="frmTicketReply"><textarea id="replymessage" name="message"></textarea></form>
</div></body></html>
<?php
        return true;

    case '/admin/index.php':
        $widget = run_hook('AdminHomeWidgets', [])[0];
        ?><!doctype html><html><head><meta charset="utf-8"><title>Dashboard</title><style>body{background:#eef1f5;font-family:Tahoma,sans-serif}.panel{width:360px;margin:30px;background:#fff;border-radius:6px;padding:14px}</style></head>
<body><div class="panel"><h4>NetArz AI</h4><?= $widget->generateOutput($widget->getData()) ?></div></body></html>
<?php
        return true;
}

http_response_code(404);
echo 'not found';
return true;
