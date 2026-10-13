<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\Dto\Page;
use App\Domain\Member;

interface MemberRepository
{
    /** @throws \App\Domain\Errors\DocumentTaken */
    public function add(Member $member): void;

    public function findById(string $id): ?Member;

    /** @return Page<Member> */
    public function search(?string $q, int $page, int $size): Page;
}
