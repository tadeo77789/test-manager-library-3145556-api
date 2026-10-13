<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\Page;
use App\Application\Ports\MemberRepository;

final class SearchMembersService
{
    public function __construct(private readonly MemberRepository $members)
    {
    }

    /** @return Page<\App\Domain\Member> */
    public function execute(?string $q, int $page, int $size): Page
    {
        return $this->members->search($q !== null && trim($q) !== '' ? trim($q) : null, $page, $size);
    }
}
