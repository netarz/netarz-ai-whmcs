<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\AgentReply;
use NetArz\WhmcsAi\Text;
use PHPUnit\Framework\TestCase;

final class TextAndReplyTest extends TestCase
{
    public function test_persian_is_normalised_for_matching(): void
    {
        $this->assertSame('کیفیت سی پنل 123', Text::normalize('كيفيت سی‌پنل ۱۲۳'));
        $this->assertSame('اسلام', Text::normalize('إِسلام'));
    }

    public function test_terms_drop_stop_words_and_keep_domains(): void
    {
        $terms = Text::terms('سلام، قیمت هاست برای example.com چقدر است؟');
        $this->assertContains('قیمت', $terms);
        $this->assertContains('هاست', $terms);
        $this->assertContains('example.com', $terms);
        $this->assertNotContains('سلام', $terms);
        $this->assertNotContains('است', $terms);
    }

    public function test_limit_never_cuts_a_persian_letter_in_half(): void
    {
        $text = str_repeat('ی', 50);
        $cut = Text::limit($text, 10);
        $this->assertTrue(mb_check_encoding($cut, 'UTF-8'));
        $this->assertSame(10, mb_strlen($cut));
    }

    public function test_clean_strips_controls_and_bidi_overrides_but_keeps_zwnj(): void
    {
        $this->assertSame("می‌خواهم\nتست", Text::clean("\x07می‌خواهم\u{202E}\nتست "));
    }

    public function test_plain_turns_html_into_text(): void
    {
        $this->assertSame("Hello\nWorld & co", Text::plain('<p>Hello</p><p>World &amp; co</p>'));
    }

    public function test_rtl_detection(): void
    {
        $this->assertTrue(Text::isRtl('سلام، DNS دامنه‌ام کار نمی‌کند'));
        $this->assertFalse(Text::isRtl('My DNS is broken'));
    }

    public function test_a_valid_answer_parses(): void
    {
        $r = AgentReply::parse('{"action":"answer","reply":"**Starter** costs $5 a month.","confidence":93,"intent":"sales","handoff_reason":""}', 'chat');
        $this->assertTrue($r->answered());
        $this->assertSame('Starter costs $5 a month.', $r->reply, 'markdown bold is removed');
        $this->assertSame(93, $r->confidence);
        $this->assertSame('sales', $r->intent);
    }

    public function test_json_inside_fences_or_chatter_still_parses(): void
    {
        $r = AgentReply::parse("Sure!\n```json\n{\"action\":\"answer\",\"reply\":\"Hi\",\"confidence\":80}\n```", 'chat');
        $this->assertTrue($r->answered());

        $r = AgentReply::parse('Here you go: {"action":"handoff","reply":"A colleague will reply.","confidence":40,"handoff_reason":"refund"} thanks', 'chat');
        $this->assertTrue($r->isHandoff());
        $this->assertSame('refund', $r->handoffReason);
    }

    public function test_prose_is_never_shown_to_the_customer(): void
    {
        $r = AgentReply::parse('I think the answer is yes.', 'chat');
        $this->assertTrue($r->malformed);
        $this->assertTrue($r->isHandoff());
        $this->assertSame('', $r->reply);
        $this->assertSame('I think the answer is yes.', $r->error, 'kept for staff eyes only');
    }

    public function test_an_empty_answer_becomes_a_handoff(): void
    {
        $r = AgentReply::parse('{"action":"answer","reply":"  ","confidence":99}', 'chat');
        $this->assertTrue($r->isHandoff());
        $this->assertTrue($r->malformed);
    }

    public function test_silence_is_allowed_in_chat_but_not_on_a_ticket(): void
    {
        $this->assertSame('silent', AgentReply::parse('{"action":"silent","reply":"ignored","confidence":90}', 'chat')->action);
        $this->assertSame('', AgentReply::parse('{"action":"silent","reply":"ignored","confidence":90}', 'chat')->reply);
        $this->assertSame('handoff', AgentReply::parse('{"action":"silent","reply":"","confidence":90}', 'ticket')->action);
    }

    public function test_unknown_actions_and_wild_confidence_are_contained(): void
    {
        $this->assertTrue(AgentReply::parse('{"action":"refund_now","reply":"done"}', 'chat')->malformed);
        $this->assertSame(100, AgentReply::parse('{"action":"answer","reply":"x","confidence":500}', 'chat')->confidence);
        $this->assertSame(0, AgentReply::parse('{"action":"answer","reply":"x","confidence":-3}', 'chat')->confidence);
    }
}
