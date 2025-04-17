<?php

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorInterface;
use Illuminate\Support\Facades\File;

class FileActivator implements ActivatorInterface
{
    protected string $jsonFile;

    public function __construct()
    {
        $config = config('extensions');
        $this->jsonFile = $config['json_file'] ?? base_path('storage/extensions.json');
    }

    public function getStatuses(): array
    {
        if (! File::exists($this->jsonFile)) {
            return [];
        }

        $data = json_decode(File::get($this->jsonFile), true);
        return is_array($data) ? $data : [];
    }

    public function setStatus(string $extension, bool $status): bool
    {
        $statuses = $this->getStatuses();
        $statuses[$extension] = $status;
        return File::put($this->jsonFile, json_encode($statuses, JSON_PRETTY_PRINT)) !== false;
    }
}
