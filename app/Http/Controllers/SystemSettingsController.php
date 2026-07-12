<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use App\Models\TranslationOverride;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Phpkaiharness\Support\HarnessConfig;

class SystemSettingsController extends Controller
{
    /**
     * Display the system configuration dashboard.
     */
    public function index(): View
    {
        // Load exchange rates
        $eurRate = (float) GlobalSetting::getValue('usd_to_eur_rate', 0.92);
        $tndRate = (float) GlobalSetting::getValue('usd_to_tnd_rate', 3.10);

        // Load baseline English keys
        $enPath = lang_path('en/messages.php');
        $translationKeys = file_exists($enPath) ? include $enPath : [];

        // Load French and Arabic baseline dictionaries to check defaults
        $frPath = lang_path('fr/messages.php');
        $frDefaults = file_exists($frPath) ? include $frPath : [];

        $arPath = lang_path('ar/messages.php');
        $arDefaults = file_exists($arPath) ? include $arPath : [];

        // Fetch DB overrides and group by key
        $dbOverrides = TranslationOverride::all()->groupBy('key');

        // Load AI Settings
        $aiProvider = GlobalSetting::getValue('ai_provider', 'ollama');
        $aiMultiAgentEnabled = (bool) GlobalSetting::getValue('ai_multi_agent_enabled', false);
        $ollamaModel = GlobalSetting::getValue('ollama_model', 'gemma4:e2b');
        $ollamaLightModel = GlobalSetting::getValue('ollama_light_model', 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf');
        $ollamaUrl = GlobalSetting::getValue('ollama_url', 'http://localhost:11434');
        $lmstudioModel = GlobalSetting::getValue('lmstudio_model', 'qwen2.5-coder-7b-instruct');
        $lmstudioLightModel = GlobalSetting::getValue('lmstudio_light_model', 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf');
        $lmstudioUrl = GlobalSetting::getValue('lmstudio_url', 'http://localhost:1234/v1');
        $geminiModel = GlobalSetting::getValue('gemini_model', 'gemini-1.5-flash');
        $geminiLightModel = GlobalSetting::getValue('gemini_light_model', 'gemini-1.5-flash');
        $geminiApiKey = GlobalSetting::getValue('gemini_api_key', '');
        $openrouterModel = GlobalSetting::getValue('openrouter_model', 'meta-llama/llama-3-8b-instruct:free');
        $openrouterLightModel = GlobalSetting::getValue('openrouter_light_model', 'meta-llama/llama-3-8b-instruct:free');
        $openrouterApiKey = GlobalSetting::getValue('openrouter_api_key', '');
        $qwenModel = GlobalSetting::getValue('qwen_model', 'qwen-plus');
        $qwenLightModel = GlobalSetting::getValue('qwen_light_model', 'qwen-turbo');
        $qwenUrl = GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
        $qwenApiKey = GlobalSetting::getValue('qwen_api_key', '');

        return view('settings.system', compact(
            'eurRate',
            'tndRate',
            'translationKeys',
            'frDefaults',
            'arDefaults',
            'dbOverrides',
            'aiProvider',
            'aiMultiAgentEnabled',
            'ollamaModel',
            'ollamaLightModel',
            'ollamaUrl',
            'lmstudioModel',
            'lmstudioLightModel',
            'lmstudioUrl',
            'geminiModel',
            'geminiLightModel',
            'geminiApiKey',
            'openrouterModel',
            'openrouterLightModel',
            'openrouterApiKey',
            'qwenModel',
            'qwenLightModel',
            'qwenUrl',
            'qwenApiKey'
        ));
    }

    /**
     * Update global settings (exchange rates).
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'usd_to_eur_rate' => 'required|numeric|min:0.01',
            'usd_to_tnd_rate' => 'required|numeric|min:0.01',
        ]);

        GlobalSetting::updateOrCreate(
            ['key' => 'usd_to_eur_rate'],
            [
                'value' => (string) $request->input('usd_to_eur_rate'),
                'description' => 'USD to EUR conversion rate',
            ]
        );

        GlobalSetting::updateOrCreate(
            ['key' => 'usd_to_tnd_rate'],
            [
                'value' => (string) $request->input('usd_to_tnd_rate'),
                'description' => 'USD to TND conversion rate',
            ]
        );

        return redirect()->route('settings.system')
            ->with('success', __('messages.settings_updated_success') ?: 'Global settings updated successfully!');
    }

    /**
     * Update custom translation overrides.
     */
    public function updateTranslation(Request $request): RedirectResponse
    {
        $request->validate([
            'translations' => 'required|array',
        ]);

        $translations = $request->input('translations'); // Array of key => [en => ..., fr => ..., ar => ...]

        foreach ($translations as $key => $locales) {
            foreach ($locales as $locale => $value) {
                if (is_null($value) || trim($value) === '') {
                    // Remove override if empty
                    TranslationOverride::where('locale', $locale)
                        ->where('key', 'messages.'.$key)
                        ->delete();
                } else {
                    // Save override
                    TranslationOverride::updateOrCreate(
                        [
                            'locale' => $locale,
                            'key' => 'messages.'.$key,
                        ],
                        [
                            'value' => trim($value),
                        ]
                    );
                }
            }
        }

        return redirect()->route('settings.system')
            ->with('success', __('messages.translation_updated_success') ?: 'Translation overrides updated successfully!');
    }

    /**
     * Update global AI provider configurations.
     */
    public function updateAi(Request $request): RedirectResponse
    {
        $request->validate([
            'ai_provider' => 'required|in:ollama,lmstudio,gemini,openrouter,qwen',
            'ai_multi_agent_enabled' => 'nullable|string',
            'ollama_model' => 'required_if:ai_provider,ollama|nullable|string',
            'ollama_light_model' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('ai_provider') === 'ollama' && $request->boolean('ai_multi_agent_enabled') && empty($value)) {
                        $fail('The Ollama light model is required when multi-agent architecture is enabled.');
                    }
                },
            ],
            'ollama_url' => 'required_if:ai_provider,ollama|nullable|url',
            'lmstudio_model' => 'required_if:ai_provider,lmstudio|nullable|string',
            'lmstudio_light_model' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('ai_provider') === 'lmstudio' && $request->boolean('ai_multi_agent_enabled') && empty($value)) {
                        $fail('The LM Studio light model is required when multi-agent architecture is enabled.');
                    }
                },
            ],
            'lmstudio_url' => 'required_if:ai_provider,lmstudio|nullable|url',
            'gemini_model' => 'required_if:ai_provider,gemini|nullable|string',
            'gemini_light_model' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('ai_provider') === 'gemini' && $request->boolean('ai_multi_agent_enabled') && empty($value)) {
                        $fail('The Gemini light model is required when multi-agent architecture is enabled.');
                    }
                },
            ],
            'gemini_api_key' => 'required_if:ai_provider,gemini|nullable|string',
            'openrouter_model' => 'required_if:ai_provider,openrouter|nullable|string',
            'openrouter_light_model' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('ai_provider') === 'openrouter' && $request->boolean('ai_multi_agent_enabled') && empty($value)) {
                        $fail('The OpenRouter light model is required when multi-agent architecture is enabled.');
                    }
                },
            ],
            'openrouter_api_key' => 'required_if:ai_provider,openrouter|nullable|string',
            'qwen_model' => 'required_if:ai_provider,qwen|nullable|string',
            'qwen_light_model' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('ai_provider') === 'qwen' && $request->boolean('ai_multi_agent_enabled') && empty($value)) {
                        $fail('The Qwen light model is required when multi-agent architecture is enabled.');
                    }
                },
            ],
            'qwen_url' => 'required_if:ai_provider,qwen|nullable|url',
            'qwen_api_key' => 'required_if:ai_provider,qwen|nullable|string',
        ]);

        $settings = [
            'ai_provider' => $request->input('ai_provider'),
            'ai_multi_agent_enabled' => $request->boolean('ai_multi_agent_enabled') ? '1' : '0',
            'ollama_model' => $request->input('ollama_model', 'gemma4:e2b'),
            'ollama_light_model' => $request->input('ollama_light_model', 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf'),
            'ollama_url' => $request->input('ollama_url', 'http://localhost:11434'),
            'lmstudio_model' => $request->input('lmstudio_model', 'qwen2.5-coder-7b-instruct'),
            'lmstudio_light_model' => $request->input('lmstudio_light_model', 'gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf'),
            'lmstudio_url' => $request->input('lmstudio_url', 'http://localhost:1234/v1'),
            'gemini_model' => $request->input('gemini_model', 'gemini-1.5-flash'),
            'gemini_light_model' => $request->input('gemini_light_model', 'gemini-1.5-flash'),
            'gemini_api_key' => $request->input('gemini_api_key', ''),
            'openrouter_model' => $request->input('openrouter_model', 'meta-llama/llama-3-8b-instruct:free'),
            'openrouter_light_model' => $request->input('openrouter_light_model', 'meta-llama/llama-3-8b-instruct:free'),
            'openrouter_api_key' => $request->input('openrouter_api_key', ''),
            'qwen_model' => $request->input('qwen_model', 'qwen-plus'),
            'qwen_light_model' => $request->input('qwen_light_model', 'qwen-turbo'),
            'qwen_url' => $request->input('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
            'qwen_api_key' => $request->input('qwen_api_key', ''),
        ];

        foreach ($settings as $key => $value) {
            GlobalSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => (string) ($value ?? ''),
                    'description' => ucfirst(str_replace('_', ' ', $key)).' setting',
                ]
            );
        }

        // Mirror the AI settings into the phpkaiharness harness config so the
        // harness dashboard and AgentLoop use the same provider.
        HarnessConfig::syncFromGlobalSettings();

        return redirect()->route('settings.system')
            ->with('success', __('messages.settings_updated_success') ?: 'AI settings updated successfully!');
    }
}
