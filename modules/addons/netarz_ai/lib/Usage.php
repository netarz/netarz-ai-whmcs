<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/**
 * Every model call is written to mod_netarz_ai_log with its tokens and an
 * estimated cost (tokens × the model's public price). The daily budget is
 * checked against that log before each call; the exact figure is always the
 * one on the NetArz panel.
 */
class Usage
{
    public const TABLE = 'mod_netarz_ai_log';

    public static function record(array $row): void
    {
        $row += [
            'channel' => 'chat', 'ref_id' => 0, 'model' => '', 'request_id' => '', 'input_tokens' => 0,
            'output_tokens' => 0, 'cost_usd' => 0, 'latency_ms' => 0, 'status' => 'ok', 'action' => '',
            'confidence' => 0, 'error' => '',
        ];
        $row['error'] = Text::limit((string) $row['error'], 250);
        $row['created_at'] = Clock::now();

        try {
            Capsule::table(self::TABLE)->insert($row);
        } catch (\Throwable $e) {
            Whmcs::log('could not write usage log: '.$e->getMessage());
        }
    }

    /** Estimated USD cost of one call. */
    public static function cost(string $model, int $input, int $output, int $cached = 0): float
    {
        $price = self::pricing($model);
        if (! $price) {
            return 0.0;
        }

        $cachedRate = isset($price['cached_input_per_million']) ? (float) $price['cached_input_per_million'] : (float) $price['input_per_million'];
        $fresh = max(0, $input - $cached);

        return round(($fresh * (float) $price['input_per_million'] + $cached * $cachedRate + $output * (float) $price['output_per_million']) / 1000000, 6);
    }

    /** @return array<string, string>|null the model's public per-million prices, cached for a day. */
    public static function pricing(string $model): ?array
    {
        $all = Cache::remember('pricing', 86400, function () {
            try {
                $map = [];
                foreach ((new Gateway())->models('chat') as $m) {
                    if (isset($m['id'], $m['pricing']['input_per_million'], $m['pricing']['output_per_million'])) {
                        $map[$m['id']] = array_intersect_key($m['pricing'], array_flip(['input_per_million', 'output_per_million', 'cached_input_per_million']));
                    }
                }

                return $map ?: null;
            } catch (\Throwable $e) {
                return null;
            }
        });

        return is_array($all) && isset($all[$model]) ? $all[$model] : null;
    }

    public static function spentToday(): float
    {
        try {
            return (float) Capsule::table(self::TABLE)->where('created_at', '>=', Clock::today())->sum('cost_usd');
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    public static function overBudget(): bool
    {
        $cap = (float) Settings::get('daily_budget');

        return $cap > 0 && self::spentToday() >= $cap;
    }

    /** @return array{calls:int, cost:float, errors:int, chat:int, ticket:int, handoffs:int} */
    public static function summary(string $since): array
    {
        try {
            $row = Capsule::table(self::TABLE)->where('created_at', '>=', $since)
                ->selectRaw("COUNT(*) as calls, COALESCE(SUM(cost_usd),0) as cost, SUM(CASE WHEN status='error' THEN 1 ELSE 0 END) as errors, SUM(CASE WHEN channel='chat' THEN 1 ELSE 0 END) as chat, SUM(CASE WHEN channel='ticket' THEN 1 ELSE 0 END) as ticket, SUM(CASE WHEN action='handoff' THEN 1 ELSE 0 END) as handoffs")
                ->first();
        } catch (\Throwable $e) {
            $row = null;
        }

        return [
            'calls' => (int) ($row->calls ?? 0),
            'cost' => round((float) ($row->cost ?? 0), 4),
            'errors' => (int) ($row->errors ?? 0),
            'chat' => (int) ($row->chat ?? 0),
            'ticket' => (int) ($row->ticket ?? 0),
            'handoffs' => (int) ($row->handoffs ?? 0),
        ];
    }

    /** Local day-by-day totals for the last N days (the dashboard chart). */
    public static function daily(int $days = 14): array
    {
        $from = date('Y-m-d', strtotime('-'.($days - 1).' days'));
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $out[date('Y-m-d', strtotime($from.' +'.$i.' days'))] = ['calls' => 0, 'cost' => 0.0];
        }

        try {
            $rows = Capsule::table(self::TABLE)->where('created_at', '>=', $from.' 00:00:00')->get(['created_at', 'cost_usd']);
            foreach ($rows as $row) {
                $day = substr((string) $row->created_at, 0, 10);
                if (isset($out[$day])) {
                    $out[$day]['calls']++;
                    $out[$day]['cost'] += (float) $row->cost_usd;
                }
            }
        } catch (\Throwable $e) {
        }

        return $out;
    }
}
