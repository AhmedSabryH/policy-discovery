<?php
namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class WarmupCacheCommand extends Command
{
    protected $signature = 'policy-discovery:warmup';
    protected $description = 'Generate policy cache without waiting for HTTP request';

    public function handle(PolicyRegistrar $registrar): int
    {
        $this->info('Scanning for policies...');
        $mapping = $registrar->discoverPolicies();
        $count = count($mapping);
        $this->info("Discovered {$count} policy-model mappings.");
        return 0;
    }
}
