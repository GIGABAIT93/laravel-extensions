<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Tests;

use Gigabait93\Extensions\Jobs\ExtensionEnableJob;
use Illuminate\Support\Facades\Bus;

class ConsoleCommandModesTest extends TestCase
{
    public function test_enable_command_supports_queue_mode_with_ids_option(): void
    {
        Bus::fake();

        $this->artisan('extensions:enable', [
            '--ids' => 'sample,base',
            '--queue' => true,
            '--json' => true,
        ])->assertExitCode(0);

        Bus::assertDispatchedTimes(ExtensionEnableJob::class, 2);
    }

    public function test_disable_command_fails_on_conflicting_target_options(): void
    {
        $this->artisan('extensions:disable', [
            'id' => 'sample',
            '--all' => true,
            '--json' => true,
        ])->assertExitCode(1);
    }

    public function test_enable_command_fails_on_conflicting_argument_and_ids_options(): void
    {
        $this->artisan('extensions:enable', [
            'id' => 'sample',
            '--ids' => 'base',
            '--json' => true,
        ])->assertExitCode(1);
    }

    public function test_make_command_requires_type_in_non_interactive_mode(): void
    {
        $this->artisan('extensions:make', [
            'name' => 'Blog',
            '--json' => true,
        ])->assertExitCode(1);
    }

    public function test_bulk_command_requires_force_in_non_interactive_mode(): void
    {
        $this->artisan('extensions:bulk', [
            'operation' => 'enable',
            '--ids' => 'sample',
            '--json' => true,
        ])->assertExitCode(1);
    }

    public function test_delete_command_requires_force_in_non_interactive_mode(): void
    {
        $this->artisan('extensions:delete', [
            'id' => 'sample',
            '--json' => true,
        ])->assertExitCode(1);
    }
}
