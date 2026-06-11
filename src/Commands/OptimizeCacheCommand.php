<?php

namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class OptimizeCacheCommand extends Command
{
    protected $signature = 'policy-discovery:optimize';
    protected $description = 'Compress the policy cache file for better performance';

    public function handle(PolicyRegistrar $registrar): int
    {
        $cachePath = config('policy-discovery.cache_file', storage_path('policy-discovery/mapping.json'));

        if (!File::exists($cachePath)) {
            $this->warn('No cache file found. Run discovery first (e.g., by accessing any route).');
            return 1;
        }

        $currentContent = File::get($cachePath);
        $decompressed = @gzdecode($currentContent);
        if ($decompressed !== false) {
            $json = $decompressed;
        } else {
            $json = $currentContent;
        }

        $mapping = json_decode($json, true);
        if (!is_array($mapping)) {
            $this->error('Invalid cache file content.');
            return 1;
        }

        $originalSize = File::size($cachePath);
        $registrar->storeCache($mapping, true);
        $newSize = File::size($cachePath);
        $reduction = round((1 - $newSize / $originalSize) * 100, 2);

        $this->info("Cache optimized successfully.");
        $this->line("Size: {$originalSize} → {$newSize} bytes (saved {$reduction}%)");
        $this->info("System will now automatically read the compressed cache for faster performance.");

        return 0;
    }
}