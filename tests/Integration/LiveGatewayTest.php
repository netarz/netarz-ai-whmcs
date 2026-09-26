<?php

namespace NetArz\WhmcsAi\Tests\Integration;

use NetArz\WhmcsAi\Gateway;
use NetArz\WhmcsAi\GatewayError;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Talks to the real https://netarz.ir/api/ai/v1 through the module's own cURL
 * client. Excluded from `composer test`; run with `composer test:live`.
 * Set NETARZ_API_KEY to also check /me and one real (paid, tiny) completion.
 */
#[Group('live')]
final class LiveGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        Gateway::$transport = null;
    }

    public function test_the_public_model_list_is_reachable_and_has_the_default_model(): void
    {
        $models = (new Gateway('', null, 30))->models('chat');

        $ids = array_column($models, 'id');
        $this->assertGreaterThan(20, count($ids));
        $this->assertContains('gpt-4o-mini', $ids, 'the module\'s default model must exist');
        $mini = $models[array_search('gpt-4o-mini', $ids, true)];
        $this->assertArrayHasKey('input_per_million', $mini['pricing']);
        $this->assertArrayHasKey('output_per_million', $mini['pricing']);
    }

    public function test_a_wrong_key_is_refused_as_an_account_problem_not_a_blip(): void
    {
        try {
            (new Gateway('sk-ntz-v1-this-key-does-not-exist-000000', null, 30))->me();
            $this->fail('the gateway accepted a made-up key');
        } catch (GatewayError $e) {
            $this->assertSame(401, $e->status);
            $this->assertTrue($e->isAccountProblem());
            $this->assertFalse($e->isTransient());
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function test_the_module_user_agent_gets_through_the_edge(): void
    {
        // Cloudflare's bot rules refuse some server user-agents; ours must not be one of them.
        try {
            (new Gateway('sk-ntz-v1-this-key-does-not-exist-000000', null, 30))->chat(['model' => 'gpt-4o-mini', 'messages' => [['role' => 'user', 'content' => 'hi']]]);
            $this->fail('expected 401');
        } catch (GatewayError $e) {
            $this->assertSame(401, $e->status, 'a 403 here would mean the edge blocked the module');
        }
    }

    public function test_with_a_real_key_balance_and_one_completion_work(): void
    {
        $key = (string) getenv('NETARZ_API_KEY');
        if ($key === '') {
            $this->markTestSkipped('Set NETARZ_API_KEY to run the paid checks.');
        }

        $gateway = new Gateway($key, null, 60);
        $me = $gateway->me();
        $this->assertSame('account', $me['object']);

        $res = $gateway->chat([
            'model' => 'gpt-4o-mini', 'max_tokens' => 40, 'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => 'Reply with {"action":"answer","reply":"pong","confidence":99}'], ['role' => 'user', 'content' => 'ping']],
        ]);
        $this->assertNotSame('', $res['request_id']);
        $this->assertStringContainsString('pong', $res['json']['choices'][0]['message']['content']);
    }
}
