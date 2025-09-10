<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Support;

readonly class OpResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public array $data = [],
        public ?string $errorCode = null
    ) {
    }

    public static function success(?string $message = null, array $data = []): self
    {
        return new self(true, $message, $data);
    }

    public static function failure(string $message, ?string $errorCode = null, array $data = []): self
    {
        return new self(false, $message, $data, $errorCode);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'error_code' => $this->errorCode,
        ];
    }
}
