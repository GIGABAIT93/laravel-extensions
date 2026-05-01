<?php

declare(strict_types=1);

namespace Modules\Scheduled\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduledServiceProvider extends ServiceProvider
{
    public static int $bootCalls = 0;

    public static int $scheduleCallbacks = 0;

    public function boot(): void
    {
        self::$bootCalls++;

        $this->app->booted(function (): void {
            self::$scheduleCallbacks++;

            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule
                ->call(static fn (): null => null)
                ->everyMinute()
                ->name('scheduled-fixture');
        });
    }
}
