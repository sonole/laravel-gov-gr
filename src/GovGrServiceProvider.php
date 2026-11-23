<?php

namespace Sonole\GovGr;

use Sonole\GovGr\Amka\Amka;
use Sonole\GovGr\BankConfirm\BankConfirm;
use Sonole\GovGr\Core\Audit\DefaultIdentityResolver;
use Sonole\GovGr\Core\Audit\Resolvers\ChainIpResolver;
use Sonole\GovGr\Core\Audit\Resolvers\HeaderIpResolver;
use Sonole\GovGr\Core\Audit\Resolvers\RequestIpResolver;
use Sonole\GovGr\Core\Console\Commands\AuditReportCommand;
use Sonole\GovGr\Core\Contracts\IdentityResolver;
use Sonole\GovGr\Core\Contracts\IpResolver;
use Sonole\GovGr\Ergani\Ergani;
use Sonole\GovGr\Gemi\Gemi;
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Contracts\TaxisnetOAuthLogoutHandler;
use Sonole\GovGr\TaxisnetOAuth\Responders\DefaultTaxisnetOAuthLoginResponder;
use Sonole\GovGr\TaxisnetOAuth\Responders\DefaultTaxisnetOAuthLogoutHandler;
use Sonole\GovGr\TaxisnetOAuth\TaxisnetOAuth;
use Sonole\GovGr\TaxRegistry\TaxRegistry;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;
use Sonole\GovGr\Amka\Facades\Amka as AmkaFacade;
use Sonole\GovGr\BankConfirm\Facades\BankConfirm as BankConfirmFacade;
use Sonole\GovGr\Ergani\Facades\Ergani as ErganiFacade;
use Sonole\GovGr\Gemi\Facades\Gemi as GemiFacade;
use Sonole\GovGr\TaxRegistry\Facades\TaxRegistry as TaxRegistryFacade;

class GovGrServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/govgr.php', 'govgr');

        $this->app->bind(IdentityResolver::class, DefaultIdentityResolver::class);

        $this->app->bind(IpResolver::class, function ($app) {
            $resolvers = [];

            foreach (config('govgr.audit.ip_resolver', []) as $entry) {
                $resolvers[] = match ($entry['driver']) {
                    'header' => new HeaderIpResolver($entry['header'], $entry['take'] ?? 'first'),
                    'request' => new RequestIpResolver(),
                    // Your own IpResolver implementation, resolved through the
                    // container so it can take its own dependencies.
                    'custom' => $app->make($entry['class']),
                    default => throw new InvalidArgumentException("Unknown IP resolver driver [{$entry['driver']}]."),
                };
            }

            return new ChainIpResolver($resolvers ?: [new RequestIpResolver()]);
        });

        $this->app->singleton(Gemi::class);
        $this->app->singleton(Amka::class);
        $this->app->singleton(Ergani::class);
        $this->app->singleton(TaxRegistry::class);
        $this->app->singleton(BankConfirm::class);
        $this->app->singleton(TaxisnetOAuth::class);
        $this->app->bind(TaxisnetOAuthLoginResponder::class, DefaultTaxisnetOAuthLoginResponder::class);
        $this->app->bind(TaxisnetOAuthLogoutHandler::class, DefaultTaxisnetOAuthLogoutHandler::class);
    }

    public function boot(): void
    {
        if (config('govgr.fake')) {
            if ($this->app->environment('production')) {
                throw new RuntimeException('govgr.fake (GOVGR_FAKE) must not be enabled in production: it replaces the real services.');
            }

            TaxRegistryFacade::fake();
            AmkaFacade::fake();
            GemiFacade::fake();
            ErganiFacade::fake();
            BankConfirmFacade::fake();
        }

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'govgr');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'taxisnet-oauth');

        if (config('govgr.taxisnet-oauth.routes_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/taxisnet-oauth.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/govgr.php' => config_path('govgr.php'),
            ], 'gov-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/taxisnet-oauth'),
            ], 'taxisnet-oauth-views');

            $this->commands([
                AuditReportCommand::class,
            ]);
        }
    }
}