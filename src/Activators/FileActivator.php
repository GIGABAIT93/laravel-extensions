<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Activators;

use Gigabait93\Extensions\Contracts\ActivatorContract;
use Illuminate\Support\Facades\Log;

class FileActivator implements ActivatorContract
{
    private string $file;

    private array $data = [];

    private ?int $lastLoadedMtime = null;

    public function __construct(?string $file = null)
    {
        $this->file = $file ?? (string) config('extensions.json_file');
        $this->load();
    }

    public function enable(string $id, ?string $type = null): void
    {
        $this->refreshIfChanged();
        $this->set($id, true, $type ?? ($this->data[$id]['type'] ?? null));
        $this->save();
    }

    public function disable(string $id, ?string $type = null): void
    {
        $this->refreshIfChanged();
        $this->set($id, false, $type ?? ($this->data[$id]['type'] ?? null));
        $this->save();
    }

    public function isEnabled(string $id): bool
    {
        $this->refreshIfChanged();

        return (bool) ($this->data[$id]['enabled'] ?? false);
    }

    public function remove(string $id): void
    {
        $this->refreshIfChanged();
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
        $this->refreshIfChanged();

        return $this->data;
    }

    private function refreshIfChanged(): void
    {
        $file = $this->file;
        if ($file === '' || !is_file($file)) {
            return;
        }

        $mtime = @filemtime($file);
        if ($mtime === false) {
            return;
        }

        if ($this->lastLoadedMtime === null || $mtime !== $this->lastLoadedMtime) {
            $this->load();
        }
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
            Log::warning('FileActivator failed to open state file for reading', ['file' => $file]);

            return;
        }
        if (!@flock($fh, LOCK_SH)) {
            Log::warning('FileActivator failed to acquire shared lock', ['file' => $file]);
        }
        $raw = stream_get_contents($fh) ?: '';
        @flock($fh, LOCK_UN);
        @fclose($fh);
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $this->data = $json;
            } else {
                Log::warning('FileActivator state file contains invalid JSON', ['file' => $file]);
            }
        }

        $mtime = @filemtime($file);
        $this->lastLoadedMtime = $mtime === false ? null : $mtime;
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
        if ($contents === false) {
            Log::warning('FileActivator failed to encode statuses to JSON', ['file' => $file]);

            return;
        }
        $fh = @fopen($tmp, 'w');
        if ($fh === false) {
            Log::warning('FileActivator failed to open temp file for writing', ['file' => $tmp]);

            return;
        }
        if (!@flock($fh, LOCK_EX)) {
            Log::warning('FileActivator failed to acquire exclusive lock', ['file' => $tmp]);
        }
        if (fwrite($fh, (string) $contents) === false) {
            Log::warning('FileActivator failed to write statuses', ['file' => $tmp]);
            @fclose($fh);
            @unlink($tmp);

            return;
        }
        fflush($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
        if (!@rename($tmp, $file)) {
            Log::warning('FileActivator failed to move temp file to destination', [
                'tmp' => $tmp,
                'file' => $file,
            ]);
            @unlink($tmp);

            return;
        }

        $mtime = @filemtime($file);
        $this->lastLoadedMtime = $mtime === false ? null : $mtime;
    }
}
