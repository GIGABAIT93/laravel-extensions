<?php

declare(strict_types=1);

namespace Themes\Alpha\Providers;

use Illuminate\Support\ServiceProvider;

class AlphaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No bindings needed for tests
    }
}
