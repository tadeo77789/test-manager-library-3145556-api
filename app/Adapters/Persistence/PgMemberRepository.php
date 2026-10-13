<?php

declare(strict_types=1);

namespace App\Adapters\Persistence;

use App\Application\Dto\Page;
use App\Application\Ports\MemberRepository;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Member;
use App\Domain\ValueObject\DocumentNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PgMemberRepository implements MemberRepository
{
    public function add(Member $member): void
    {
        try {
            DB::table('library.member')->insert([
                'id' => $member->id,
                'full_name' => $member->fullName,
                'document_number' => $member->documentNumber->value,
            ]);
        } catch (QueryException $e) {
            if (Rows::isUniqueViolation($e, 'ux_member_document_number')) {
                throw new DocumentTaken();
            }
            throw $e;
        }
    }

    public function findById(string $id): ?Member
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $id) !== 1) {
            return null;
        }
        $r = DB::table('library.member')->where('id', $id)->first();

        return $r === null ? null : $this->map($r);
    }

    public function search(?string $q, int $page, int $size): Page
    {
        $query = DB::table('library.member');
        if ($q !== null) {
            $like = Rows::likeEscape($q);
            $query->where(static fn ($w) => $w->where('full_name', 'ilike', $like)->orWhere('document_number', 'ilike', $like));
        }
        $total = (clone $query)->count();
        $rows = $query->orderBy('full_name')->orderBy('id')->offset(($page - 1) * $size)->limit($size)->get();

        return new Page(array_map([$this, 'map'], $rows->all()), $page, $size, $total);
    }

    private function map(\stdClass $r): Member
    {
        return new Member($r->id, $r->full_name, DocumentNumber::fromString($r->document_number));
    }
}
