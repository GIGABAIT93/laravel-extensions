<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Services;

use Gigabait93\Extensions\Support\ManifestValue;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Log;

readonly class MigratorService
{
    public function __construct(private Container $app)
    {
    }

    public function migrate(ManifestValue $manifest): bool
    {
        $dir = rtrim($manifest->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
        if (!is_dir($dir)) {
            return true; // nothing to do
        }
        /** @var Migrator $migrator */
        $migrator = $this->app->make('migrator');
        try {
            if (!$migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }
        } catch (\Exception|\Error $e) {
            Log::warning('Extension migrator: Failed to create migration repository', [
                'extension' => $manifest->id,
                'error' => $e->getMessage(),
            ]);
        }
        try {
            $migrator->run([$dir]);

            return true;
        } catch (\Exception|\Error $e) {
            Log::error('Extension migrator: Failed to run migrations', [
                'extension' => $manifest->id,
                'directory' => $dir,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
