<?php

namespace Tests\Feature;

use App\Models\GlobalSetting;
use App\Models\User;
use App\Services\AiConfigHelper;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Enums\Lab;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->ceo()->create();
        $this->actingAs($user);
    }

    public function test_system_settings_dashboard_renders_successfully(): void
    {
        $response = $this->get(route('settings.system'));
        $response->assertStatus(200);
        $response->assertSee('Exchange Rates');
        $response->assertSee('Translation Manager');
    }

    public function test_system_settings_update_exchange_rates(): void
    {
        $postData = [
            'usd_to_eur_rate' => 0.9543,
            'usd_to_tnd_rate' => 3.2512,
        ];

        $response = $this->post(route('settings.system.update'), $postData);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        $this->assertEquals('0.9543', GlobalSetting::getValue('usd_to_eur_rate'));
        $this->assertEquals('3.2512', GlobalSetting::getValue('usd_to_tnd_rate'));
    }

    public function test_system_settings_update_translations_overrides(): void
    {
        // 1. Create translation override
        $postData = [
            'translations' => [
                'close' => [
                    'en' => 'Exit System',
                    'fr' => 'Sortie',
                    'ar' => 'خروج نظام',
                ],
            ],
        ];

        $response = $this->post(route('settings.system.translations.update'), $postData);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        // Assert database has translation overrides
        $this->assertDatabaseHas('translation_overrides', [
            'locale' => 'en',
            'key' => 'messages.close',
            'value' => 'Exit System',
        ]);
        $this->assertDatabaseHas('translation_overrides', [
            'locale' => 'fr',
            'key' => 'messages.close',
            'value' => 'Sortie',
        ]);
        $this->assertDatabaseHas('translation_overrides', [
            'locale' => 'ar',
            'key' => 'messages.close',
            'value' => 'خروج نظام',
        ]);

        // 2. Verify that translator resolves overrides dynamically
        App::setLocale('en');
        $this->assertEquals('Exit System', __('messages.close'));

        App::setLocale('fr');
        $this->assertEquals('Sortie', __('messages.close'));

        App::setLocale('ar');
        $this->assertEquals('خروج نظام', __('messages.close'));

        // 3. Clear translation override by posting empty string
        $clearData = [
            'translations' => [
                'close' => [
                    'en' => '',
                    'fr' => '',
                    'ar' => '',
                ],
            ],
        ];

        $response = $this->post(route('settings.system.translations.update'), $clearData);
        $response->assertRedirect(route('settings.system'));

        // Assert DB is cleaned up
        $this->assertDatabaseMissing('translation_overrides', [
            'key' => 'messages.close',
        ]);

        // Translator should fall back to default files
        App::setLocale('en');
        $this->assertEquals('Close', __('messages.close'));
    }

    public function test_system_settings_update_ai_provider(): void
    {
        $postData = [
            'ai_provider' => 'gemini',
            'gemini_model' => 'gemini-1.5-pro',
            'gemini_api_key' => 'test-api-key-12345',
        ];

        $response = $this->post(route('settings.system.ai.update'), $postData);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        $this->assertEquals('gemini', GlobalSetting::getValue('ai_provider'));
        $this->assertEquals('gemini-1.5-pro', GlobalSetting::getValue('gemini_model'));
        $this->assertEquals('test-api-key-12345', GlobalSetting::getValue('gemini_api_key'));

        // Test configuration helper resolution
        $aiConfig = AiConfigHelper::configure();
        $this->assertEquals(Lab::Gemini, $aiConfig['provider']);
        $this->assertEquals('gemini-1.5-pro', $aiConfig['model']);
        $this->assertEquals('test-api-key-12345', config('ai.providers.gemini.key'));
    }

    public function test_custom_http_factory_bypasses_ssl_verification_in_local_and_testing_environments(): void
    {
        // Under testing environment, verify should be set to false
        $request = Http::createPendingRequest();
        $ref = new \ReflectionClass($request);
        $prop = $ref->getProperty('options');
        $prop->setAccessible(true);
        $options = $prop->getValue($request);
        $this->assertFalse($options['verify'] ?? true);

        // Temporarily change environment to production
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        $prodRequest = Http::createPendingRequest();
        $prodRef = new \ReflectionClass($prodRequest);
        $prodProp = $prodRef->getProperty('options');
        $prodProp->setAccessible(true);
        $prodOptions = $prodProp->getValue($prodRequest);

        // Under production environment, verify should not be explicitly set to false (uses default Guzzle behavior)
        $this->assertTrue(($prodOptions['verify'] ?? true) !== false);

        // Restore original env
        $this->app['env'] = $originalEnv;
    }

    public function test_ollama_ping_endpoint_for_gemini_diagnostics(): void
    {
        // Configure Gemini provider settings in database
        GlobalSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => 'gemini']);
        GlobalSetting::updateOrCreate(['key' => 'gemini_api_key'], ['value' => 'mocked-key']);
        GlobalSetting::updateOrCreate(['key' => 'gemini_model'], ['value' => 'gemini-3.5-flash']);

        // Mock Http requests to Gemini API
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-3.5-flash', 'displayName' => 'Gemini 3.5 Flash'],
                    ['name' => 'models/gemini-1.5-pro', 'displayName' => 'Gemini 1.5 Pro'],
                ],
            ], 200),
        ]);

        $response = $this->get(route('ollama.ping'));
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'provider' => 'gemini',
            'provider_name' => 'Gemini',
            'status' => 'ok',
            'url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'target_model' => 'gemini-3.5-flash',
        ]);

        $response->assertJsonStructure([
            'available_models',
            'message',
            'model_status',
        ]);
    }

    public function test_system_settings_update_ai_provider_openrouter(): void
    {
        $postData = [
            'ai_provider' => 'openrouter',
            'openrouter_model' => 'meta-llama/llama-3-8b-instruct:free',
            'openrouter_api_key' => 'test-openrouter-key-12345',
        ];

        $response = $this->post(route('settings.system.ai.update'), $postData);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        $this->assertEquals('openrouter', GlobalSetting::getValue('ai_provider'));
        $this->assertEquals('meta-llama/llama-3-8b-instruct:free', GlobalSetting::getValue('openrouter_model'));
        $this->assertEquals('test-openrouter-key-12345', GlobalSetting::getValue('openrouter_api_key'));

        // Test configuration helper resolution
        $aiConfig = AiConfigHelper::configure();
        $this->assertEquals(Lab::OpenRouter, $aiConfig['provider']);
        $this->assertEquals('meta-llama/llama-3-8b-instruct:free', $aiConfig['model']);
        $this->assertEquals('test-openrouter-key-12345', config('ai.providers.openrouter.key'));
    }

    public function test_ollama_ping_endpoint_for_openrouter_diagnostics(): void
    {
        GlobalSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => 'openrouter']);
        GlobalSetting::updateOrCreate(['key' => 'openrouter_api_key'], ['value' => 'mocked-openrouter-key']);
        GlobalSetting::updateOrCreate(['key' => 'openrouter_model'], ['value' => 'meta-llama/llama-3-8b-instruct:free']);

        Http::fake([
            'https://openrouter.ai/api/v1/models*' => Http::response([
                'data' => [
                    ['id' => 'meta-llama/llama-3-8b-instruct:free'],
                    ['id' => 'google/gemini-2.5-flash'],
                ],
            ], 200),
        ]);

        $response = $this->get(route('ollama.ping'));
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'provider' => 'openrouter',
            'provider_name' => 'OpenRouter',
            'status' => 'ok',
            'url' => 'https://openrouter.ai/api/v1/models',
            'target_model' => 'meta-llama/llama-3-8b-instruct:free',
        ]);
    }

    public function test_system_settings_update_ai_provider_qwen(): void
    {
        $postData = [
            'ai_provider' => 'qwen',
            'qwen_model' => 'qwen-plus',
            'qwen_light_model' => 'qwen-turbo',
            'qwen_url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
            'qwen_api_key' => 'test-qwen-key-12345',
        ];

        $response = $this->post(route('settings.system.ai.update'), $postData);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        $this->assertEquals('qwen', GlobalSetting::getValue('ai_provider'));
        $this->assertEquals('qwen-plus', GlobalSetting::getValue('qwen_model'));
        $this->assertEquals('test-qwen-key-12345', GlobalSetting::getValue('qwen_api_key'));

        // Test configuration helper resolution
        $aiConfig = AiConfigHelper::configure();
        $this->assertEquals('qwen', $aiConfig['provider']);
        $this->assertEquals('qwen-plus', $aiConfig['model']);
        $this->assertEquals('openai', config('ai.providers.qwen.driver'));
        $this->assertEquals('test-qwen-key-12345', config('ai.providers.qwen.key'));
    }

    public function test_ollama_ping_endpoint_for_qwen_diagnostics(): void
    {
        GlobalSetting::updateOrCreate(['key' => 'ai_provider'], ['value' => 'qwen']);
        GlobalSetting::updateOrCreate(['key' => 'qwen_api_key'], ['value' => 'mocked-qwen-key']);
        GlobalSetting::updateOrCreate(['key' => 'qwen_model'], ['value' => 'qwen-plus']);
        GlobalSetting::updateOrCreate(['key' => 'qwen_url'], ['value' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1']);

        Http::fake([
            'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/models*' => Http::response([
                'data' => [
                    ['id' => 'qwen-plus'],
                    ['id' => 'qwen-turbo'],
                ],
            ], 200),
        ]);

        $response = $this->get(route('ollama.ping'));
        $response->assertStatus(200);

        $response->assertJsonFragment([
            'provider' => 'qwen',
            'provider_name' => 'Qwen Cloud',
            'status' => 'ok',
            'url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
            'target_model' => 'qwen-plus',
        ]);
    }

    public function test_system_settings_ai_multi_agent_configuration(): void
    {
        // 1. If multi-agent is disabled, the light model is optional (can be null/empty)
        $postDataDisabled = [
            'ai_provider' => 'ollama',
            'ollama_model' => 'gemma4:e2b',
            'ollama_url' => 'http://localhost:11434',
            'ai_multi_agent_enabled' => '0',
            'ollama_light_model' => '', // Empty
        ];

        $response = $this->post(route('settings.system.ai.update'), $postDataDisabled);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        $this->assertEquals('0', GlobalSetting::getValue('ai_multi_agent_enabled'));

        // Configuration helper should resolve light configuration to match main configuration
        $config = AiConfigHelper::configureMultiModel();
        $this->assertEquals($config['main']['model'], $config['light']['model']);

        // 2. If multi-agent is enabled, light model is mandatory
        $postDataEnabledFail = [
            'ai_provider' => 'ollama',
            'ollama_model' => 'gemma4:e2b',
            'ollama_url' => 'http://localhost:11434',
            'ai_multi_agent_enabled' => '1',
            'ollama_light_model' => '', // Empty, should fail validation!
        ];

        $response = $this->post(route('settings.system.ai.update'), $postDataEnabledFail);
        $response->assertSessionHasErrors(['ollama_light_model']);

        // 3. If multi-agent is enabled and light model is provided, it should pass validation and save
        $postDataEnabledSuccess = [
            'ai_provider' => 'ollama',
            'ollama_model' => 'gemma4:e2b',
            'ollama_url' => 'http://localhost:11434',
            'ai_multi_agent_enabled' => '1',
            'ollama_light_model' => 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf',
        ];

        $response = $this->post(route('settings.system.ai.update'), $postDataEnabledSuccess);
        $response->assertRedirect(route('settings.system'));
        $response->assertSessionHas('success');

        $this->assertEquals('1', GlobalSetting::getValue('ai_multi_agent_enabled'));
        $this->assertEquals('gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf', GlobalSetting::getValue('ollama_light_model'));

        // Configuration helper should resolve light model to the configured light model
        $config = AiConfigHelper::configureMultiModel();
        $this->assertEquals('gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf', $config['light']['model']);
    }
}
