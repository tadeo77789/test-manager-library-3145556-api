<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Adapters\Persistence\PgBookRepository;
use App\Adapters\Persistence\PgLoanRepository;
use App\Adapters\Persistence\PgMemberRepository;
use App\Adapters\Persistence\PgReportReader;
use App\Adapters\Persistence\PgUserRepository;
use App\Adapters\Security\Argon2idPasswordHasher;
use App\Adapters\Security\JwtTokenIssuer;
use App\Adapters\Storage\DiskCoverStorage;
use App\Adapters\System\SystemClock;
use App\Adapters\System\UuidGenerator;
use App\Application\Ports\BookRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\IdGenerator;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\ReportReader;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Single composition root (Constitution Art. III): the only place where ports meet adapters.
 * Application services are auto-wired by the container from their port-typed constructors.
 */
final class PortBindingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn () => new SystemClock((int) config('library.clock_offset_days')));
        $this->app->singleton(IdGenerator::class, fn () => new UuidGenerator());
        $this->app->singleton(PasswordHasher::class, fn () => new Argon2idPasswordHasher());
        $this->app->singleton(TokenIssuer::class, fn ($app) => new JwtTokenIssuer(
            (string) config('library.jwt_secret'),
            $app->make(Clock::class),
            (int) config('library.jwt_ttl_seconds'),
        ));
        $this->app->singleton(CoverStorage::class, fn ($app) => new DiskCoverStorage(
            (string) config('library.covers_dir'),
            storage_path('covers'),
            $app->make(IdGenerator::class),
        ));

        $this->app->singleton(BookRepository::class, fn () => new PgBookRepository());
        $this->app->singleton(MemberRepository::class, fn () => new PgMemberRepository());
        $this->app->singleton(UserRepository::class, fn () => new PgUserRepository());
        $this->app->singleton(LoanRepository::class, fn () => new PgLoanRepository());
        $this->app->singleton(ReportReader::class, fn () => new PgReportReader());
    }
}
