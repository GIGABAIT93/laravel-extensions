<?php

declare(strict_types=1);

namespace Themes\Beta\Providers;

use Illuminate\Support\ServiceProvider;

class BetaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No bindings needed for tests
    }
}
