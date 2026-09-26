<?php

namespace NetArz\WhmcsAi\Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use WHMCS\Database\Capsule;

/**
 * A WHMCS database and localAPI in miniature: the tables and columns the
 * module reads, seeded with a small hosting company, and the API commands the
 * module calls — answering in WHMCS's own response shapes and firing the same
 * hooks WHMCS fires (TicketOpen, TicketAdminReply, …).
 */
final class FakeWhmcs
{
    /** @var array<int, array{command:string, params:array, admin:string}> */
    public static $calls = [];

    /** @var array<int, array> */
    public static $adminEmails = [];

    /** @var array<string, callable> override one command in a test */
    public static $overrides = [];

    public static function boot(string $database = ':memory:'): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        self::reset();
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$adminEmails = [];
        self::$overrides = [];
        $GLOBALS['__whmcs_activity'] = [];
        $GLOBALS['__whmcs_modulelog'] = [];
        $_SESSION = [];
    }

    public static function schema(): void
    {
        $s = Capsule::schema();
        foreach (['tblconfiguration', 'tbladmins', 'tblticketdepartments', 'tblknowledgebase', 'tblknowledgebasecats', 'tblknowledgebaselinks', 'tblannouncements', 'tblproducts', 'tblproductgroups', 'tblpricing', 'tblcurrencies', 'tbldomainpricing', 'tblnetworkissues', 'tbltickets', 'tblticketreplies', 'tblticketnotes', 'tblclients', 'tblhosting', 'tbldomains', 'tblinvoices'] as $t) {
            $s->dropIfExists($t);
        }

        $s->create('tblconfiguration', function (Blueprint $t) { $t->string('setting')->primary(); $t->text('value')->nullable(); });
        $s->create('tbladmins', function (Blueprint $t) {
            $t->increments('id'); $t->string('username'); $t->string('firstname'); $t->string('lastname'); $t->string('email')->default('');
            $t->string('language')->default('english'); $t->boolean('disabled')->default(false);
        });
        $s->create('tblticketdepartments', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('order')->default(0); $t->string('hidden')->default(''); });
        $s->create('tblknowledgebase', function (Blueprint $t) {
            $t->increments('id'); $t->string('title'); $t->text('article'); $t->integer('views')->default(0); $t->string('private')->nullable();
            $t->integer('parentid')->default(0); $t->string('language')->default('');
        });
        $s->create('tblknowledgebasecats', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('hidden')->default(''); });
        $s->create('tblknowledgebaselinks', function (Blueprint $t) { $t->increments('id'); $t->integer('categoryid'); $t->integer('articleid'); });
        $s->create('tblannouncements', function (Blueprint $t) {
            $t->increments('id'); $t->dateTime('date'); $t->string('title'); $t->text('announcement'); $t->integer('published')->default(1);
            $t->integer('parentid')->default(0); $t->string('language')->default('');
        });
        $s->create('tblproductgroups', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('hidden')->default(false); $t->integer('order')->default(0); });
        $s->create('tblproducts', function (Blueprint $t) {
            $t->increments('id'); $t->integer('gid'); $t->string('name'); $t->text('description')->nullable(); $t->string('paytype')->default('recurring');
            $t->boolean('hidden')->default(false); $t->boolean('retired')->default(false); $t->integer('order')->default(0);
        });
        $s->create('tblcurrencies', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('prefix')->default(''); $t->string('suffix')->default(''); $t->boolean('default')->default(false); });
        $s->create('tblpricing', function (Blueprint $t) {
            $t->increments('id'); $t->string('type'); $t->integer('currency'); $t->integer('relid');
            foreach (['msetupfee', 'qsetupfee', 'ssetupfee', 'asetupfee', 'bsetupfee', 'tsetupfee', 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'] as $c) {
                $t->decimal($c, 16, 2)->default(-1);
            }
        });
        $s->create('tbldomainpricing', function (Blueprint $t) { $t->increments('id'); $t->string('extension'); $t->integer('order')->default(0); });
        $s->create('tblnetworkissues', function (Blueprint $t) {
            $t->increments('id'); $t->string('title'); $t->text('description'); $t->string('type')->default('Server'); $t->string('status');
            $t->string('priority')->default('Medium'); $t->dateTime('startdate');
        });
        $s->create('tblclients', function (Blueprint $t) {
            $t->increments('id'); $t->string('firstname'); $t->string('lastname'); $t->string('companyname')->default(''); $t->string('email');
            $t->string('status')->default('Active'); $t->decimal('credit', 10, 2)->default(0); $t->string('password')->default('secret-hash');
        });
        $s->create('tblhosting', function (Blueprint $t) {
            $t->increments('id'); $t->integer('userid'); $t->integer('packageid'); $t->string('domain')->default(''); $t->string('domainstatus')->default('Active');
            $t->string('billingcycle')->default('Monthly'); $t->decimal('amount', 10, 2)->default(0); $t->date('nextduedate')->nullable(); $t->date('regdate')->nullable();
            $t->string('username')->default(''); $t->string('password')->default(''); $t->string('dedicatedip')->default('');
        });
        $s->create('tbldomains', function (Blueprint $t) {
            $t->increments('id'); $t->integer('userid'); $t->string('domain'); $t->string('status')->default('Active'); $t->date('expirydate')->nullable(); $t->boolean('donotrenew')->default(false);
        });
        $s->create('tblinvoices', function (Blueprint $t) {
            $t->increments('id'); $t->integer('userid'); $t->date('duedate'); $t->decimal('total', 10, 2); $t->string('status')->default('Unpaid');
        });
        $s->create('tbltickets', function (Blueprint $t) {
            $t->increments('id'); $t->string('tid'); $t->string('c'); $t->integer('did'); $t->integer('userid')->default(0); $t->string('name')->default('');
            $t->string('email')->default(''); $t->dateTime('date'); $t->string('title'); $t->text('message'); $t->string('status')->default('Open');
            $t->string('urgency')->default('Medium'); $t->string('admin')->default(''); $t->dateTime('lastreply')->nullable();
        });
        $s->create('tblticketreplies', function (Blueprint $t) {
            $t->increments('id'); $t->integer('tid'); $t->integer('userid')->default(0); $t->string('name')->default(''); $t->string('email')->default('');
            $t->dateTime('date'); $t->text('message'); $t->string('admin')->default(''); $t->text('attachment')->nullable();
        });
        $s->create('tblticketnotes', function (Blueprint $t) { $t->increments('id'); $t->integer('ticketid'); $t->string('admin'); $t->dateTime('date'); $t->text('message'); });
    }

    /** A small hosting company: two admins, three departments, KB, products, a client with services. */
    public static function seed(): void
    {
        $now = date('Y-m-d H:i:s');
        self::rows('tblconfiguration', [
            ['setting' => 'CompanyName', 'value' => 'Pars Host'],
            ['setting' => 'SystemURL', 'value' => 'https://my.parshost.test/'],
            ['setting' => 'Language', 'value' => 'english'],
        ]);
        self::rows('tbladmins', [
            ['username' => 'support-ai', 'firstname' => 'Support', 'lastname' => 'Assistant', 'language' => 'english', 'disabled' => 0],
            ['username' => 'sara', 'firstname' => 'Sara', 'lastname' => 'Ahmadi', 'language' => 'farsi', 'disabled' => 0],
            ['username' => 'old', 'firstname' => 'Old', 'lastname' => 'Admin', 'language' => 'english', 'disabled' => 1],
        ]);
        self::rows('tblticketdepartments', [
            ['name' => 'Sales', 'order' => 1], ['name' => 'Technical', 'order' => 2], ['name' => 'Billing', 'order' => 3],
        ]);
        self::rows('tblknowledgebase', [
            ['title' => 'How to point your domain to our nameservers', 'article' => '<p>Set your nameservers to <b>ns1.parshost.test</b> and <b>ns2.parshost.test</b>. DNS changes take up to 24 hours.</p>', 'views' => 90, 'private' => '', 'parentid' => 0],
            ['title' => 'Installing an SSL certificate in cPanel', 'article' => '<p>Open cPanel, then SSL/TLS Status, then Run AutoSSL. Free Let\'s Encrypt SSL is included with every hosting plan.</p>', 'views' => 60, 'private' => '', 'parentid' => 0],
            ['title' => 'Internal: root password rotation', 'article' => 'Rotate root passwords every Monday. Current root: hunter2', 'views' => 5, 'private' => 'on', 'parentid' => 0],
            ['title' => 'چطور رمز ایمیل را عوض کنم', 'article' => 'از سی‌پنل (cPanel) وارد بخش Email Accounts شوید و روی Manage بزنید.', 'views' => 40, 'private' => '', 'parentid' => 0],
        ]);
        self::rows('tblknowledgebase', [
            ['title' => 'Staff only: reseller discount codes', 'article' => 'Use code RESELL50 for half price.', 'views' => 1, 'private' => '', 'parentid' => 0],
        ]);
        self::rows('tblknowledgebasecats', [['name' => 'Guides'], ['name' => 'Internal', 'hidden' => 'on']]);
        self::rows('tblknowledgebaselinks', [
            ['categoryid' => 1, 'articleid' => 1], ['categoryid' => 1, 'articleid' => 2], ['categoryid' => 1, 'articleid' => 4],
            ['categoryid' => 2, 'articleid' => 5], ['categoryid' => 2, 'articleid' => 2],
        ]);
        self::rows('tblannouncements', [
            ['date' => $now, 'title' => 'New Germany data centre', 'announcement' => 'Our new Frankfurt location is live for VPS orders.', 'published' => 1, 'parentid' => 0],
            ['date' => $now, 'title' => 'Draft announcement', 'announcement' => 'Secret launch next month.', 'published' => 0, 'parentid' => 0],
        ]);
        self::rows('tblcurrencies', [['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'default' => 1], ['code' => 'IRT', 'prefix' => '', 'suffix' => ' Toman', 'default' => 0]]);
        self::rows('tblproductgroups', [['name' => 'Shared Hosting', 'order' => 1], ['name' => 'VPS', 'order' => 2], ['name' => 'Hidden group', 'hidden' => 1, 'order' => 3]]);
        self::rows('tblproducts', [
            ['gid' => 1, 'name' => 'Starter', 'description' => '5 GB NVMe, 1 website, free SSL', 'order' => 1],
            ['gid' => 1, 'name' => 'Business', 'description' => '50 GB NVMe, unlimited websites', 'order' => 2],
            ['gid' => 2, 'name' => 'VPS Frankfurt 2GB', 'description' => '2 vCPU, 2 GB RAM', 'order' => 1],
            ['gid' => 1, 'name' => 'Legacy plan', 'description' => 'old', 'retired' => 1, 'order' => 9],
            ['gid' => 3, 'name' => 'Secret plan', 'description' => 'hidden', 'order' => 1],
        ]);
        self::rows('tblpricing', [
            ['type' => 'product', 'currency' => 1, 'relid' => 1, 'monthly' => 5, 'annually' => 50, 'msetupfee' => 0, 'asetupfee' => 0],
            ['type' => 'product', 'currency' => 1, 'relid' => 2, 'monthly' => 15, 'annually' => 150, 'msetupfee' => 0, 'asetupfee' => 0],
            ['type' => 'product', 'currency' => 1, 'relid' => 3, 'monthly' => 12, 'msetupfee' => 10],
        ]);
        self::rows('tbldomainpricing', [['extension' => '.com', 'order' => 1], ['extension' => '.ir', 'order' => 2]]);
        self::rows('tblpricing', [
            ['type' => 'domainregister', 'currency' => 1, 'relid' => 1, 'msetupfee' => 12.99],
            ['type' => 'domainrenew', 'currency' => 1, 'relid' => 1, 'msetupfee' => 14.99],
            ['type' => 'domainregister', 'currency' => 1, 'relid' => 2, 'msetupfee' => 3],
        ]);
        self::rows('tblnetworkissues', [
            ['title' => 'Mail delays on server de2', 'description' => 'Outgoing mail is queued; fix in progress.', 'status' => 'Investigating', 'startdate' => $now],
            ['title' => 'Old resolved issue', 'description' => 'done', 'status' => 'Resolved', 'startdate' => $now],
        ]);
        self::rows('tblclients', [
            ['firstname' => 'Reza', 'lastname' => 'Karimi', 'email' => 'reza@example.com', 'credit' => 3.5],
            ['firstname' => 'Mina', 'lastname' => 'Rahimi', 'email' => 'mina@example.com'],
        ]);
        self::rows('tblhosting', [
            ['userid' => 1, 'packageid' => 1, 'domain' => 'rezashop.test', 'amount' => 5, 'nextduedate' => '2026-10-10', 'regdate' => '2025-10-10', 'username' => 'rezashop', 'password' => 'P@ssw0rd-SECRET', 'dedicatedip' => '10.0.0.7'],
        ]);
        self::rows('tbldomains', [['userid' => 1, 'domain' => 'rezashop.test', 'expirydate' => '2026-12-01', 'donotrenew' => 0]]);
        self::rows('tblinvoices', [
            ['userid' => 1, 'duedate' => '2026-10-10', 'total' => 5, 'status' => 'Unpaid'],
            ['userid' => 1, 'duedate' => '2026-09-10', 'total' => 5, 'status' => 'Paid'],
            ['userid' => 2, 'duedate' => '2026-10-01', 'total' => 99, 'status' => 'Unpaid'],
        ]);
    }

    private static function rows(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            Capsule::table($table)->insert($row);
        }
    }

    /** Open a ticket the way a customer would, firing TicketOpen. */
    public static function customerOpensTicket(int $clientId, int $deptId, string $subject, string $message): int
    {
        $result = self::api('OpenTicket', ['clientid' => $clientId, 'deptid' => $deptId, 'subject' => $subject, 'message' => $message], '');

        return (int) $result['id'];
    }

    /** A customer reply, firing TicketUserReply. */
    public static function customerReplies(int $ticketId, string $message): int
    {
        $ticket = Capsule::table('tbltickets')->find($ticketId);
        $id = Capsule::table('tblticketreplies')->insertGetId(['tid' => $ticketId, 'userid' => $ticket->userid, 'date' => date('Y-m-d H:i:s'), 'message' => $message]);
        Capsule::table('tbltickets')->where('id', $ticketId)->update(['status' => 'Customer-Reply', 'lastreply' => date('Y-m-d H:i:s')]);
        \run_hook('TicketUserReply', ['ticketid' => $ticketId, 'replyid' => $id, 'userid' => $ticket->userid, 'deptid' => $ticket->did, 'message' => $message, 'subject' => $ticket->title, 'status' => 'Customer-Reply']);

        return (int) $id;
    }

    public static function api(string $command, array $params, string $admin): array
    {
        self::$calls[] = ['command' => $command, 'params' => $params, 'admin' => $admin];
        if (isset(self::$overrides[$command])) {
            return (self::$overrides[$command])($params, $admin);
        }

        switch ($command) {
            case 'GetClientsDetails':
                $c = Capsule::table('tblclients')->find((int) $params['clientid']);
                if (! $c) {
                    return ['result' => 'error', 'message' => 'Client Not Found'];
                }
                $client = ['userid' => $c->id, 'id' => $c->id, 'firstname' => $c->firstname, 'lastname' => $c->lastname, 'companyname' => $c->companyname, 'email' => $c->email, 'status' => $c->status, 'credit' => $c->credit, 'currency_code' => 'USD', 'password' => $c->password];

                return ['result' => 'success', 'client' => $client] + $client;

            case 'GetClientsProducts':
                $rows = Capsule::table('tblhosting as h')->join('tblproducts as p', 'p.id', '=', 'h.packageid')->join('tblproductgroups as g', 'g.id', '=', 'p.gid')
                    ->where('h.userid', (int) $params['clientid'])->get(['h.*', 'p.name', 'g.name as groupname']);
                $list = [];
                foreach ($rows as $r) {
                    $list[] = ['id' => $r->id, 'name' => $r->name, 'groupname' => $r->groupname, 'domain' => $r->domain, 'status' => $r->domainstatus, 'billingcycle' => $r->billingcycle, 'recurringamount' => $r->amount, 'nextduedate' => $r->nextduedate, 'regdate' => $r->regdate, 'username' => $r->username, 'password' => $r->password, 'dedicatedip' => $r->dedicatedip, 'serverhostname' => 'de2.parshost.test'];
                }

                return ['result' => 'success', 'totalresults' => count($list), 'products' => ['product' => $list]];

            case 'GetClientsDomains':
                $list = array_map(function ($d) {
                    return ['id' => $d->id, 'domainname' => $d->domain, 'status' => $d->status, 'expirydate' => $d->expirydate, 'donotrenew' => (int) $d->donotrenew];
                }, Capsule::table('tbldomains')->where('userid', (int) $params['clientid'])->get()->all());

                return ['result' => 'success', 'totalresults' => count($list), 'domains' => ['domain' => $list]];

            case 'GetInvoices':
                $q = Capsule::table('tblinvoices')->where('userid', (int) $params['userid']);
                if (! empty($params['status'])) {
                    $q->where('status', $params['status']);
                }
                $list = array_map(function ($i) {
                    return ['id' => $i->id, 'duedate' => $i->duedate, 'total' => $i->total, 'status' => $i->status, 'currencycode' => 'USD'];
                }, $q->get()->all());

                return ['result' => 'success', 'totalresults' => count($list), 'invoices' => ['invoice' => $list]];

            case 'OpenTicket':
                $client = ! empty($params['clientid']) ? Capsule::table('tblclients')->find((int) $params['clientid']) : null;
                $tid = (string) random_int(100000, 999999);
                $id = Capsule::table('tbltickets')->insertGetId([
                    'tid' => $tid, 'c' => substr(md5($tid), 0, 8), 'did' => (int) $params['deptid'], 'userid' => $client ? $client->id : 0,
                    'name' => $client ? $client->firstname.' '.$client->lastname : (string) ($params['name'] ?? ''), 'email' => $client ? $client->email : (string) ($params['email'] ?? ''),
                    'date' => date('Y-m-d H:i:s'), 'title' => (string) $params['subject'], 'message' => (string) $params['message'], 'urgency' => (string) ($params['priority'] ?? 'Medium'),
                ]);
                $dept = Capsule::table('tblticketdepartments')->find((int) $params['deptid']);
                \run_hook('TicketOpen', ['ticketid' => $id, 'ticketmask' => $tid, 'userid' => $client ? $client->id : 0, 'deptid' => (int) $params['deptid'], 'deptname' => $dept ? $dept->name : '', 'subject' => $params['subject'], 'message' => $params['message'], 'priority' => $params['priority'] ?? 'Medium']);

                return ['result' => 'success', 'id' => $id, 'tid' => $tid, 'c' => substr(md5($tid), 0, 8)];

            case 'GetTicket':
                $t = Capsule::table('tbltickets')->find((int) $params['ticketid']);
                if (! $t) {
                    return ['result' => 'error', 'message' => 'Ticket ID Not Found'];
                }
                $dept = Capsule::table('tblticketdepartments')->find($t->did);
                $replies = [['replyid' => '0', 'userid' => (string) $t->userid, 'name' => $t->name, 'email' => $t->email, 'date' => $t->date, 'message' => $t->message, 'attachment' => '', 'admin' => '']];
                foreach (Capsule::table('tblticketreplies')->where('tid', $t->id)->orderBy('id')->get() as $r) {
                    $replies[] = ['replyid' => (string) $r->id, 'userid' => (string) $r->userid, 'name' => $r->name, 'email' => $r->email, 'date' => $r->date, 'message' => $r->message, 'attachment' => (string) $r->attachment, 'admin' => $r->admin];
                }
                $notes = array_map(function ($n) {
                    return ['noteid' => $n->id, 'date' => $n->date, 'message' => $n->message, 'admin' => $n->admin];
                }, Capsule::table('tblticketnotes')->where('ticketid', $t->id)->get()->all());

                return [
                    'result' => 'success', 'ticketid' => (string) $t->id, 'tid' => $t->tid, 'c' => $t->c, 'deptid' => (string) $t->did, 'deptname' => $dept ? $dept->name : '',
                    'userid' => (string) $t->userid, 'name' => $t->name, 'email' => $t->email, 'date' => $t->date, 'subject' => $t->title, 'status' => $t->status,
                    'priority' => $t->urgency, 'admin' => $t->admin, 'replies' => ['reply' => $replies], 'notes' => ['note' => $notes],
                ];

            case 'AddTicketReply':
                $t = Capsule::table('tbltickets')->find((int) $params['ticketid']);
                if (! $t) {
                    return ['result' => 'error', 'message' => 'Ticket ID Not Found'];
                }
                $adminRow = ! empty($params['adminusername']) ? Capsule::table('tbladmins')->where('username', $params['adminusername'])->first() : null;
                $adminName = $adminRow ? $adminRow->firstname.' '.$adminRow->lastname : (string) ($params['adminusername'] ?? '');
                $rid = Capsule::table('tblticketreplies')->insertGetId(['tid' => $t->id, 'userid' => $adminName !== '' ? 0 : $t->userid, 'date' => date('Y-m-d H:i:s'), 'message' => (string) $params['message'], 'admin' => $adminName]);
                Capsule::table('tbltickets')->where('id', $t->id)->update(['status' => $params['status'] ?? ($adminName !== '' ? 'Answered' : 'Customer-Reply')]);
                if ($adminName !== '') {
                    \run_hook('TicketAdminReply', ['ticketid' => $t->id, 'replyid' => $rid, 'admin' => $params['adminusername'], 'deptid' => $t->did, 'message' => $params['message'], 'status' => 'Answered']);
                }

                return ['result' => 'success'];

            case 'AddTicketNote':
                Capsule::table('tblticketnotes')->insert(['ticketid' => (int) $params['ticketid'], 'admin' => $admin, 'date' => date('Y-m-d H:i:s'), 'message' => (string) $params['message']]);

                return ['result' => 'success'];

            case 'SendAdminEmail':
                self::$adminEmails[] = $params;

                return ['result' => 'success'];
        }

        return ['result' => 'error', 'message' => 'Command not faked: '.$command];
    }

    /** A staff member replies from the admin area (fires TicketAdminReply). */
    public static function staffReplies(int $ticketId, string $username, string $message): void
    {
        self::api('AddTicketReply', ['ticketid' => $ticketId, 'message' => $message, 'adminusername' => $username], $username);
    }

    /** @return array<int, array> calls of one command */
    public static function callsOf(string $command): array
    {
        return array_values(array_filter(self::$calls, function ($c) use ($command) {
            return $c['command'] === $command;
        }));
    }
}
