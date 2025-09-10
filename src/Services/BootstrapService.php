<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Gigabait93\Extensions\Support\ManifestValue;
use Illuminate\Contracts\Foundation\Application;

class BootstrapService
{
    private bool $warmed = false;

    public function __construct(
        private readonly Application $app,
        private readonly AutoloadService $autoloader,
        private readonly RegistryService $registry,
        private readonly ActivatorContract $activator,
    ) {
    }

    public function warmup(): void
    {
        if ($this->warmed) {
            return;
        }
        $this->warmed = true;
        $this->registry->discover();
        $statuses = $this->activator->statuses();
        // Collect enabled manifests
        $manifests = [];
        foreach ($statuses as $id => $state) {
            if (!empty($state['enabled'])) {
                $m = $this->registry->find($id);
                if ($m) {
                    $manifests[$id] = $m;
                }
            }
        }

        // Apply load order from config: ids listed go first in given order
        $ordered = $this->applyLoadOrder(array_values($manifests));
        foreach ($ordered as $manifest) {
            $this->registerProvider($manifest);
        }
    }

    /**
     * @param ManifestValue[] $manifests
     * @return ManifestValue[]
     */
    private function applyLoadOrder(array $manifests): array
    {
        $orderCfg = config('extensions.load_order', []);
        // Normalize to a flat list of ids/names (case-insensitive)
        $order = [];
        foreach ((array) $orderCfg as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $order[] = $v;
            } elseif (is_string($v)) {
                $order[] = $v;
            }
        }
        if (empty($order)) {
            return $manifests;
        }
        $index = [];
        foreach ($order as $i => $idOrName) {
            $index[strtolower($idOrName)] = $i;
        }
        usort($manifests, function (ManifestValue $a, ManifestValue $b) use ($index) {
            $ia = $index[strtolower($a->id)] ?? $index[strtolower($a->name)] ?? PHP_INT_MAX;
            $ib = $index[strtolower($b->id)] ?? $index[strtolower($b->name)] ?? PHP_INT_MAX;

            return $ia <=> $ib;
        });

        return $manifests;
    }

    public function registerProvider(ManifestValue $manifest): void
    {
        $provider = $manifest->provider;

        // Try to infer base namespace prefix up to \Providers\ and map to extension src dir
        $pos = strrpos($provider, '\\Providers\\');
        if ($pos !== false) {
            $baseNs = substr($provider, 0, $pos);
            if ($baseNs) {
                $this->autoloader->ensurePsr4($manifest, $baseNs);
            }
        }

        if (class_exists($provider)) {
            $this->app->register($provider);

            return;
        }

        // Fallback to convention: Type\\Name\\Providers\\NameServiceProvider
        $baseNs = rtrim($manifest->type, '\\') . '\\' . $manifest->name;
        $this->autoloader->ensurePsr4($manifest, $baseNs);
        $fallback = $baseNs . '\\Providers\\' . $manifest->name . 'ServiceProvider';
        if (class_exists($fallback)) {
            $this->app->register($fallback);
        }
    }
}
