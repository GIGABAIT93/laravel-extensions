<?php

namespace Gigabait93\Extensions\Services\Concerns;

use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Support\Facades\Artisan;

trait ManagesExtensions
{
    /**
     * Install an extension: run migrations and seeders.
     *
     * @param  string $name
     * @param  bool   $force
     * @return string Result message
     */
    public function install(string $name, bool $force = false): string
    {
        $ext = $this->get($name);
        if (! $ext) {
            return "Extension '{$name}' not found.";
        }

        if ($mp = $ext->getMigrationPath()) {
            Artisan::call('migrate', ['--path' => $this->relative($mp), '--force' => $force]);
        }

        if ($sp = $ext->getSeederPath()) {
            $ns = $ext->getNamespace();
            if ($ns && class_exists($class = "{$ns}\\Database\\Seeders\\DatabaseSeeder")) {
                Artisan::call('db:seed', ['--class' => $class, '--force' => $force]);
            } else {
                Artisan::call('db:seed', ['--path' => $this->relative($sp), '--force' => $force]);
            }
        }

        $this->callExtensionEvent($ext, 'onInstall');
        $this->invalidateCache();
        return "Extension '{$name}' installed.";
    }

    /**
     * Enable an extension (unless protected).
     */
    public function enable(string $name): string
    {
        $ext = $this->get($name);

        if ($ext) {
            $type        = $ext->getType();
            $switchTypes = config('extensions.switch_types', []);

            if ($type && in_array($type, $switchTypes, true)) {
                foreach ($this->all() as $other) {
                    if ($other->getName() === $name) {
                        continue;
                    }
                    if ($other->getType() === $type && ! $other->isProtected()) {
                        $this->activator->setStatus($other->getName(), false);
                    }
                }
            }
        }

        $ok = $this->activator->setStatus($name, true);
        $this->invalidateCache();
        if ($ok && $ext) {
            $this->callExtensionEvent($ext, 'onEnable');
        }
        return $ok ? "Extension enabled." : "Enable failed.";
    }

    /**
     * Disable an extension (unless protected).
     */
    public function disable(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected.";
        }
        $ext = $this->get($name);
        $ok  = $this->activator->setStatus($name, false);
        $this->invalidateCache();
        if ($ok && $ext) {
            $this->callExtensionEvent($ext, 'onDisable');
        }
        return $ok ? "Extension disabled." : "Disable failed.";
    }

    /**
     * Delete an extension directory (unless protected).
     */
    public function delete(string $name): string
    {
        if ($this->isProtected($name)) {
            return "Extension '{$name}' is protected.";
        }

        $ext     = $this->get($name);
        $deleted = false;
        foreach ($this->paths as $base) {
            $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $name;
            if ($this->fs->isDirectory($dir)) {
                if ($ext) {
                    $this->callExtensionEvent($ext, 'onDelete');
                }
                $this->fs->deleteDirectory($dir);
                $deleted = true;
            }
        }

        $this->invalidateCache();
        return $deleted ? "Extension '{$name}' deleted." : "Extension '{$name}' not found.";
    }

    /**
     * Check if an extension has a migrations folder.
     */
    public function hasMigrations(string $name): bool
    {
        return (bool) $this->get($name)?->getMigrationPath();
    }

    /**
     * Check if an extension has a seeders folder.
     */
    public function hasSeeders(string $name): bool
    {
        return (bool) $this->get($name)?->getSeederPath();
    }

    /**
     * Call lifecycle event on the extension class if it exists.
     */
    protected function callExtensionEvent(Extension $ext, string $event): void
    {
        $ns = $ext->getNamespace();
        if (! $ns) {
            return;
        }

        $class = $ns . '\\Extension';
        if (class_exists($class) && method_exists($class, $event)) {
            $class::$event();
        }
    }
}
