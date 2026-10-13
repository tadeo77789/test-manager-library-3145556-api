<?php

declare(strict_types=1);

namespace App\Adapters\Console;

use App\Application\Services\EnsureAdminService;
use Illuminate\Console\Command;

/** Runs at container start: creates the admin from ADMIN_USERNAME / ADMIN_PASSWORD when missing. */
final class CreateAdminCommand extends Command
{
    protected $signature = 'library:create-admin';
    protected $description = 'Create the initial admin user from ADMIN_USERNAME and ADMIN_PASSWORD if it does not exist';

    public function handle(EnsureAdminService $ensure): int
    {
        $username = (string) config('library.admin_username');
        $password = (string) config('library.admin_password');
        if ($username === '' || $password === '') {
            $this->error('ADMIN_USERNAME and ADMIN_PASSWORD must be set.');

            return self::FAILURE;
        }
        $this->info($ensure->execute($username, $password) ? 'Admin user created.' : 'Admin user already exists.');

        return self::SUCCESS;
    }
}
