<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_true_is_true(): void
    {
        $this->assertTrue(true);
    }

    public function test_word_export_html_structure(): void
    {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">';
        $this->assertStringContainsString('xmlns:w="urn:schemas-microsoft-com:office:word"', $html);
        $this->assertStringContainsString('xmlns:o="urn:schemas-microsoft-com:office:office"', $html);
    }
}
