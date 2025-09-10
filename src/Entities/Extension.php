<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Entities;

use Gigabait93\Extensions\Facades\Extensions as ExtensionsFacade;
use Gigabait93\Extensions\Support\ManifestValue;
use Gigabait93\Extensions\Support\OpResult;

/**
 * Extension entity that exposes all manifest fields as real, typed, read-only properties.
 *
 * You can now access:
 *  $ext->id;
 *  $ext->name;
 *  $ext->description;
 *  $ext->author;
 *  $ext->type;
 *  $ext->provider;
 *  $ext->path;
 *  $ext->version;
 *  $ext->compatible_with;      // may be null
 *  $ext->requires_extensions;  // string[] from manifest
 *  $ext->requires_packages;    // array<string,string> from manifest
 *  $ext->meta;                 // array<string,mixed> from manifest
 *
 * Backward-compatible methods are kept and now simply return these properties.
 */
final class Extension
{
    // ===== Flattened manifest fields as real properties =====
    public readonly string $id;

    public readonly string $name;

    public readonly string $description;

    public readonly string $author;

    public readonly string $type;

    public readonly string $provider;

    public readonly string $path;

    public readonly string $version;

    public readonly ?string $compatible_with;

    /** @var string[] */
    public readonly array $requires_extensions;

    /** @var array<string,string> */
    public readonly array $requires_packages;

    /** @var array<string,mixed> */
    public readonly array $meta;

    // ===== Extension runtime flags =====
    private bool $enabled;

    private bool $broken;

    private ?string $issue;

    public function __construct(ManifestValue $manifest, bool $enabled = false, bool $broken = false, ?string $issue = null)
    {

        // Copy all public fields from manifest into typed read-only props
        $this->id = $manifest->id;
        $this->name = $manifest->name;
        $this->description = $manifest->description;
        $this->author = $manifest->author;
        $this->type = $manifest->type;
        $this->provider = $manifest->provider;
        $this->path = $manifest->path;
        $this->version = $manifest->version;
        $this->compatible_with = $manifest->compatible_with ?? null;
        $this->requires_extensions = $manifest->requires_extensions;
        $this->requires_packages = $manifest->requires_packages;
        $this->meta = $manifest->meta;

        $this->enabled = $enabled;
        $this->broken = $broken;
        $this->issue = $issue;
    }

    public static function fromManifest(ManifestValue $manifest, bool $enabled = false, bool $broken = false, ?string $issue = null): self
    {
        return new self($manifest, $enabled, $broken, $issue);
    }

    // ===== Explicit API (kept for BC with your existing code) =====

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return string[]
     */
    public function requires(): array
    {
        return $this->requires_extensions;
    }

    /**
     * @return array<string,string>
     */
    public function requiresPackages(): array
    {
        return $this->requires_packages;
    }

    public function compatibleWith(): string
    {
        return $this->compatible_with ?? '1.0.0';
    }

    /**
     * Get arbitrary meta value or all meta when key is null.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function meta(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->meta;
        }

        return $this->meta[$key] ?? $default;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isBroken(): bool
    {
        return $this->broken;
    }

    public function issue(): ?string
    {
        return $this->issue;
    }

    public function withEnabled(bool $enabled): self
    {
        $clone = clone $this;
        $clone->enabled = $enabled;

        return $clone;
    }

    public function withBroken(bool $broken, ?string $issue = null): self
    {
        $clone = clone $this;
        $clone->broken = $broken;
        $clone->issue = $issue;

        return $clone;
    }

    // ===== Operations delegated to service =====

    public function enable(): OpResult
    {
        return ExtensionsFacade::enable($this->id);
    }

    public function disable(): OpResult
    {
        return ExtensionsFacade::disable($this->id);
    }

    public function installDeps(): OpResult
    {
        return ExtensionsFacade::installDependencies($this->id);
    }

    public function delete(): OpResult
    {
        return ExtensionsFacade::delete($this->id);
    }

    public function migrate(): bool
    {
        return ExtensionsFacade::migrate($this->id);
    }

    /**
     * @return string[]
     */
    public function missingPackages(): array
    {
        return ExtensionsFacade::missingPackages($this->id);
    }

    /**
     * Export a flattened array view (manifest fields + flags).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'provider' => $this->provider,
            'path' => $this->path,
            'version' => $this->version,
            'compatible_with' => $this->compatible_with,
            'requires_extensions' => $this->requires_extensions,
            'requires_packages' => $this->requires_packages,
            'meta' => $this->meta,
            'enabled' => $this->enabled,
            'broken' => $this->broken,
            'issue' => $this->issue,
        ];
    }
}
