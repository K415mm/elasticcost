<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->extend('translator', function ($translator, $app) {
            $loader = $app['translation.loader'];
            $locale = $translator->getLocale();

            $trans = new \App\Services\CustomTranslator($loader, $locale);
            $trans->setFallback($translator->getFallback());

            return $trans;
        });
    }
}
