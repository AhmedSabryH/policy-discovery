<?php

namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class ClearPolicyCacheCommand extends Command
{
    protected $signature = 'policy-discovery:clear';
    protected $description = 'Clear cached policy mappings';

    public function handle(PolicyRegistrar $registrar): int
    {
        $registrar->clearCache();
        $this->info('Policy discovery cache cleared successfully.');
        return 0;
    }
}