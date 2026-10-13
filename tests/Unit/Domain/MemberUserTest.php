<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Errors\ValidationFailed;
use App\Domain\Member;
use App\Domain\User;
use App\Domain\ValueObject\DocumentNumber;
use PHPUnit\Framework\TestCase;

final class MemberUserTest extends TestCase
{
    public function test_username_is_trimmed_and_lowercased(): void
    {
        $u = User::createLibrarian('u1', 'Ana ', 'hash', 'librarian');
        $this->assertSame('ana', $u->username);
        $this->assertSame('librarian', $u->role);
    }

    public function test_admin_role_requested_through_api_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        User::createLibrarian('u1', 'ana', 'hash', 'admin');
    }

    public function test_invalid_role_is_rejected(): void
    {
        $this->expectException(ValidationFailed::class);
        new User('u1', 'ana', 'hash', 'root');
    }

    public function test_member_requires_name_and_document(): void
    {
        $m = Member::create('m1', ' Luis Pérez ', DocumentNumber::fromString('1020'));
        $this->assertSame('Luis Pérez', $m->fullName);
        $this->expectException(ValidationFailed::class);
        Member::create('m2', '  ', DocumentNumber::fromString('1'));
    }
}
