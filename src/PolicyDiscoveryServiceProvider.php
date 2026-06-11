<?php

namespace MudQadm\PolicyDiscovery;


use Illuminate\Support\ServiceProvider;
use MudQadm\PolicyDiscovery\Commands\CheckPoliciesCommand;
use MudQadm\PolicyDiscovery\Commands\ClearPolicyCacheCommand;
use MudQadm\PolicyDiscovery\Commands\ExportMappingCommand;
use MudQadm\PolicyDiscovery\Commands\ListPoliciesCommand;
use MudQadm\PolicyDiscovery\Commands\OptimizeCacheCommand;
use MudQadm\PolicyDiscovery\Commands\RebuildCacheCommand;
use MudQadm\PolicyDiscovery\Commands\StatsCommand;
use MudQadm\PolicyDiscovery\Commands\WarmupCacheCommand;

class PolicyDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/policy-discovery.php', 'policy-discovery');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/Config/policy-discovery.php' => config_path('policy-discovery.php'),
        ], 'policy-discovery-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearPolicyCacheCommand::class,
                OptimizeCacheCommand::class,
                ListPoliciesCommand::class,
                WarmupCacheCommand::class,
                CheckPoliciesCommand::class,
                StatsCommand::class,
                RebuildCacheCommand::class,
                ExportMappingCommand::class,
            ]);
        }

        $this->app->booted(function () {
            $this->app->make(PolicyRegistrar::class)->register();
        });
    }
}
