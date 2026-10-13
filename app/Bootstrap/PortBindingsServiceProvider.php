<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Illuminate\Support\ServiceProvider;

/**
 * Single composition root (Constitution Art. III): the only place where ports meet adapters.
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
