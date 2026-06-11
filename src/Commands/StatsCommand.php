<?php

namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class StatsCommand extends Command
{
    protected $signature = 'policy-discovery:stats';
    protected $description = 'Display statistics about policy discovery cache and mappings';

    public function handle(PolicyRegistrar $registrar): int
    {
        $stats = $registrar->getStats();

        $this->newLine();
        $this->info('Policy Discovery Statistics');
        $this->line("🔹 Total policies found: <info>{$stats['total_policies']}</info>");
        $this->line("🔹 Total model–policy mappings: <info>{$stats['total_mappings']}</info>");
        $this->line("🔹 Cache file: <comment>" . config('policy-discovery.cache_file') . "</comment>");
        
        if ($stats['cache_file_size'] > 0) {
            $size = $this->formatBytes($stats['cache_file_size']);
            $this->line("🔹 Cache size: <comment>{$size}</comment>");
        } else {
            $this->line("🔹 Cache size: <comment>not created yet</comment>");
        }

        if ($stats['is_compressed']) {
            $this->line("🔹 Compression: <info>Yes</info> (saved {$stats['compression_percentage']}%)");
        } else {
            $this->line("🔹 Compression: <comment>No</comment> (run <fg=yellow>policy-discovery:optimize</> to enable)");
        }

        if ($stats['last_modified']) {
            $date = date('Y-m-d H:i:s', $stats['last_modified']);
            $this->line("🔹 Last cache update: <comment>{$date}</comment>");
        }

        return 0;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}