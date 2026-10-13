<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\IdGenerator;
use App\Application\Ports\MemberRepository;
use App\Domain\Member;
use App\Domain\ValueObject\DocumentNumber;

/** HU-05: the unique document is enforced by the database and surfaced as DOCUMENT_TAKEN. */
final class CreateMemberService
{
    public function __construct(private readonly MemberRepository $members, private readonly IdGenerator $ids)
    {
    }

    public function execute(string $fullName, string $documentNumber): Member
    {
        $member = Member::create($this->ids->next(), $fullName, DocumentNumber::fromString($documentNumber));
        $this->members->add($member);

        return $member;
    }
}
