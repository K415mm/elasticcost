<?php

namespace App\Http\Controllers;

use App\Models\GlobalSetting;
use App\Models\TranslationOverride;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    /**
     * Display the system configuration dashboard.
     */
    public function index()
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

        return view('settings.system', compact(
            'eurRate',
            'tndRate',
            'translationKeys',
            'frDefaults',
            'arDefaults',
            'dbOverrides'
        ));
    }

    /**
     * Update global settings (exchange rates).
     */
    public function update(Request $request)
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
    public function updateTranslation(Request $request)
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
                        ->where('key', 'messages.' . $key)
                        ->delete();
                } else {
                    // Save override
                    TranslationOverride::updateOrCreate(
                        [
                            'locale' => $locale,
                            'key' => 'messages.' . $key,
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
}
