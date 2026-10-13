<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Dto\MemberDetail;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\MemberRepository;
use App\Domain\Errors\NotFound;

final class GetMemberService
{
    public function __construct(private readonly MemberRepository $members, private readonly LoanRepository $loans)
    {
    }

    public function execute(string $id): MemberDetail
    {
        $member = $this->members->findById($id) ?? throw new NotFound('El socio no existe.');

        return new MemberDetail($member, $this->loans->countUnreturnedByMember($id));
    }
}
