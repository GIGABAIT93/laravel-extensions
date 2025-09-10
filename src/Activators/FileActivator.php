<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorContract;

class FileActivator implements ActivatorContract
{
    private string $file;

    private array $data = [];

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? (string) config('extensions.json_file');
        $this->load();
    }

    public function enable(string $id, ?string $type = null): void
    {
        $this->set($id, true, $type ?? ($this->data[$id]['type'] ?? null));
        $this->save();
    }

    public function disable(string $id, ?string $type = null): void
    {
        $this->set($id, false, $type ?? ($this->data[$id]['type'] ?? null));
        $this->save();
    }

    public function isEnabled(string $id): bool
    {
        return (bool) ($this->data[$id]['enabled'] ?? false);
    }

    public function remove(string $id): void
    {
        unset($this->data[$id]);
        $this->save();
    }

    public function set(string $id, bool $enabled, ?string $type = null): void
    {
        $this->data[$id] = [
            'enabled' => $enabled,
            'type' => $type,
        ];
    }

    public function statuses(): array
    {
        return $this->data;
    }

    private function load(): void
    {
        $file = $this->file;
        if (!$file) {
            return;
        }
        if (!is_file($file)) {
            return;
        }
        $fh = @fopen($file, 'r');
        if ($fh === false) {
            return;
        }
        @flock($fh, LOCK_SH);
        $raw = stream_get_contents($fh) ?: '';
        @flock($fh, LOCK_UN);
        @fclose($fh);
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $this->data = $json;
            }
        }
    }

    private function save(): void
    {
        $file = $this->file;
        if (!$file) {
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0o775, true);
        }
        $tmp = $file . '.tmp';
        $contents = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $fh = @fopen($tmp, 'w');
        if ($fh === false) {
            return;
        }
        @flock($fh, LOCK_EX);
        fwrite($fh, (string) $contents);
        fflush($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        @rename($tmp, $file);
    }
}
