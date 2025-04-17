<?php

namespace Gigabait93\Extensions\Contracts;

interface ActivatorInterface
{
    /**
     * Returns all extensions statuses
     *
     * @return array<string, bool>  ['package/name' => true|false, …]
     */
    public function getStatuses(): array;

    /**
     * Establishes extension status
     *
     * @param  string   $extension
     * @param  bool     $status
     * @return bool     чи вдалося зберегти?
     */
    public function setStatus(string $extension, bool $status): bool;
}
