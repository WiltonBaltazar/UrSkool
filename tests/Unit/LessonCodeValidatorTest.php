<?php

namespace Tests\Unit;

use App\Support\LessonCodeValidator;
use Tests\TestCase;

class LessonCodeValidatorTest extends TestCase
{
    public function test_it_validates_selector_and_text_rules(): void
    {
        $validator = new LessonCodeValidator;

        $result = $validator->validate([
            'html' => '<section id="hero"><h1>Aprender HTML</h1></section>',
            'css' => '',
            'js' => '',
        ], [
            ['kind' => 'selector_exists', 'value' => '#hero'],
            ['kind' => 'text_includes', 'value' => 'aprender html'],
        ]);

        $this->assertTrue($result['passed']);
        $this->assertSame([], $result['failures']);
    }

    public function test_it_fails_closed_when_rules_are_missing(): void
    {
        $validator = new LessonCodeValidator;

        $result = $validator->validate([
            'html' => '<div>ok</div>',
            'css' => '',
            'js' => '',
        ], null);

        $this->assertFalse($result['passed']);
        $this->assertNotEmpty($result['failures']);
    }
}
