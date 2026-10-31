<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\ClientContext;
use NetArz\WhmcsAi\Knowledge;
use NetArz\WhmcsAi\Prompt;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\TestCase;
use WHMCS\Database\Capsule;

/**
 * 1.1.0: what the assistant may sell. Reported by a customer (2026-10-31): with
 * WHMCS stock control on, a sold-out product was still offered with its order link.
 */
final class CatalogueTest extends TestCase
{
    public function test_a_sold_out_product_is_marked_and_has_no_order_link(): void
    {
        $k = Knowledge::forQuestion('Do you have a VPS in Amsterdam?')['text'];

        $this->assertStringContainsString('VPS › VPS Amsterdam 4GB — OUT OF STOCK', $k);
        $this->assertStringNotContainsString('cart.php?a=add&pid=6', $k, 'no order link for a product with no stock');
        $this->assertStringNotContainsString('$20 monthly', $k, 'no price either, so it is not quoted as buyable');
        // in-stock products keep their link
        $this->assertStringContainsString('cart.php?a=add&pid=3', $k);
    }

    public function test_the_owner_can_leave_sold_out_products_out_entirely(): void
    {
        Settings::set('knowledge_stock', 'hide');

        $k = Knowledge::forQuestion('VPS Amsterdam')['text'];
        $this->assertStringNotContainsString('Amsterdam', $k);
        $this->assertStringContainsString('VPS Frankfurt 2GB', $k);
    }

    public function test_low_stock_is_mentioned_and_stock_without_control_is_unlimited(): void
    {
        $k = Knowledge::forQuestion('pro hosting plan')['text'];

        $this->assertStringContainsString('Shared Hosting › Pro — $25 monthly — only 3 left', $k);
        $this->assertStringNotContainsString('Starter — $5 monthly, $50 annually — only', $k, 'stock control off means no count');
    }

    public function test_restocking_brings_the_order_link_back(): void
    {
        Capsule::table('tblproducts')->where('id', 6)->update(['qty' => 4]);

        $k = Knowledge::forQuestion('VPS Amsterdam')['text'];
        $this->assertStringNotContainsString('OUT OF STOCK', $k);
        $this->assertStringContainsString('VPS Amsterdam 4GB — $20 monthly — only 4 left', $k);
        $this->assertStringContainsString('cart.php?a=add&pid=6', $k);
    }

    public function test_stock_control_stored_the_old_way_is_understood(): void
    {
        $this->assertSame(0, Knowledge::stockLeft((object) ['stockcontrol' => 'on', 'qty' => '0']));
        $this->assertSame(2, Knowledge::stockLeft((object) ['stockcontrol' => '1', 'qty' => 2]));
        $this->assertNull(Knowledge::stockLeft((object) ['stockcontrol' => '', 'qty' => 0]));
        $this->assertSame(0, Knowledge::stockLeft((object) ['stockcontrol' => 1, 'qty' => -3]), 'oversold counts as none left');
    }

    public function test_excluded_products_never_appear(): void
    {
        $this->assertSame([], Settings::saveForm(['knowledge_exclude' => '3, 7; x 3']));
        $this->assertSame('3,7', Settings::get('knowledge_exclude'));
        $this->assertSame([3, 7], Settings::excludedProducts());
        Settings::set('knowledge_products', 1); // the real form sends every switch; this one sent a single field

        $k = Knowledge::forQuestion('VPS Frankfurt pro plan')['text'];
        $this->assertStringNotContainsString('Frankfurt', $k);
        $this->assertStringNotContainsString('› Pro', $k);
        $this->assertStringContainsString('Starter', $k);
    }

    public function test_addons_are_listed_only_with_products_that_can_be_sold(): void
    {
        $k = Knowledge::forQuestion('can I add backups?')['text'];

        $this->assertStringContainsString('## Product add-ons', $k);
        $this->assertStringContainsString('- Daily backup — $2 monthly | with: Starter, Business', $k);
        $this->assertStringNotContainsString('Extra IPv4', $k, 'its only product is sold out');
        $this->assertStringNotContainsString('Old addon', $k, 'retired');

        Settings::set('knowledge_addons', 0);
        $this->assertStringNotContainsString('Daily backup', Knowledge::forQuestion('backups')['text']);
    }

    public function test_a_client_is_quoted_in_their_own_currency(): void
    {
        $guest = Knowledge::forQuestion('starter plan price')['text'];
        $this->assertStringContainsString('Starter — $5 monthly', $guest);

        $mina = Knowledge::forQuestion('starter plan price', Knowledge::BUDGET, 2)['text'];
        $this->assertStringContainsString('Starter — 500,000 Toman monthly', $mina);
        $this->assertStringNotContainsString('Starter — $5', $mina);
    }

    public function test_the_prompt_forbids_selling_what_is_not_in_stock(): void
    {
        $system = Prompt::system('chat', 'k', '', 'en');

        $this->assertStringContainsString('11. Sell only what can be ordered now.', $system);
        $this->assertStringContainsString('Never promise when it will be back', $system);
    }

    public function test_pending_orders_reach_the_client_context(): void
    {
        $context = ClientContext::forClient(1);

        $this->assertStringContainsString('Pending orders (placed, not yet accepted by staff):', $context);
        $this->assertMatchesRegularExpression('/- order #1 — placed 2026-10-30, 25(\.00)? USD, invoice paid/', $context);
        $this->assertStringNotContainsString('order #2', $context, 'an accepted order is not pending');
        $this->assertStringNotContainsString('203.0.113.9', $context, 'the order IP is never copied');
    }
}
