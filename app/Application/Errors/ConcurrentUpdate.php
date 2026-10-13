<?php

declare(strict_types=1);

namespace App\Application\Errors;

/** Raised by an adapter when optimistic concurrency detects that a book changed since it was read. */
final class ConcurrentUpdate extends \RuntimeException
{
}
