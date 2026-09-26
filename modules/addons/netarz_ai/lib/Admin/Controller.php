<?php

namespace NetArz\WhmcsAi\Admin;

use NetArz\WhmcsAi\Agent;
use NetArz\WhmcsAi\Balance;
use NetArz\WhmcsAi\Cache;
use NetArz\WhmcsAi\Chat;
use NetArz\WhmcsAi\Clock;
use NetArz\WhmcsAi\Gateway;
use NetArz\WhmcsAi\GatewayError;
use NetArz\WhmcsAi\Knowledge;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Text;
use NetArz\WhmcsAi\Tickets;
use NetArz\WhmcsAi\Usage;
use NetArz\WhmcsAi\View;
use NetArz\WhmcsAi\Whmcs;

/** The module's admin page: addonmodules.php?module=netarz_ai */
class Controller
{
    public const TABS = ['dashboard', 'inbox', 'tickets', 'knowledge', 'settings', 'logs'];

    /** @var string */
    private $link;

    public function __construct(string $moduleLink)
    {
        $this->link = $moduleLink;
    }

    public function render(): string
    {
        $tab = isset($_GET['tab']) && in_array($_GET['tab'], self::TABS, true) ? $_GET['tab'] : 'dashboard';
        $flash = [];

        if ($tab === 'settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['netarz_settings'])) {
            if (! Csrf::check((string) ($_POST['_ntz'] ?? ''))) {
                $flash = ['type' => 'error', 'text' => Lang::get('err_csrf')];
            } else {
                $errors = Settings::saveForm($_POST);
                Cache::forget('balance');
                $flash = $errors
                    ? ['type' => 'error', 'text' => implode(' ', $errors)]
                    : ['type' => 'success', 'text' => Lang::get('settings_saved')];
            }
        }

        $data = [
            'link' => $this->link,
            'tab' => $tab,
            'flash' => $flash,
            'csrf' => Csrf::token(),
            'hasKey' => Settings::apiKey() !== '',
            'waiting' => Chat::waitingCount(),
        ];

        switch ($tab) {
            case 'dashboard':
                $data['balance'] = Settings::apiKey() !== '' ? Balance::get() : null;
                $data['today'] = Usage::summary(Clock::today());
                $data['month'] = Usage::summary(date('Y-m-01 00:00:00', Clock::time()));
                $data['daily'] = Usage::daily(14);
                $data['recentJobs'] = Tickets::recent(8);
                $data['openChats'] = count(Chat::adminList('open', 200));
                break;
            case 'tickets':
                $data['jobs'] = Tickets::recent(80, isset($_GET['outcome']) ? preg_replace('/[^a-z_]/', '', (string) $_GET['outcome']) : '');
                break;
            case 'settings':
                $data['settings'] = [];
                foreach (Settings::DEFAULTS as $key => $default) {
                    $data['settings'][$key] = Settings::get($key);
                }
                $data['admins'] = Whmcs::admins();
                $data['departments'] = Whmcs::departments();
                $data['maskedKey'] = self::mask(Settings::apiKey());
                break;
            case 'knowledge':
                $data['kbCount'] = count(Knowledge::kbArticles());
                $data['annCount'] = count(Knowledge::announcements());
                $data['custom'] = (string) Settings::get('knowledge_custom');
                break;
            case 'logs':
                $data['logs'] = \WHMCS\Database\Capsule::table(Usage::TABLE)->orderByDesc('id')->limit(200)->get()->all();
                break;
        }

        return View::render('admin/layout', $data);
    }

    /** addonmodules.php?module=netarz_ai&ajax=… */
    public function ajax(string $action): array
    {
        $adminId = Whmcs::adminId();
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $in = $_POST + $_GET;

        $writes = ['chat_send', 'chat_typing', 'chat_mode', 'chat_ai', 'chat_close', 'chat_ticket', 'chat_delete', 'test', 'knowledge_preview', 'test_connection', 'ticket_draft', 'ticket_draft_mark', 'ticket_pause', 'job_retry', 'save_knowledge'];
        if (in_array($action, $writes, true) && (! $post || ! Csrf::check((string) ($_SERVER['HTTP_X_NTZ_TOKEN'] ?? ($in['_ntz'] ?? ''))))) {
            return ['ok' => false, 'error' => Lang::get('err_csrf'), '_status' => 419];
        }

        $id = (int) ($in['id'] ?? 0);

        // A draft or a test question can take a while; do not lock the admin's other tabs meanwhile.
        \NetArz\WhmcsAi\PublicApi::releaseSession();

        switch ($action) {
            case 'balance':
                return ['ok' => true, 'balance' => Balance::get(! empty($in['fresh']))];

            case 'test_connection':
                Cache::forget('balance');
                Cache::forget('pricing');
                $b = Balance::get(true);

                return ['ok' => $b['ok'], 'balance' => $b, 'error' => $b['error']];

            case 'usage':
                try {
                    $remote = (new Gateway(null, null, 20))->usage(date('Y-m-d', strtotime('-29 days')), date('Y-m-d'), (string) ($in['group'] ?? 'day') === 'model' ? 'model' : 'day');
                } catch (GatewayError $e) {
                    $remote = ['error' => $e->getMessage()];
                }

                return ['ok' => true, 'remote' => $remote, 'local' => Usage::daily(30)];

            case 'models':
                $models = Cache::remember('models_list', 21600, function () {
                    try {
                        $out = [];
                        foreach ((new Gateway(null, null, 20))->models('chat') as $m) {
                            if (! empty($m['id'])) {
                                $out[] = [
                                    'id' => (string) $m['id'], 'name' => (string) ($m['name'] ?? $m['id']),
                                    'in' => (string) ($m['pricing']['input_per_million'] ?? ''), 'out' => (string) ($m['pricing']['output_per_million'] ?? ''),
                                    'json' => ! empty($m['capabilities']['json']),
                                ];
                            }
                        }

                        return $out ?: null;
                    } catch (\Throwable $e) {
                        return null;
                    }
                });

                return ['ok' => is_array($models), 'models' => $models ?: []];

            case 'threads':
                return ['ok' => true, 'threads' => Chat::adminList((string) ($in['filter'] ?? 'open')), 'waiting' => Chat::waitingCount()];

            case 'thread':
                $thread = Chat::adminThread($id, (int) ($in['after'] ?? 0), $adminId);

                return $thread ? ['ok' => true] + $thread : ['ok' => false, 'error' => 'not_found', '_status' => 404];

            case 'chat_send':
                $message = Chat::adminSend($id, $adminId, (string) ($in['body'] ?? ''));

                return $message ? ['ok' => true, 'message' => $message] : ['ok' => false, 'error' => 'not_sent'];

            case 'chat_typing':
                Chat::adminTyping($id, $adminId);

                return ['ok' => true];

            case 'chat_mode':
                Chat::setMode($id, (string) ($in['mode'] ?? 'ai'));
                if (($in['mode'] ?? '') === 'ai') {
                    $thread = Chat::thread($id);
                    if ($thread) {
                        Chat::respond($thread); // answer whatever is still open
                    }
                }

                return ['ok' => true];

            case 'chat_ai':
                Chat::setAiEnabled($id, ! empty($in['enabled']));

                return ['ok' => true];

            case 'chat_close':
                Chat::close($id);

                return ['ok' => true];

            case 'chat_delete':
                Chat::delete($id);

                return ['ok' => true];

            case 'chat_ticket':
                $thread = Chat::thread($id);
                $result = $thread ? Chat::toTicket($thread, (int) ($in['dept'] ?? 0)) : ['ok' => false, 'error' => 'not_found'];

                return $result + ['admin_url' => isset($result['ticket_id']) ? 'supporttickets.php?action=view&id='.$result['ticket_id'] : ''];

            case 'alerts':
                return ['ok' => true] + Chat::alerts((int) ($in['since'] ?? 0));

            case 'knowledge_preview':
                $k = Knowledge::forQuestion((string) ($in['question'] ?? ''));

                return ['ok' => true, 'text' => $k['text'], 'sources' => $k['sources'], 'chars' => mb_strlen($k['text'])];

            case 'save_knowledge':
                Settings::set('knowledge_custom', Text::limit((string) ($in['knowledge_custom'] ?? ''), 60000));

                return ['ok' => true, 'message' => Lang::get('settings_saved')];

            case 'test':
                $question = Text::clean((string) ($in['question'] ?? ''));
                if ($question === '') {
                    return ['ok' => false, 'error' => Lang::get('err_empty_question')];
                }
                $channel = ($in['channel'] ?? 'chat') === 'ticket' ? 'ticket' : 'chat';
                $reply = Agent::reply($channel === 'ticket' ? 'ticket' : 'test', [['role' => 'user', 'content' => $question]], max(0, (int) ($in['client_id'] ?? 0)));

                return [
                    'ok' => $reply->ran(),
                    'action' => $reply->action, 'reply' => $reply->reply, 'confidence' => $reply->confidence,
                    'intent' => $reply->intent, 'handoff_reason' => $reply->handoffReason, 'malformed' => $reply->malformed,
                    'error' => $reply->error, 'model' => $reply->model, 'tokens' => $reply->inputTokens + $reply->outputTokens,
                    'cost' => $reply->costUsd, 'sources' => $reply->sources,
                ];

            case 'ticket_draft':
                $result = Tickets::draftNow((int) ($in['ticket_id'] ?? 0));

                return ['ok' => true, 'outcome' => $result['outcome'], 'job' => self::jobShape($result['job'])];

            case 'ticket_draft_mark':
                Tickets::markDraft($id, (string) ($in['status'] ?? ''));

                return ['ok' => true];

            case 'ticket_pause':
                Tickets::setPaused((int) ($in['ticket_id'] ?? 0), ! empty($in['paused']));

                return ['ok' => true];

            case 'job_retry':
                $job = Tickets::job($id);
                if (! $job) {
                    return ['ok' => false, 'error' => 'not_found'];
                }
                \WHMCS\Database\Capsule::table(Tickets::JOBS)->where('id', $id)->update(['status' => 'pending', 'attempts' => 0, 'due_at' => Clock::now(), 'updated_at' => Clock::now()]);

                return ['ok' => true, 'outcome' => Tickets::process($id), 'job' => self::jobShape(Tickets::job($id))];
        }

        return ['ok' => false, 'error' => 'unknown_action', '_status' => 404];
    }

    public static function jobShape($job): ?array
    {
        if (! $job) {
            return null;
        }

        return [
            'id' => (int) $job->id, 'ticket_id' => (int) $job->ticket_id, 'status' => (string) $job->status,
            'outcome' => (string) $job->outcome, 'draft' => (string) $job->draft, 'confidence' => (int) $job->confidence,
            'reason' => (string) $job->reason, 'reason_label' => Lang::reason((string) $job->reason), 'draft_status' => (string) $job->draft_status, 'error' => (string) $job->error,
            'updated_at' => (string) $job->updated_at,
        ];
    }

    private static function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }

        return substr($key, 0, 12).'••••••••'.substr($key, -4);
    }
}
