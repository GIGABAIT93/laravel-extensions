<?php

namespace Gigabait93\Extensions\Commands\Concerns;

use Gigabait93\Extensions\Facades\Extensions;
use function Laravel\Prompts\select;

trait InteractsWithExtensions
{
    protected function promptExtension(string $promptKey): ?string
    {
        $name = $this->argument('extension');
        if ($name) {
            return $name;
        }

        if (! $this->input->isInteractive()) {
            $this->error(trans('extensions::commands.extension_name_required'));
            return null;
        }

        $list = Extensions::all()->map(fn($e) => $e->getName())->toArray();
        if (empty($list)) {
            $this->error(trans('extensions::commands.no_extensions_to_process'));
            return null;
        }

        return select(trans($promptKey), $list);
    }
}
