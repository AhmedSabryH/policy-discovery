<?php

namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class ListPoliciesCommand extends Command
{
    protected $signature = 'policy-discovery:list';
    protected $description = 'List all discovered policies and their mapped models';

    public function handle(PolicyRegistrar $registrar): int
    {
        $mapping = $registrar->getCachedMapping();
        if (!$mapping) {
            $this->warn('No cached mapping found. Discovering now...');
            $mapping = $registrar->discoverPolicies();
        }

        if (empty($mapping)) {
            $this->info('No policies discovered.');
            return 0;
        }

        $rows = [];
        foreach ($mapping as $model => $policy) {
            $rows[] = [$model, $policy];
        }

        $this->table(['Model', 'Policy'], $rows);
        return 0;
    }
}