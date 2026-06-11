<?php

namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class RebuildCacheCommand extends Command
{
    protected $signature = 'policy-discovery:rebuild';
    protected $description = 'Clear cache and rediscover all policies';

    public function handle(PolicyRegistrar $registrar): int
    {
        $registrar->clearCache();
        $this->info('Cache cleared. Rediscovering...');
        $registrar->discoverPolicies();
        $this->info('Cache rebuilt successfully.');
        return 0;
    }
}
