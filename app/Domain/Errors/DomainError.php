<?php

declare(strict_types=1);

namespace App\Domain\Errors;

/**
 * Base of every business error. The code matches api-contract.md section 2; the message is Spanish
 * (user-facing text required by the contract). Only the HTTP adapter maps it to a status.
 */
abstract class DomainError extends \RuntimeException
{
    /** @param list<array{field: string, issue: string}> $details */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return list<array{field: string, issue: string}> */
    public function details(): array
    {
        return $this->details;
    }
}
