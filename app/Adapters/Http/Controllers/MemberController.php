<?php

declare(strict_types=1);

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Input;
use App\Adapters\Http\Presenter;
use App\Application\Services\CreateMemberService;
use App\Application\Services\GetMemberService;
use App\Application\Services\SearchMembersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController
{
    public function index(Request $request, SearchMembersService $search): JsonResponse
    {
        $v = Input::validate($request, ['q' => 'sometimes|nullable|string'], $request->query());
        $paging = Input::paging($request);

        return response()->json(Presenter::page($search->execute($v['q'] ?? null, $paging['page'], $paging['size']), [Presenter::class, 'member']));
    }

    public function show(string $id, GetMemberService $get): JsonResponse
    {
        $detail = $get->execute($id);

        return response()->json(Presenter::member($detail->member) + ['active_loans' => $detail->activeLoans]);
    }

    public function store(Request $request, CreateMemberService $create): JsonResponse
    {
        $v = Input::validate($request, ['full_name' => 'required|string', 'document_number' => 'required']);
        $member = $create->execute($v['full_name'], (string) (is_scalar($v['document_number']) ? $v['document_number'] : ''));

        return response()->json(Presenter::member($member), 201);
    }
}
