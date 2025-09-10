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

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function getData(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }
        
        return $this->data[$key] ?? $default;
    }

    public function withData(array $data): self
    {
        return new self($this->success, $this->message, array_merge($this->data, $data), $this->errorCode);
    }

    public function withMessage(string $message): self
    {
        return new self($this->success, $message, $this->data, $this->errorCode);
    }

    public function getCode(): ?string
    {
        return $this->errorCode;
    }

    public function hasCode(string $code): bool
    {
        return $this->errorCode === $code;
    }

    public function throw(?string $message = null): void
    {
        if ($this->isFailure()) {
            throw new \RuntimeException($message ?? $this->message ?? 'Operation failed');
        }
    }
}
