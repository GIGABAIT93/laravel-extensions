<?php

namespace Gigabait93\Extensions\Activators;

use Illuminate\Support\Facades\File;

class FileActivator
{
    protected string $jsonFile;

    public function __construct()
    {
        $config = config('extensions');
        $this->jsonFile = $config['json_file'] ?? base_path('extensions.json');
    }

    public function getStatuses(): array
    {
        if (File::exists($this->jsonFile)) {
            $data = json_decode(File::get($this->jsonFile), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    public function setStatus(string $extension, bool $status): bool
    {
        $statuses = $this->getStatuses();
        $statuses[$extension] = $status;
        $this->saveStatuses($statuses);
        return true;
    }

    protected function saveStatuses(array $statuses): void
    {
        File::put($this->jsonFile, json_encode($statuses, JSON_PRETTY_PRINT));
    }
}
