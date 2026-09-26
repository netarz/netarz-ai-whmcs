<?php

namespace NetArz\WhmcsAi;

use Illuminate\Database\Schema\Blueprint;
use WHMCS\Database\Capsule;

/** Creates, upgrades and drops the module's tables. Every table is prefixed mod_netarz_ai_. */
class Schema
{
    public const VERSION = '1.0.2';

    public const TABLES = [
        'mod_netarz_ai_settings',
        'mod_netarz_ai_threads',
        'mod_netarz_ai_messages',
        'mod_netarz_ai_ticket_jobs',
        'mod_netarz_ai_ticket_state',
        'mod_netarz_ai_log',
    ];

    public static function install(): void
    {
        $schema = Capsule::schema();

        if (! $schema->hasTable('mod_netarz_ai_settings')) {
            $schema->create('mod_netarz_ai_settings', function (Blueprint $t) {
                $t->string('key', 64)->primary();
                $t->longText('value')->nullable();
            });
        }

        if (! $schema->hasTable('mod_netarz_ai_threads')) {
            $schema->create('mod_netarz_ai_threads', function (Blueprint $t) {
                $t->increments('id');
                $t->char('token_hash', 64)->unique();
                $t->unsignedInteger('client_id')->nullable()->index();
                $t->string('name', 120)->default('');
                $t->string('email', 190)->default('');
                $t->string('status', 16)->default('open')->index();      // open | closed
                $t->string('mode', 16)->default('ai');                   // ai | human | waiting
                $t->boolean('ai_enabled')->default(true);
                $t->string('handoff_reason', 255)->default('');
                $t->timestamp('handoff_at')->nullable();
                $t->unsignedInteger('admin_id')->nullable();
                $t->unsignedInteger('ticket_id')->nullable();
                $t->unsignedInteger('unread_admin')->default(0);
                $t->unsignedInteger('last_seen_by_visitor')->default(0);  // message id
                $t->unsignedInteger('last_seen_by_admin')->default(0);    // message id
                $t->timestamp('reply_lock_until')->nullable();
                $t->timestamp('admin_typing_until')->nullable();
                $t->string('page_url', 500)->default('');
                $t->string('ip', 45)->default('')->index();
                $t->string('user_agent', 255)->default('');
                $t->string('language', 8)->default('');
                $t->timestamp('last_visitor_at')->nullable();
                $t->timestamp('last_message_at')->nullable()->index();
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('mod_netarz_ai_messages')) {
            $schema->create('mod_netarz_ai_messages', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('thread_id')->index();
                $t->string('sender', 16);                                  // visitor | ai | admin | system
                $t->unsignedInteger('admin_id')->nullable();
                $t->text('body');
                $t->text('meta')->nullable();
                $t->timestamp('created_at')->nullable()->index();
            });
        }

        if (! $schema->hasTable('mod_netarz_ai_ticket_jobs')) {
            $schema->create('mod_netarz_ai_ticket_jobs', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('ticket_id')->index();
                $t->unsignedInteger('reply_id')->default(0);
                $t->string('event', 16);                                   // open | reply | manual
                $t->string('status', 16)->default('pending')->index();     // pending | processing | done | skipped | failed
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->timestamp('due_at')->nullable()->index();
                $t->timestamp('claimed_at')->nullable();
                $t->string('outcome', 32)->default('');                    // replied | drafted | handoff | …
                $t->text('draft')->nullable();
                $t->unsignedTinyInteger('confidence')->default(0);
                $t->string('reason', 255)->default('');
                $t->string('draft_status', 16)->default('');               // '' | pending | used | dismissed
                $t->text('error')->nullable();
                $t->timestamps();
                $t->unique(['ticket_id', 'reply_id', 'event'], 'netarz_ai_job_unique');
            });
        }

        if (! $schema->hasTable('mod_netarz_ai_ticket_state')) {
            $schema->create('mod_netarz_ai_ticket_state', function (Blueprint $t) {
                $t->unsignedInteger('ticket_id')->primary();
                $t->boolean('ai_paused')->default(false);
                $t->boolean('human_replied')->default(false);
                $t->unsignedInteger('ai_replies')->default(0);
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('mod_netarz_ai_log')) {
            $schema->create('mod_netarz_ai_log', function (Blueprint $t) {
                $t->increments('id');
                $t->string('channel', 16)->index();                        // chat | ticket | test
                $t->unsignedInteger('ref_id')->default(0);
                $t->string('model', 120)->default('');
                $t->string('request_id', 64)->default('');
                $t->unsignedInteger('input_tokens')->default(0);
                $t->unsignedInteger('output_tokens')->default(0);
                $t->decimal('cost_usd', 12, 6)->default(0);
                $t->unsignedInteger('latency_ms')->default(0);
                $t->string('status', 16)->default('ok');                   // ok | error
                $t->string('action', 16)->default('');
                $t->unsignedTinyInteger('confidence')->default(0);
                $t->string('error', 255)->default('');
                $t->timestamp('created_at')->nullable()->index();
            });
        }

        Settings::flush();
        Settings::set('db_version', self::VERSION);
    }

    /** Runs from _upgrade(); additive only, never drops data. */
    public static function upgrade(string $from): void
    {
        self::install();
    }

    public static function uninstall(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Capsule::schema()->dropIfExists($table);
        }
    }
}
