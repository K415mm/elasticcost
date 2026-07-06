<?php

namespace App\Providers;

use App\Http\Client\CustomHttpFactory;
use App\Services\AiConfigHelper;
use App\Services\CustomTranslator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        Gate::define('use-ai-agents', fn ($user = null) => true);

        // Pulse dashboard access — managers and CEO only
        Gate::define('viewPulse', fn ($user = null) => $user?->hasAnyRole(['manager', 'ceo']) ?? false);

        // Passport scopes mapped to user roles
        Passport::tokensCan([
            'client' => 'Client role — view own sizing reports and data',
            'manager' => 'Manager role — manage clients, scenarios, and assets',
            'sales_manager' => 'Sales Manager role — manage sales pipeline and partners',
            'partner' => 'Partner role — limited external partner access',
            'ceo' => 'CEO role — full access to all resources',
        ]);

        Passport::defaultScopes(['client']);

        // Role-based authorization Gates
        Gate::define('manage-clients', fn ($user = null) => $user?->hasAnyRole(['manager', 'sales_manager', 'ceo']) ?? false);
        Gate::define('manage-scenarios', fn ($user = null) => $user?->hasAnyRole(['manager', 'ceo']) ?? false);
        Gate::define('manage-partners', fn ($user = null) => $user?->hasAnyRole(['sales_manager', 'ceo']) ?? false);
        Gate::define('manage-settings', fn ($user = null) => $user?->isCeo() ?? false);
        Gate::define('view-all-reports', fn ($user = null) => $user?->hasAnyRole(['manager', 'sales_manager', 'ceo']) ?? false);
        Gate::define('view-financials', fn ($user = null) => $user?->hasAnyRole(['sales_manager', 'ceo']) ?? false);

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
