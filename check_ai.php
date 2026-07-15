<?php

use App\Models\GlobalSetting;
use App\Services\AiConfigHelper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

echo "=== Qwen Config ===\n";
echo 'API Key: '.(GlobalSetting::getValue('qwen_api_key', '') ? 'SET (len='.strlen(GlobalSetting::getValue('qwen_api_key', '')).')' : 'NOT SET')."\n";
echo 'Model: '.GlobalSetting::getValue('qwen_model', 'qwen-plus')."\n";
echo 'URL: '.GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1')."\n";

$apiKey = GlobalSetting::getValue('qwen_api_key', '');
$url = GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
$url = rtrim($url, '/');

echo "\n=== Testing Qwen API ===\n";
try {
    $headers = [];
    if (! empty($apiKey)) {
        $headers['Authorization'] = 'Bearer '.$apiKey;
    }
    $response = Http::withHeaders($headers)->timeout(10)->get($url.'/models');
    echo 'Status: '.$response->status()."\n";
    echo 'Body: '.substr($response->body(), 0, 1000)."\n";
} catch (Throwable $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n=== Env Fallback Keys ===\n";
echo 'PHPKAIHARNESS_QWEN_KEY: '.(env('PHPKAIHARNESS_QWEN_KEY') ? 'SET' : 'NOT SET')."\n";
echo 'QWEN_API_KEY: '.(env('QWEN_API_KEY') ? 'SET' : 'NOT SET')."\n";
echo 'DASHSCOPE_API_KEY: '.(env('DASHSCOPE_API_KEY') ? 'SET' : 'NOT SET')."\n";

echo "\n=== Test AiConfigHelper::configure() ===\n";
$config = AiConfigHelper::configure();
echo 'Provider: '.(is_object($config['provider']) ? $config['provider']->value : (string) $config['provider'])."\n";
echo 'Model: '.$config['model']."\n";
