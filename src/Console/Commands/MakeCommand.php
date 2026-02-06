<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Console\Commands;

use Gigabait93\Extensions\Scaffolding\ExtensionBuilder;
use Gigabait93\Extensions\Scaffolding\StubGroups;
use Gigabait93\Extensions\Services\ExtensionService;
use Gigabait93\Extensions\Support\ScaffoldConfig;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

/**
 * Scaffold a new extension using predefined stubs.
 */
class MakeCommand extends BaseCommand
{
    protected $signature = 'extensions:make {name? : Extension name}
                            {--type= : Extension type}
                            {--base= : Base path for extension}
                            {--stubs-path= : Path to stubs directory}
                            {--groups=* : Stub groups to include}
                            {--all-groups : Include all available optional stub groups}
                            {--force : Overwrite existing files}
                            {--json : Output as JSON}
                            {--plain : Output without formatting}';

    protected $description = 'Scaffold a new extension using predefined stubs';

    public function handle(ExtensionService $extensions, ExtensionBuilder $builder): int
    {
        $types = $extensions->types();
        if (empty($types)) {
            $this->error(__('extensions::lang.no_types_configured'));

            return self::FAILURE;
        }

        $type = (string) ($this->option('type') ?? '');
        if ($type === '') {
            if ($this->isInteractive()) {
                $options = array_combine($types, $types);
                $type = (string) select(__('extensions::lang.select_extension_type'), $options);
            } else {
                $this->error(__('extensions::lang.type_required_non_interactive'));

                return self::FAILURE;
            }
        }
        // Validate provided type against configured types (case-insensitive)
        $typeValid = in_array(strtolower($type), array_map('strtolower', $types), true);
        if (!$typeValid) {
            Log::error('extensions:make invalid type provided', ['type' => $type, 'available' => $types]);
            $this->error(__('extensions::lang.invalid_type', ['type' => $type, 'available' => implode(', ', $types)]));

            return self::FAILURE;
        }

        $name = (string) ($this->argument('name') ?? '');
        if ($name === '') {
            if ($this->isInteractive()) {
                $name = (string) text(__('extensions::lang.enter_extension_name'), required: true, validate: function ($v) {
                    if (trim((string) $v) === '') {
                        return __('extensions::lang.name_required');
                    }

                    return null;
                });
            } else {
                $this->error(__('extensions::lang.name_required_non_interactive'));

                return self::FAILURE;
            }
        }
        $name = Str::studly($name);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            $this->error(__('extensions::lang.invalid_extension_name', ['name' => $name]));

            return self::FAILURE;
        }

        $base = $this->option('base');
        $stubsPath = $this->option('stubs-path') ?: ScaffoldConfig::stubsPath();
        $defaultGroups = (array) config('extensions.stubs.default', []);
        $groupsOpt = (array) ($this->option('groups') ?? []);
        $allGroups = (bool) $this->option('all-groups');

        // Scan available groups from stubs directory (not only from config)
        $available = StubGroups::scan($stubsPath);
        // Filter out mandatory groups from selection
        $mandatory = ScaffoldConfig::mandatoryGroups();
        $available = array_values(array_diff($available, $mandatory));

        $groups = array_values(array_filter(array_map('strval', $groupsOpt), static fn (string $group): bool => trim($group) !== ''));
        if ($allGroups) {
            $groups = $available;
        } elseif (empty($groups)) {
            // Offer selection of all detected groups; preselect defaults from config
            if ($this->isInteractive()) {
                $choices = empty($available) ? [] : array_combine($available, $available);
                $pre = array_values(array_intersect($available, $defaultGroups));
                $groups = empty($choices)
                    ? []
                    : multiselect(__('extensions::lang.select_stub_groups'), options: $choices, default: $pre, required: false);
            } else {
                $groups = array_values(array_intersect($available, $defaultGroups));
            }
        }

        // Log selection for easier troubleshooting
        Log::info('extensions:make selection', [
            'type' => $type,
            'name' => $name,
            'base' => $base,
            'stubsPath' => $stubsPath,
            'groups' => $groups,
        ]);

        $builder
            ->withType($type)
            ->withName($name)
            ->withBasePath(is_string($base) && $base !== '' ? (string) $base : null)
            ->withStubsPath($stubsPath)
            ->withGroups($groups)
            ->withForce((bool) $this->option('force'));

        try {
            $result = $builder->build();
        } catch (\Exception|\Error $e) {
            Log::error('extensions:make failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect(explode("\n", $e->getTraceAsString()))->take(5)->all(),
            ]);
            $this->error(__('extensions::lang.failed_to_create_extension', ['error' => $e->getMessage()]));

            return self::FAILURE;
        }

        if ($this->isJsonOutput()) {
            $this->line((string) json_encode([
                'success' => true,
                'name' => $name,
                'type' => $type,
                'result' => $result,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        info(__('extensions::lang.extension_created', ['name' => $name, 'namespace' => $result['namespace'], 'path' => $result['path']]));
        $rows = array_map(fn ($f) => [$f], $result['files']);
        if ($this->isInteractive()) {
            table([__('extensions::lang.generated_files')], $rows);
        } else {
            foreach ($rows as [$file]) {
                $this->line('- ' . $file);
            }
        }

        return self::SUCCESS;
    }
}
