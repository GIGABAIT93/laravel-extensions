<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Contracts;

interface ActivatorContract
{
    /** Mark extension as enabled */
    public function enable(string $id, ?string $type = null): void;

    /** Mark extension as disabled */
    public function disable(string $id, ?string $type = null): void;

    /** Check if extension enabled */
    public function isEnabled(string $id): bool;

    /** Remove extension state record (e.g., if deleted) */
    public function remove(string $id): void;

    /**
     * Set or update state for discovered extension.
     * Implementations may persist `type` to support switch_types constraints.
     */
    public function set(string $id, bool $enabled, ?string $type = null): void;

    /**
     * Return map of id => [enabled(bool), type(?string)].
     * @return array<string, array{enabled: bool, type: string|null}>
     */
    public function statuses(): array;
}
