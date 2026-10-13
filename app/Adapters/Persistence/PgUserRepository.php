<?php

declare(strict_types=1);

namespace App\Adapters\Persistence;

use App\Application\Ports\UserRepository;
use App\Domain\Errors\UsernameTaken;
use App\Domain\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgUserRepository implements UserRepository
{
    public function add(User $user): void
    {
        try {
            DB::table('library.user')->insert([
                'id' => $user->id,
                'username' => $user->username,
                'password_hash' => $user->passwordHash,
                'role' => $user->role,
            ]);
        } catch (QueryException $e) {
            if (Rows::isUniqueViolation($e, 'ux_user_username')) {
                throw new UsernameTaken();
            }
            throw $e;
        }
    }

    public function findByUsername(string $normalizedUsername): ?User
    {
        $r = DB::table('library.user')->where('username', $normalizedUsername)->first();

        return $r === null ? null : new User($r->id, $r->username, $r->password_hash, $r->role);
    }
}
