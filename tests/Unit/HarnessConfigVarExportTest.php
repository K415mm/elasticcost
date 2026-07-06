<?php

namespace Tests\Unit;

use Phpkaiharness\Http\Controllers\HarnessConfigController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression tests for HarnessConfigController::varExportPretty().
 *
 * Bug: saving a config with an empty array value (e.g. 'clients' => [])
 * produced invalid PHP `[\n,\n]` (a comma with no preceding element),
 * causing a fatal parse error the next time config/harness.php was loaded:
 * "Cannot use empty array elements in arrays".
 */
class HarnessConfigVarExportTest extends TestCase
{
    private function varExportPretty(mixed $value, int $indent = 1): string
    {
        $controller = new HarnessConfigController;
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('varExportPretty');
        $method->setAccessible(true);

        return $method->invoke($controller, $value, $indent);
    }

    public function test_empty_array_exports_as_empty_brackets(): void
    {
        $result = $this->varExportPretty([]);

        $this->assertSame('[]', $result);
    }

    public function test_empty_array_nested_in_assoc_array_does_not_produce_dangling_comma(): void
    {
        $result = $this->varExportPretty([
            'failover' => [
                'enabled' => true,
                'clients' => [],
            ],
        ]);

        // The dangling-comma bug produced a line consisting of only ",".
        $this->assertDoesNotMatchRegularExpression('/^\s*,\s*$/m', $result);
        $this->assertStringContainsString("'clients' => []", $result);
    }

    public function test_generated_php_array_is_syntactically_valid(): void
    {
        $result = $this->varExportPretty([
            'default' => ['provider' => 'qwen', 'model' => 'qwen-plus'],
            'failover' => ['enabled' => true, 'clients' => []],
            'guardrails' => ['enabled' => false, 'high_risk_tools' => []],
            'list_values' => [1, 2, 3],
        ]);

        $php = "<?php\nreturn {$result};\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'harness_cfg_test_');
        file_put_contents($tmpFile, $php);

        // php -l equivalent: attempt to include and evaluate syntax.
        $output = [];
        $returnCode = 0;
        exec('php -l '.escapeshellarg($tmpFile), $output, $returnCode);
        unlink($tmpFile);

        $this->assertSame(0, $returnCode, 'Generated PHP config must be syntactically valid: '.implode("\n", $output));
    }

    public function test_non_empty_array_still_exports_with_all_elements(): void
    {
        $result = $this->varExportPretty(['admin', 'sizing', 'read-only']);

        $this->assertStringContainsString("'admin'", $result);
        $this->assertStringContainsString("'sizing'", $result);
        $this->assertStringContainsString("'read-only'", $result);
    }

    public function test_boolean_and_scalar_values_export_correctly(): void
    {
        $this->assertSame('true', $this->varExportPretty(true));
        $this->assertSame('false', $this->varExportPretty(false));
        $this->assertSame('null', $this->varExportPretty(null));
        $this->assertSame('42', $this->varExportPretty(42));
        $this->assertSame("'hello'", $this->varExportPretty('hello'));
    }
}
