<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/**
 * What the agent may know about this WHMCS: the owner's own notes, the public
 * knowledgebase, announcements, the product catalogue with prices, domain
 * prices and open network issues. It is retrieved per question, so a large
 * knowledgebase never ends up in one prompt.
 *
 * Only public data is read here. Anything about one customer comes from
 * ClientContext, and only for that customer.
 */
class Knowledge
{
    /** Hard ceiling on the knowledge block, in characters. */
    public const BUDGET = 14000;

    /**
     * @return array{text: string, sources: array<int, array{type:string, title:string, url:string}>}
     */
    public static function forQuestion(string $question, int $budget = self::BUDGET): array
    {
        $terms = Text::searchTerms($question);
        $blocks = [];
        $sources = [];

        $custom = trim((string) Settings::get('knowledge_custom'));
        if ($custom !== '') {
            $blocks[] = "## Owner's notes (highest priority)\n".Text::limit($custom, (int) ($budget * 0.45));
        }

        $remaining = $budget - (int) array_sum(array_map('mb_strlen', $blocks));

        $candidates = [];
        if (Settings::bool('knowledge_kb')) {
            $candidates = array_merge($candidates, self::kbArticles());
        }
        if (Settings::bool('knowledge_announcements')) {
            $candidates = array_merge($candidates, self::announcements());
        }

        $ranked = self::rank($candidates, $terms);
        $docs = [];
        foreach (array_slice($ranked, 0, 5) as $doc) {
            $chunk = '### '.$doc['title'].($doc['url'] !== '' ? ' ('.$doc['url'].')' : '')."\n".Text::limit($doc['body'], 1800);
            if (mb_strlen($chunk) > $remaining - 200) {
                break;
            }
            $docs[] = $chunk;
            $remaining -= mb_strlen($chunk);
            $sources[] = ['type' => $doc['type'], 'title' => $doc['title'], 'url' => $doc['url']];
        }
        if ($docs) {
            $blocks[] = "## Help articles and announcements\n".implode("\n\n", $docs);
        }

        // Titles of the other articles: cheap, and lets the model point to the right page
        // even when the question and the article share no words.
        $index = [];
        $used = array_column($sources, 'url');
        foreach ($candidates as $doc) {
            if ($doc['type'] === 'kb' && ! in_array($doc['url'], $used, true) && count($index) < 20) {
                $index[] = '- '.$doc['title'].' — '.$doc['url'];
            }
        }
        if ($index) {
            $blocks[] = "## Other help articles (titles only; send the link if one fits)\n".implode("\n", $index);
        }

        if (Settings::bool('knowledge_network')) {
            $network = self::networkIssues();
            if ($network !== '') {
                $blocks[] = "## Open network / server issues\n".$network;
            }
        }

        if (Settings::bool('knowledge_products')) {
            $products = self::products($terms, max(1500, (int) ($remaining * 0.6)));
            if ($products !== '') {
                $blocks[] = "## Products and prices (from the order form)\n".$products;
                $remaining -= mb_strlen($products);
            }
        }

        if (Settings::bool('knowledge_domains')) {
            $domains = self::domainPrices($terms, $question);
            if ($domains !== '') {
                $blocks[] = "## Domain prices (1 year)\n".$domains;
            }
        }

        $blocks[] = "## Useful links\n".self::links();

        return ['text' => Text::limit(implode("\n\n", $blocks), $budget), 'sources' => $sources];
    }

    /**
     * Keyword score: title hits count three times, body hits once, capped per
     * term so one repeated word cannot drown out the rest.
     *
     * @param array<int, array{type:string,title:string,body:string,url:string}> $docs
     * @param string[] $terms
     */
    public static function rank(array $docs, array $terms): array
    {
        if (! $terms) {
            return [];
        }

        $scored = [];
        foreach ($docs as $doc) {
            $title = Text::normalize($doc['title']);
            $body = Text::normalize($doc['body']);
            $score = 0;
            foreach ($terms as $term) {
                $score += 3 * min(2, substr_count($title, $term));
                $score += min(4, substr_count($body, $term));
            }
            if ($score > 0) {
                $doc['score'] = $score;
                $scored[] = $doc;
            }
        }

        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $scored;
    }

    /** @return array<int, array{type:string,title:string,body:string,url:string}> */
    public static function kbArticles(): array
    {
        try {
            $hidden = self::articlesInHiddenCategories();
            $rows = Capsule::table('tblknowledgebase')
                ->where('parentid', 0)
                ->when($hidden, function ($q) use ($hidden) {
                    $q->whereNotIn('id', $hidden);
                })
                ->where(function ($q) {
                    $q->whereNull('private')->orWhere('private', '')->orWhere('private', '0');
                })
                ->orderByDesc('views')
                ->limit(400)
                ->get(['id', 'title', 'article']);
        } catch (\Throwable $e) {
            return [];
        }

        $base = Whmcs::systemUrl();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'type' => 'kb',
                'title' => (string) $row->title,
                'body' => Text::plain((string) $row->article),
                'url' => $base.'knowledgebase.php?action=displayarticle&id='.(int) $row->id,
            ];
        }

        return $out;
    }

    /**
     * Articles that sit only in hidden categories are not public either.
     *
     * @return int[]
     */
    private static function articlesInHiddenCategories(): array
    {
        try {
            if (! Capsule::schema()->hasTable('tblknowledgebaselinks') || ! Capsule::schema()->hasTable('tblknowledgebasecats')) {
                return [];
            }
            $hiddenCats = Capsule::table('tblknowledgebasecats')->whereIn('hidden', ['on', '1', 1])->pluck('id')->all();
            if (! $hiddenCats) {
                return [];
            }
            $inHidden = Capsule::table('tblknowledgebaselinks')->whereIn('categoryid', $hiddenCats)->pluck('articleid')->all();
            $inVisible = Capsule::table('tblknowledgebaselinks')->whereNotIn('categoryid', $hiddenCats)->pluck('articleid')->all();

            return array_values(array_diff(array_map('intval', $inHidden), array_map('intval', $inVisible)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<int, array{type:string,title:string,body:string,url:string}> */
    public static function announcements(): array
    {
        try {
            $rows = Capsule::table('tblannouncements')
                ->where('parentid', 0)
                ->whereIn('published', [1, '1', 'on'])
                ->orderByDesc('date')
                ->limit(60)
                ->get(['id', 'date', 'title', 'announcement']);
        } catch (\Throwable $e) {
            return [];
        }

        $base = Whmcs::systemUrl();
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'type' => 'announcement',
                'title' => (string) $row->title.' — '.substr((string) $row->date, 0, 10),
                'body' => Text::plain((string) $row->announcement),
                'url' => $base.'announcements.php?id='.(int) $row->id,
            ];
        }

        return $out;
    }

    public static function networkIssues(): string
    {
        try {
            $rows = Capsule::table('tblnetworkissues')
                ->where('status', '!=', 'Resolved')
                ->orderByDesc('startdate')
                ->limit(10)
                ->get(['title', 'description', 'status', 'type', 'startdate', 'priority']);
        } catch (\Throwable $e) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = '- '.$row->title.' ['.$row->status.', '.$row->type.', since '.$row->startdate.']: '.Text::limit(Text::plain((string) $row->description), 400);
        }

        return implode("\n", $lines);
    }

    /**
     * Visible, non-retired products with their cheapest listed cycle in the
     * default currency. Products whose name or group matches the question come
     * first; the rest fill the budget.
     *
     * @param string[] $terms
     */
    public static function products(array $terms, int $budget): string
    {
        try {
            $currency = Capsule::table('tblcurrencies')->where('default', 1)->first(['id', 'code', 'prefix', 'suffix']);
            if (! $currency) {
                $currency = Capsule::table('tblcurrencies')->orderBy('id')->first(['id', 'code', 'prefix', 'suffix']);
            }

            $query = Capsule::table('tblproducts as p')
                ->leftJoin('tblproductgroups as g', 'g.id', '=', 'p.gid')
                ->where('p.hidden', 0)
                ->where(function ($q) {
                    $q->whereNull('g.hidden')->orWhere('g.hidden', 0);
                });
            if (Capsule::schema()->hasColumn('tblproducts', 'retired')) {
                $query->where('p.retired', 0);
            }
            $rows = $query->orderBy('g.order')->orderBy('p.order')->limit(300)
                ->get(['p.id', 'p.name', 'p.description', 'p.paytype', 'g.name as group_name']);

            $prices = [];
            if ($currency) {
                foreach (Capsule::table('tblpricing')->where('type', 'product')->where('currency', $currency->id)->get() as $price) {
                    $prices[(int) $price->relid] = $price;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        $base = Whmcs::systemUrl();
        $matched = [];
        $rest = [];
        foreach ($rows as $row) {
            $line = '- '.$row->group_name.' › '.$row->name;
            $price = self::priceLine($row->paytype, $prices[(int) $row->id] ?? null, $currency);
            if ($price !== '') {
                $line .= ' — '.$price;
            }
            $description = Text::limit(Text::plain((string) $row->description), 220);
            if ($description !== '') {
                $line .= ' | '.str_replace("\n", ' ', $description);
            }
            $line .= ' | order: '.$base.'cart.php?a=add&pid='.(int) $row->id;

            $haystack = Text::normalize($row->name.' '.$row->group_name);
            $hit = false;
            foreach ($terms as $term) {
                if (strpos($haystack, $term) !== false) {
                    $hit = true;
                    break;
                }
            }
            if ($hit) {
                $matched[] = $line;
            } else {
                $rest[] = $line;
            }
        }

        $out = '';
        foreach (array_merge($matched, $rest) as $line) {
            if (mb_strlen($out) + mb_strlen($line) > $budget) {
                break;
            }
            $out .= $line."\n";
        }

        return trim($out);
    }

    private static function priceLine(string $paytype, $price, $currency): string
    {
        if ($paytype === 'free') {
            return 'free';
        }
        if (! $price || ! $currency) {
            return '';
        }

        $cycles = $paytype === 'onetime'
            ? ['monthly' => 'one-time']
            : ['monthly' => 'monthly', 'quarterly' => 'quarterly', 'semiannually' => 'semi-annually', 'annually' => 'annually', 'biennially' => 'biennially', 'triennially' => 'triennially'];

        $parts = [];
        foreach ($cycles as $column => $label) {
            $amount = (float) $price->{$column};
            if ($amount < 0) {
                continue;
            }
            $setupColumn = substr($column, 0, 1).'setupfee';
            $setup = isset($price->{$setupColumn}) ? (float) $price->{$setupColumn} : 0.0;
            $parts[] = self::money($amount, $currency).' '.$label.($setup > 0 ? ' (+'.self::money($setup, $currency).' setup)' : '');
        }

        return implode(', ', $parts);
    }

    /**
     * Domain prices, only when the question is about domains — a list of sixty
     * extensions would otherwise crowd out everything else.
     *
     * @param string[] $terms
     */
    public static function domainPrices(array $terms, string $question = ''): string
    {
        $words = ['domain', 'domains', 'tld', 'whois', 'dns', 'دامنه', 'دامین', 'دومین', 'پسوند'];
        $asksAboutDomains = (bool) array_intersect($terms, $words)
            || preg_match('/(^|[\s(])\.[a-z]{2,12}\b/i', $question)
            || preg_match('/\b[a-z0-9-]+\.[a-z]{2,12}\b/i', $question);
        if (! $asksAboutDomains) {
            return '';
        }

        try {
            $currency = Capsule::table('tblcurrencies')->where('default', 1)->first(['id', 'code', 'prefix', 'suffix']);
            $rows = Capsule::table('tbldomainpricing as d')
                ->join('tblpricing as r', function ($j) use ($currency) {
                    $j->on('r.relid', '=', 'd.id')->where('r.type', '=', 'domainregister')->where('r.currency', '=', $currency ? $currency->id : 1);
                })
                ->leftJoin('tblpricing as n', function ($j) use ($currency) {
                    $j->on('n.relid', '=', 'd.id')->where('n.type', '=', 'domainrenew')->where('n.currency', '=', $currency ? $currency->id : 1);
                })
                ->orderBy('d.order')
                ->limit(60)
                ->get(['d.extension', 'r.msetupfee as register', 'n.msetupfee as renew']);
        } catch (\Throwable $e) {
            return '';
        }

        $lines = [];
        foreach ($rows as $row) {
            if ((float) $row->register < 0) {
                continue;
            }
            $line = '- '.$row->extension.': register '.self::money((float) $row->register, $currency);
            if ($row->renew !== null && (float) $row->renew >= 0) {
                $line .= ', renew '.self::money((float) $row->renew, $currency);
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public static function links(): string
    {
        $base = Whmcs::systemUrl();

        return implode("\n", [
            '- Client area: '.$base.'clientarea.php',
            '- Open a ticket: '.$base.'submitticket.php',
            '- My tickets: '.$base.'supporttickets.php',
            '- Unpaid invoices: '.$base.'clientarea.php?action=invoices',
            '- My services: '.$base.'clientarea.php?action=services',
            '- My domains: '.$base.'clientarea.php?action=domains',
            '- Order / store: '.$base.'cart.php',
            '- Domain search: '.$base.'cart.php?a=add&domain=register',
            '- Knowledgebase: '.$base.'knowledgebase.php',
            '- Network status: '.$base.'serverstatus.php',
            '- Reset password: '.$base.'password/reset',
        ]);
    }

    private static function money(float $amount, $currency): string
    {
        if (! $currency) {
            return number_format($amount, 2);
        }
        $decimals = floor($amount) == $amount ? 0 : 2;

        return trim($currency->prefix.number_format($amount, $decimals).$currency->suffix);
    }
}
