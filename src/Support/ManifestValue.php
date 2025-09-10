<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Support;

/**
 * Value object for extension manifest.
 */
final readonly class ManifestValue
{
    public function __construct(
        // Required first
        public string $id,
        public string $name,
        public string $provider,
        public string $path,

        // Optional after required
        public string $description = '',
        public string $author = '',
        public string $type = 'Modules',
        public string $version = '1.0.0',
        public ?string $compatible_with = '1.0.0',

        /** @var string[] */
        public array $requires_extensions = [],

        /** @var array<string,string> map of composer package => constraint */
        public array $requires_packages = [],

        /** @var array<string,mixed> raw/extra metadata from extension.json */
        public array $meta = [],
    ) {
    }
}
