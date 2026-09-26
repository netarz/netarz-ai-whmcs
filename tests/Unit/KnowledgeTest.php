<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\ClientContext;
use NetArz\WhmcsAi\Knowledge;
use NetArz\WhmcsAi\Prompt;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\TestCase;

final class KnowledgeTest extends TestCase
{
    public function test_the_relevant_article_is_retrieved_with_its_link(): void
    {
        $k = Knowledge::forQuestion('Which nameservers should I use for my domain?');

        $this->assertStringContainsString('ns1.parshost.test', $k['text']);
        $this->assertStringContainsString('knowledgebase.php?action=displayarticle&id=1', $k['text']);
        $this->assertSame('How to point your domain to our nameservers', $k['sources'][0]['title']);
    }

    public function test_persian_questions_find_persian_articles(): void
    {
        $k = Knowledge::forQuestion('رمز ایميل رو چطور عوض کنم؟'); // Arabic ي on purpose
        $this->assertStringContainsString('Email Accounts', $k['text']);
    }

    public function test_a_persian_question_finds_an_english_article(): void
    {
        $k = Knowledge::forQuestion('نیم سرورهاتون چیه؟ می‌خوام دامنه‌ام رو وصل کنم');
        $this->assertSame('How to point your domain to our nameservers', $k['sources'][0]['title'] ?? null);
        $this->assertStringContainsString('ns1.parshost.test', $k['text']);

        $k = Knowledge::forQuestion('چطور گواهی اس اس ال نصب کنم؟');
        $this->assertSame('Installing an SSL certificate in cPanel', $k['sources'][0]['title'] ?? null);
    }

    public function test_other_articles_are_listed_by_title_so_the_model_can_link_them(): void
    {
        $k = Knowledge::forQuestion('something unrelated entirely');
        $this->assertStringContainsString('## Other help articles', $k['text']);
        $this->assertStringContainsString('Installing an SSL certificate in cPanel — https://my.parshost.test/knowledgebase.php?action=displayarticle&id=2', $k['text']);
        $this->assertStringNotContainsString('root password rotation', $k['text'], 'private articles are not listed either');
    }

    public function test_articles_only_in_hidden_categories_stay_out(): void
    {
        $k = Knowledge::forQuestion('reseller discount codes');
        $this->assertStringNotContainsString('RESELL50', $k['text']);
        $this->assertStringNotContainsString('reseller discount', $k['text']);
        // an article in both a hidden and a visible category is public
        $this->assertStringContainsString('Installing an SSL certificate', Knowledge::forQuestion('ssl certificate cpanel')['text']);
    }

    public function test_private_articles_and_unpublished_announcements_never_leak(): void
    {
        $k = Knowledge::forQuestion('root password rotation secret launch');

        $this->assertStringNotContainsString('hunter2', $k['text']);
        $this->assertStringNotContainsString('Secret launch', $k['text']);
    }

    public function test_products_list_prices_and_order_links_but_not_hidden_or_retired_ones(): void
    {
        $k = Knowledge::forQuestion('how much is a VPS in Frankfurt?');

        $this->assertStringContainsString('VPS › VPS Frankfurt 2GB — $12 monthly (+$10 setup)', $k['text']);
        $this->assertStringContainsString('Shared Hosting › Starter — $5 monthly, $50 annually', $k['text']);
        $this->assertStringContainsString('cart.php?a=add&pid=3', $k['text']);
        $this->assertStringNotContainsString('Legacy plan', $k['text']);
        $this->assertStringNotContainsString('Secret plan', $k['text']);
        // the product the question names comes first
        $this->assertLessThan(strpos($k['text'], 'Starter'), strpos($k['text'], 'VPS Frankfurt'));
    }

    public function test_domain_prices_only_when_the_question_is_about_domains(): void
    {
        $this->assertStringNotContainsString('Domain prices', Knowledge::forQuestion('my website is slow')['text']);

        $k = Knowledge::forQuestion('price to register mybrand.com?');
        $this->assertStringContainsString('.com: register $12.99, renew $14.99', $k['text']);
        $this->assertStringContainsString('Domain prices', Knowledge::forQuestion('قیمت دامنه چنده؟')['text']);
    }

    public function test_open_network_issues_are_known_and_resolved_ones_are_not(): void
    {
        $k = Knowledge::forQuestion('is email down?');
        $this->assertStringContainsString('Mail delays on server de2', $k['text']);
        $this->assertStringNotContainsString('Old resolved issue', $k['text']);
    }

    public function test_owner_notes_come_first_and_sources_can_be_switched_off(): void
    {
        Settings::set('knowledge_custom', 'Support hours: 9 to 17, Saturday to Wednesday.');
        Settings::set('knowledge_products', 0);
        Settings::set('knowledge_kb', 0);

        $k = Knowledge::forQuestion('nameservers and prices?');
        $this->assertStringStartsWith("## Owner's notes", $k['text']);
        $this->assertStringNotContainsString('ns1.parshost.test', $k['text']);
        $this->assertStringNotContainsString('Starter', $k['text']);
    }

    public function test_the_knowledge_block_respects_its_budget(): void
    {
        Settings::set('knowledge_custom', str_repeat('Long policy text. ', 5000));
        $k = Knowledge::forQuestion('anything', 4000);
        $this->assertLessThanOrEqual(4000, mb_strlen($k['text']));
    }

    public function test_client_context_carries_the_account_but_never_credentials(): void
    {
        $context = ClientContext::forClient(1);

        $this->assertStringContainsString('Reza Karimi', $context);
        $this->assertStringContainsString('Shared Hosting › Starter (rezashop.test) — Active', $context);
        $this->assertStringContainsString('rezashop.test — Active, expires 2026-12-01, auto-renew on', $context);
        $this->assertStringContainsString('viewinvoice.php?id=1', $context);
        $this->assertMatchesRegularExpression('/Account credit: 3\.50? USD/', $context);
        // Paid invoices and another client's invoices are not there.
        $this->assertStringNotContainsString('id=2', $context);
        $this->assertStringNotContainsString('id=3', $context);
        // Credentials, server IPs and hostnames the API returns are dropped.
        foreach (['P@ssw0rd-SECRET', 'secret-hash', '10.0.0.7', 'de2.parshost.test', 'rezashop,', 'username'] as $secret) {
            $this->assertStringNotContainsString($secret, $context, $secret);
        }
    }

    public function test_client_context_is_empty_for_guests_or_when_switched_off(): void
    {
        $this->assertSame('', ClientContext::forClient(0));
        Settings::set('knowledge_client', 0);
        $this->assertSame('', ClientContext::forClient(1));
    }

    public function test_the_prompt_carries_the_rules_the_contract_and_the_owner_voice(): void
    {
        Settings::set('instructions', 'Always mention our Telegram channel.');
        $prompt = Prompt::system('ticket', 'KB TEXT', 'CLIENT TEXT', 'fa');

        $this->assertStringContainsString('Pars Host', $prompt);
        $this->assertStringContainsString('Only state facts found in KNOWLEDGE or CUSTOMER', $prompt);
        $this->assertStringContainsString('"action":"answer|handoff|silent"', $prompt);
        $this->assertStringContainsString('«شما»', $prompt);
        $this->assertStringContainsString('written Persian', $prompt);
        $this->assertStringContainsString('Always mention our Telegram channel.', $prompt);
        $this->assertStringContainsString("# KNOWLEDGE\nKB TEXT", $prompt);
        $this->assertStringContainsString("# CUSTOMER\nCLIENT TEXT", $prompt);
        // Rules come before the owner's text, which comes before the data: owner text cannot redefine the hard rules.
        $this->assertLessThan(strpos($prompt, 'Always mention'), strpos($prompt, '# Hard rules'));
    }

    public function test_chat_prompt_asks_for_polite_spoken_persian(): void
    {
        $this->assertStringContainsString('Polite spoken Persian', Prompt::system('chat', '', '', 'fa'));
        $this->assertStringContainsString('reply in English', Prompt::system('chat', '', '', 'en'));
    }
}
