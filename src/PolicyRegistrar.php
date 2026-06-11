<?php

namespace MudQadm\PolicyDiscovery;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use MudQadm\PolicyDiscovery\Attributes\PolicyFor;

class PolicyRegistrar
{
    protected array $mapping = [];

    public function register(): void
    {
        $this->mapping = $this->getCachedMapping() ?? $this->discoverPolicies();

        foreach ($this->mapping as $modelClass => $policyClass) {
            Gate::policy($modelClass, $policyClass);
        }
    }

    public function discoverPolicies(): array
    {
        $policyDirectory = config('policy-discovery.policy_directory', app_path('Policies'));
        $modelDirectories = config('policy-discovery.model_directories', [app_path('Models')]);
        $recursive = config('policy-discovery.recursive', true);

        if (!is_dir($policyDirectory)) {
            return [];
        }

        $finder = Finder::create()
            ->files()
            ->in($policyDirectory)
            ->name('*Policy.php');

        if (!$recursive) {
            $finder->depth(0);
        }

        $mapping = [];

        foreach ($finder as $file) {
            $policyClass = $this->getClassFromFile($file, $policyDirectory);
            if (!$policyClass || !class_exists($policyClass)) {
                continue;
            }

            $models = $this->resolveModelsForPolicy($policyClass, $modelDirectories);
            foreach ($models as $model) {
                $mapping[$model] = $policyClass;
            }
        }

        if (config('policy-discovery.cache_enabled', true)) {
            $this->storeCache($mapping, false);
        }

        return $mapping;
    }

    /**
     * Store mapping in a JSON file.
     */
    public function storeCache(array $mapping, bool $compressed = false): void
    {
        $cachePath = config('policy-discovery.cache_file', storage_path('policy-discovery/mapping.json'));

        $directory = dirname($cachePath);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $json = json_encode($mapping, JSON_PRETTY_PRINT);

        if ($compressed) {
            $json = gzencode($json, 6);
        }

        File::put($cachePath, $json);
    }

    /**
     * Retrieve mapping from JSON file if it exists and is valid.
     */
    public function getCachedMapping(): ?array
    {
        if (!config('policy-discovery.cache_enabled', true)) {
            return null;
        }

        $cachePath = config('policy-discovery.cache_file', storage_path('policy-discovery/mapping.json'));


        if (!File::exists($cachePath)) {
            return null;
        }

        $content = File::get($cachePath);

        $decompressed = @gzdecode($content);
        if ($decompressed !== false) {
            $content = $decompressed;
        }

        $mapping = json_decode($content, true);

        return is_array($mapping) ? $mapping : null;
    }

    public function clearCache(): void
    {
        $cachePath = config('policy-discovery.cache_file', storage_path('policy-discovery/mapping.json'));
        if (File::exists($cachePath)) {
            File::delete($cachePath);
        }
    }

    /**
     * Check for missing model mappings or orphaned policies.
     * 
     * @return array{missing_models: array, missing_policies: array, warnings: array}
     */
    public function checkMapping(): array
    {
        $mapping = $this->getCachedMapping() ?? $this->discoverPolicies();
        $allModelClasses = $this->getAllModelClasses();

        $missingModels = [];
        $missingPolicies = [];
        $warnings = [];

        $policiesSeen = [];
        foreach ($mapping as $modelClass => $policyClass) {
            $policiesSeen[$policyClass] = true;
            if (!class_exists($modelClass)) {
                $missingModels[] = [
                    'policy' => $policyClass,
                    'missing_model' => $modelClass,
                ];
            }
        }

        $allPolicies = $this->scanPolicyFiles();
        foreach ($allPolicies as $policyClass) {
            if (!isset($policiesSeen[$policyClass])) {
                $missingPolicies[] = $policyClass;
            }
        }

        foreach ($allModelClasses as $modelClass) {
            if (!isset($mapping[$modelClass])) {
                $missingPoliciesForModel[] = $modelClass;
            }
        }
        if (!empty($missingPoliciesForModel)) {
            $warnings['models_without_policy'] = $missingPoliciesForModel;
        }

        return [
            'missing_models' => $missingModels,
            'missing_policies' => $missingPolicies,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get statistics about discovered policies and cache.
     * 
     * @return array{
     *     total_policies: int,
     *     total_mappings: int,
     *     cache_file_size: int,
     *     is_compressed: bool,
     *     last_modified: int|null,
     *     compression_percentage: float|null
     * }
     */
    public function getStats(): array
    {
        $cachePath = config('policy-discovery.cache_file', storage_path('policy-discovery/mapping.json'));
        $mapping = $this->getCachedMapping() ?? $this->discoverPolicies();

        $totalMappings = count($mapping);
        $uniquePolicies = count(array_unique($mapping));

        $stats = [
            'total_policies' => $uniquePolicies,
            'total_mappings' => $totalMappings,
            'cache_file_size' => 0,
            'is_compressed' => false,
            'last_modified' => null,
            'compression_percentage' => null,
        ];

        if (File::exists($cachePath)) {
            $stats['cache_file_size'] = File::size($cachePath);
            $stats['last_modified'] = File::lastModified($cachePath);

            $content = File::get($cachePath);
            $decompressed = @gzdecode($content);
            if ($decompressed !== false) {
                $stats['is_compressed'] = true;
                $originalSize = strlen($decompressed);
                $compressedSize = $stats['cache_file_size'];
                if ($originalSize > 0) {
                    $stats['compression_percentage'] = round((1 - $compressedSize / $originalSize) * 100, 2);
                }
            }
        }

        return $stats;
    }

    protected function getClassFromFile(\SplFileInfo $file, string $baseDir): ?string
    {
        $relativePath = $file->getRealPath();
        $namespace = config('policy-discovery.policy_namespace', 'App\\Policies');
        $relativePathFromBase = str_replace($baseDir, '', $relativePath);
        $relativePathFromBase = ltrim($relativePathFromBase, DIRECTORY_SEPARATOR);
        $relativePathFromBase = str_replace(['/', '\\'], '\\', $relativePathFromBase);
        $relativePathFromBase = preg_replace('/\.php$/', '', $relativePathFromBase);
        return $namespace . '\\' . $relativePathFromBase;
    }

    protected function resolveModelsForPolicy(string $policyClass, array $modelDirectories): array
    {
        $reflection = new ReflectionClass($policyClass);
        $attributes = $reflection->getAttributes(PolicyFor::class);

        if (!empty($attributes)) {
            $models = [];
            foreach ($attributes as $attr) {
                $policyFor = $attr->newInstance();
                $models = array_merge($models, $policyFor->models);
            }
            return $models;
        }

        $shortName = $reflection->getShortName();
        $modelName = preg_replace('/Policy$/', '', $shortName);

        $possibleModelPaths = [
            $modelName,
            str_replace('\\', '/', $modelName)
        ];

        foreach ($modelDirectories as $baseModelsDir) {
            $baseModelsDir = rtrim($baseModelsDir, DIRECTORY_SEPARATOR);
            $baseNamespace = $this->getNamespaceFromDirectory($baseModelsDir);

            foreach ($possibleModelPaths as $modelPath) {
                $modelClass = $baseNamespace . '\\' . str_replace('/', '\\', $modelPath);
                if (class_exists($modelClass)) {
                    return [$modelClass];
                }
            }
        }

        $policyPath = str_replace('\\', '/', $reflection->getNamespaceName());
        $policyPath = str_replace('App\\Policies\\', '', $policyPath);
        $modelByPath = 'App\\Models\\' . str_replace('/', '\\', $policyPath) . '\\' . $modelName;
        if (class_exists($modelByPath)) {
            return [$modelByPath];
        }

        return [];
    }

    protected function getNamespaceFromDirectory(string $directory): string
    {
        $basePath = app_path();
        $relative = str_replace($basePath, '', $directory);
        $relative = ltrim($relative, DIRECTORY_SEPARATOR);
        $namespace = str_replace(['/', '\\'], '\\', $relative);
        return 'App\\' . $namespace;
    }

    protected function getAllModelClasses(): array
    {
        $modelDirectories = config('policy-discovery.model_directories', [app_path('Models')]);
        $models = [];
        foreach ($modelDirectories as $dir) {
            if (!is_dir($dir)) continue;
            $finder = Finder::create()->files()->in($dir)->name('*.php');
            foreach ($finder as $file) {
                $class = $this->getClassFromFile($file, $dir);
                if ($class && class_exists($class)) {
                    $models[] = $class;
                }
            }
        }
        return $models;
    }

    protected function scanPolicyFiles(): array
    {
        $policyDirectory = config('policy-discovery.policy_directory', app_path('Policies'));
        if (!is_dir($policyDirectory)) return [];
        $finder = Finder::create()->files()->in($policyDirectory)->name('*Policy.php');
        $policies = [];
        foreach ($finder as $file) {
            $class = $this->getClassFromFile($file, $policyDirectory);
            if ($class && class_exists($class)) {
                $policies[] = $class;
            }
        }
        return $policies;
    }
}
