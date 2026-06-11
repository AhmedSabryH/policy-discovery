<?php
namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;
use Illuminate\Support\Facades\File;

class ExportMappingCommand extends Command
{
    protected $signature = 'policy-discovery:export {--path= : Export file path}';
    protected $description = 'Export current policy mapping to JSON file';

    public function handle(PolicyRegistrar $registrar): int
    {
        $path = $this->option('path') ?? storage_path('policy-discovery/export.json');
        $mapping = $registrar->getCachedMapping() ?? $registrar->discoverPolicies();
        File::put($path, json_encode($mapping, JSON_PRETTY_PRINT));
        $this->info("Mapping exported to {$path}");
        return 0;
    }
}
