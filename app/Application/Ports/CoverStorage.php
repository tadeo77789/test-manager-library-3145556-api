<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\StoredCover;

interface CoverStorage
{
    /**
     * Validates the binary by content (JPEG or PNG, max 2 MB) and returns an opaque key.
     *
     * @throws \App\Domain\Errors\ValidationFailed
     */
    public function store(string $bytes): string;

    public function read(string $key): ?StoredCover;

    public function delete(string $key): void;
}
