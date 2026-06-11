<?php

namespace MudQadm\PolicyDiscovery\Commands;

use Illuminate\Console\Command;
use MudQadm\PolicyDiscovery\PolicyRegistrar;

class CheckPoliciesCommand extends Command
{
    protected $signature = 'policy-discovery:check';
    protected $description = 'Validate policy-model mappings and report issues';

    public function handle(PolicyRegistrar $registrar): int
    {
        $this->info('Checking policy discovery integrity...');
        $report = $registrar->checkMapping();

        $hasErrors = false;

        if (!empty($report['missing_models'])) {
            $hasErrors = true;
            $this->error('❌ Policies with missing models:');
            foreach ($report['missing_models'] as $item) {
                $this->line("   Policy: {$item['policy']} → expects model: {$item['missing_model']}");
            }
        }

        if (!empty($report['missing_policies'])) {
            $hasErrors = true;
            $this->warn('⚠️  Policy files discovered but not mapped to any model:');
            foreach ($report['missing_policies'] as $policy) {
                $this->line("   {$policy}");
            }
            $this->line('   (Hint: use #[PolicyFor(Model::class)] attribute or fix naming convention)');
        }

        if (!empty($report['warnings']['models_without_policy'])) {
            $this->warn('⚠️  Models without any policy:');
            foreach ($report['warnings']['models_without_policy'] as $model) {
                $this->line("   {$model}");
            }
        }

        if (!$hasErrors && empty($report['warnings'])) {
            $this->info('✓ All policies and models are correctly mapped.');
        }

        return $hasErrors ? 1 : 0;
    }
}