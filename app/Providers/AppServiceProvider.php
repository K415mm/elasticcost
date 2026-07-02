<?php

namespace App\Providers;

use App\Http\Client\CustomHttpFactory;
use App\Services\AiConfigHelper;
use App\Services\CustomTranslator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            return new CustomHttpFactory($app->make(Dispatcher::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewHarness', fn ($user = null) => true);

        // Dynamic configuration of the AI SDK from database global settings


        AiConfigHelper::configure();

        // Ensure the phpkaiharness analytics store uses a shared, writable path
        // so both the web process and the queue worker write to the same SQLite DB.
        if (empty(config('harness.cache.db_path'))) {
            config(['harness.cache.db_path' => storage_path('app/phpkaiharness/monitor.db')]);
        }

        $this->app->extend('translator', function ($translator, $app) {
            $loader = $app['translation.loader'];
            $locale = $translator->getLocale();

            $trans = new CustomTranslator($loader, $locale);
            $trans->setFallback($translator->getFallback());

            return $trans;
        });
    }
}
