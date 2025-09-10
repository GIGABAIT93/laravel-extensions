<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

/**
 * Publish package configuration, migrations, translations and stubs.
 *
 * @option string $tag   Publish tag [extensions|extensions-config|extensions-migrations|extensions-lang|extensions-stubs]
 * @option bool   $force Overwrite existing files
 * @option bool   $plain Output without formatting
 */
class PublishCommand extends BaseCommand
{
    protected $signature = 'extensions:publish '
        . '{--tag= : Publish tag [extensions|extensions-config|extensions-migrations|extensions-lang|extensions-stubs]} '
        . '{--force : Overwrite existing files} '
        . '{--plain : Output without formatting}';

    protected $description = 'Publish package configuration, migrations, translations and stubs';

    public function handle(): int
    {
        $tag = (string) ($this->option('tag') ?? '');
        $force = (bool) $this->option('force');

        $interactive = $this->isInteractive();
        if ($tag === '' && $interactive) {
            $tag = (string) select(__('extensions::lang.select_publish'), [
                'extensions' => __('extensions::lang.publish_all'),
                'extensions-config' => __('extensions::lang.publish_config'),
                'extensions-migrations' => __('extensions::lang.publish_migrations'),
                'extensions-lang' => __('extensions::lang.publish_lang'),
                'extensions-stubs' => __('extensions::lang.publish_stubs'),
            ], 'extensions');
        }
        if ($tag === '') {
            $tag = 'extensions';
        }

        // Use Laravel's built-in vendor:publish command
        $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
            '--provider' => 'Gigabait93\Extensions\Providers\ExtensionsServiceProvider',
        ]);

        info(__('extensions::lang.published_tag', ['tag' => $tag]));

        return self::SUCCESS;
    }
}
