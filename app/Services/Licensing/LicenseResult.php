<?php

namespace App\Services\Licensing;

final class LicenseResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?array $license = null,
    ) {}

    public function valid(): bool
    {
        return $this->status === 'valid';
    }
}
