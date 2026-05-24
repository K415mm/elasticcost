<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleAndCurrency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Process Language (Locale) Selection
        if ($request->has('locale')) {
            $locale = $request->query('locale');
            if (in_array($locale, ['en', 'fr', 'ar'])) {
                session(['locale' => $locale]);
            }
        }
        
        $activeLocale = session('locale', 'en');
        app()->setLocale($activeLocale);

        // 2. Process Currency Selection
        if ($request->has('currency')) {
            $currency = strtoupper($request->query('currency'));
            if (in_array($currency, ['USD', 'EUR', 'TND'])) {
                session(['currency' => $currency]);
            }
        }

        return $next($request);
    }
}
