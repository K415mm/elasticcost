<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\GlobalSetting;
use App\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

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
                ]
            ]
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
                ]
            ]
        ];

        $response = $this->post(route('settings.system.translations.update'), $clearData);
        $response->assertRedirect(route('settings.system'));

        // Assert DB is cleaned up
        $this->assertDatabaseMissing('translation_overrides', [
            'key' => 'messages.close'
        ]);

        // Translator should fall back to default files
        App::setLocale('en');
        $this->assertEquals('Close', __('messages.close'));
    }
}
