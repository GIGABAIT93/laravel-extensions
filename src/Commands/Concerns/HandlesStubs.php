<?php

namespace Gigabait93\Extensions\Commands\Concerns;

use Illuminate\Support\Str;
use function Laravel\Prompts\{multiselect, select};

trait HandlesStubs
{
    protected function selectBasePath(?string $path): ?string
    {
        if (empty($this->bases)) {
            $this->error(trans('extensions::commands.paths_required'));
            return null;
        }

        $paths = array_values($this->bases);
        if (! ($path && in_array($path, $paths, true))) {
            $path = select(trans('extensions::commands.select_base_path'), $paths, $paths[0] ?? null);
        }

        if (! $path) {
            $this->error(trans('extensions::commands.base_path_required'));
            return null;
        }

        return $path;
    }

    protected function availableStubs(string $stubRoot): array
    {
        $groups = [];
        foreach ($this->files->allFiles($stubRoot) as $file) {
            $rel = Str::after($file->getPathname(), $stubRoot . DIRECTORY_SEPARATOR);
            $rel = str_replace('\\', '/', $rel);
            $group = Str::before($rel, '/');
            $group = Str::before($group, '.');
            $groups[] = Str::lower($group);
        }
        $groups = array_values(array_unique($groups));
        return array_values(array_diff($groups, ['extension', 'providers']));
    }

    protected function resolveStubs(array $available): array
    {
        $stubs = $this->option('stub');
        if (empty($stubs)) {
            $optionAll = trans('extensions::commands.option_all');
            $choices = array_merge([$optionAll], $available);
            $default = array_values(array_diff(config('extensions.stubs.default') ?: $available, ['extension', 'providers']));
            $stubs = multiselect(trans('extensions::commands.select_stubs'), $choices, $default, scroll: 10);
            if (in_array($optionAll, $stubs, true)) {
                $stubs = $available;
            }
        }

        $stubs = array_map('strtolower', $stubs);
        return array_values(array_unique(array_merge($stubs, ['extension', 'providers'])));
    }
}
