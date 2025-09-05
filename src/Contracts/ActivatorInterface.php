<?php

namespace Gigabait93\Extensions\Contracts;

/**
 * Defines persistence for extension activation states.
 */
interface ActivatorInterface
{
    /**
     * Get activation statuses for all extensions.
     *
     * @return array<string, bool> Map of extension name => active flag
     */
    public function getStatuses(): array;

    /**
     * Set activation status for the given extension.
     *
     * @param string $extension Fully qualified extension name (e.g. "Vendor/Name")
     * @param bool $status Whether the extension is active
     * @return bool Whether the status was saved successfully
     */
    public function setStatus(string $extension, bool $status): bool;
}
