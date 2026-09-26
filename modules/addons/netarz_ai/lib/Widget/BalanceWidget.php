<?php

namespace NetArz\WhmcsAi\Widget;

use NetArz\WhmcsAi\Balance;
use NetArz\WhmcsAi\Chat;
use NetArz\WhmcsAi\Clock;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Usage;
use NetArz\WhmcsAi\View;

/** Admin dashboard widget: NetArz AI credit, today's spend, chats waiting for a person. */
class BalanceWidget extends \WHMCS\Module\AbstractWidget
{
    protected $title = 'NetArz AI';

    protected $description = 'NetArz AI credit, spend and live chats';

    protected $weight = 60;

    protected $columns = 1;

    protected $cache = false;

    protected $requiredPermission = '';

    public function getData()
    {
        return [
            'hasKey' => Settings::apiKey() !== '',
            'balance' => Settings::apiKey() !== '' ? Balance::get() : null,
            'today' => Usage::summary(Clock::today()),
            'waiting' => Chat::waitingCount(),
            'budget' => (float) Settings::get('daily_budget'),
        ];
    }

    public function generateOutput($data)
    {
        Lang::use(netarz_ai_admin_lang());

        return View::render('admin/widget', $data);
    }
}
