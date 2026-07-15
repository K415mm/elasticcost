<?php

use App\Ai\Agents\SizingRegulator;
use App\Models\GlobalSetting;
use App\Services\AiConfigHelper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apiKey = GlobalSetting::getValue('qwen_api_key', '');
$url = rtrim(GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/');
$model = GlobalSetting::getValue('qwen_model', 'qwen-plus');

echo "=== Simple chat completion test ===\n";
echo "URL: $url/chat/completions\n";
echo "Model: $model\n";

try {
    $response = Http::withHeaders([
        'Authorization' => 'Bearer '.$apiKey,
        'Content-Type' => 'application/json',
    ])->timeout(30)->post($url.'/chat/completions', [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'Say hello in one word.'],
        ],
        'max_tokens' => 10,
    ]);
    echo 'Status: '.$response->status()."\n";
    echo 'Body: '.substr($response->body(), 0, 2000)."\n";
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n=== Test with SizingRegulator via AI SDK ===\n";
try {
    $aiConfig = AiConfigHelper::configure();
    echo 'Provider: '.(is_object($aiConfig['provider']) ? $aiConfig['provider']->value : (string) $aiConfig['provider'])."\n";
    echo 'Model: '.$aiConfig['model']."\n";

    $response = (new SizingRegulator)->prompt('Say hello', provider: $aiConfig['provider'], model: $aiConfig['model'], timeout: 30);
    echo 'Response text: '.substr($response->text, 0, 500)."\n";
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
}
