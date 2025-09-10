<?php

declare(strict_types=1);

namespace Modules\Sample\Providers;

use Illuminate\Support\ServiceProvider;

class SampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No bindings needed for tests
    }
}
