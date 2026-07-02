<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Translation\Translator as BaseTranslator;

class CustomTranslator extends BaseTranslator
{
    /**
     * Get the translation for the given key.
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        $locale = $locale ?: $this->locale;

        if (is_string($key) && str_starts_with($key, 'messages.')) {
            try {
                $override = DB::table('translation_overrides')
                    ->where('locale', $locale)
                    ->where('key', $key)
                    ->first();

                if ($override) {
                    return $this->makeReplacements($override->value, $replace);
                }
            } catch (\Exception $e) {
                // Fail silently (e.g., during migrations or when database isn't initialized)
            }
        }

        return parent::get($key, $replace, $locale, $fallback);
    }
}
