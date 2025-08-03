<?php

namespace Gigabait93\Extensions\Services\Concerns;

trait DiscoversExtensions
{
    /**
     * Scan the filesystem for new extensions, register them (disabled by default),
     * and return the list of newly discovered names.
     *
     * @return string[]
     */
    public function discover(): array
    {
        $found = [];
        foreach ($this->paths as $base) {
            if (! $this->fs->isDirectory($base)) {
                continue;
            }
            foreach ($this->fs->directories($base) as $dir) {
                if ($this->fs->exists("$dir/extension.json")) {
                    $found[] = basename($dir);
                }
            }
        }
        $found = array_unique($found);

        $statuses = $this->activator->getStatuses();
        $new      = [];

        foreach ($found as $name) {
            if (! array_key_exists($name, $statuses)) {
                // register new extension as disabled
                $this->activator->setStatus($name, false);
                $new[] = $name;
            }
        }

        if (! empty($new)) {
            $this->invalidateCache();
        }

        return $new;
    }
}
