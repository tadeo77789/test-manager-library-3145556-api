<?php

declare(strict_types=1);

namespace App\Application\Dto;

use App\Domain\Member;

final readonly class MemberDetail
{
    public function __construct(public Member $member, public int $activeLoans)
    {
    }
}
