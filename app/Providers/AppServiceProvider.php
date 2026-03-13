<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\AmazonSesProvider;
use App\Integrations\AmazonSnsProvider;
use App\Integrations\FreeSwitchEslClient;
use App\Integrations\FreeSwitchEslProvider;
use App\Models\Campaign;
use App\Policies\CampaignPolicy;
use App\Policies\ContactPolicy;
use App\Support\SlidingWindowRateLimiter;
use App\Support\TemplateRenderer;
use Aws\Ses\SesClient;
use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register singletons
        $this->app->singleton(TemplateRenderer::class, function () {
            return new TemplateRenderer();
        });

        $this->app->singleton(SlidingWindowRateLimiter::class, function () {
            return new SlidingWindowRateLimiter();
        });

        // AWS Clients
        $this->app->singleton(SesClient::class, function () {
            return new SesClient([
                'version' => 'latest',
                'region' => config('messaging.aws.region'),
            ]);
        });

        $this->app->singleton(SnsClient::class, function () {
            return new SnsClient([
                'version' => 'latest',
                'region' => config('messaging.aws.region'),
            ]);
        });

        // Providers
        $this->app->singleton(AmazonSesProvider::class, function ($app) {
            return new AmazonSesProvider($app->make(SesClient::class));
        });

        $this->app->singleton(AmazonSnsProvider::class, function ($app) {
            return new AmazonSnsProvider($app->make(SnsClient::class));
        });

        $this->app->singleton(FreeSwitchEslProvider::class, function () {
            return new FreeSwitchEslProvider(
                new FreeSwitchEslClient(
                    config('messaging.freeswitch.host'),
                    config('messaging.freeswitch.port'),
                    config('messaging.freeswitch.password'),
                )
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(\App\Models\Contact::class, ContactPolicy::class);
    }
}
