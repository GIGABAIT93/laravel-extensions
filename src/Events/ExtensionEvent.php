<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Events;

use Gigabait93\Extensions\Entities\Extension;
use Illuminate\Foundation\Events\Dispatchable;

abstract class ExtensionEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Extension $extension,
        public readonly array $context = []
    ) {
    }
}
