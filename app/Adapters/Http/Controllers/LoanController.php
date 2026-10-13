<?php

declare(strict_types=1);

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Input;
use App\Adapters\Http\Presenter;
use App\Application\Ports\Clock;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\Errors\ValidationFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController
{
    public function __construct(private readonly Clock $clock)
    {
    }

    public function store(Request $request, LendBooksService $lend): JsonResponse
    {
        $v = Input::validate($request, [
            'member_id' => 'required|string',
            'items' => 'required|array|min:1|max:3',
            'items.*' => 'array',
            'items.*.book_id' => 'required|string',
        ]);
        $bookIds = array_map(static fn (array $i): string => $i['book_id'], array_values($v['items']));
        $loan = $lend->execute($v['member_id'], $bookIds, $request->attributes->get('claims')->userId);

        return response()->json(Presenter::loan($loan, $this->clock->now()), 201);
    }

    public function index(Request $request, ListLoansService $list): JsonResponse
    {
        $v = Input::validate($request, [
            'from' => 'sometimes|nullable|date_format:Y-m-d',
            'to' => 'sometimes|nullable|date_format:Y-m-d',
            'status' => 'sometimes|nullable|string',
            'member_id' => 'sometimes|nullable|string',
        ], $request->query());
        $paging = Input::paging($request);
        $page = $list->execute(
            isset($v['from']) ? Input::date($v['from']) : null,
            isset($v['to']) ? Input::date($v['to']) : null,
            $v['status'] ?? null,
            $v['member_id'] ?? null,
            $paging['page'],
            $paging['size'],
        );
        $now = $this->clock->now();

        return response()->json(Presenter::page($page, static fn ($l): array => Presenter::loan($l, $now)));
    }

    public function show(string $id, GetLoanService $get): JsonResponse
    {
        return response()->json(Presenter::loan($get->execute($id), $this->clock->now()));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $return): JsonResponse
    {
        return response()->json(Presenter::item($return->execute($loanId, $itemId)));
    }

    public function overdue(Request $request, OverdueService $overdue): JsonResponse
    {
        $paging = Input::paging($request, 50);
        $all = $overdue->execute();
        $slice = array_slice($all, ($paging['page'] - 1) * $paging['size'], $paging['size']);

        return response()->json([
            'items' => array_map([Presenter::class, 'overdue'], $slice),
            'page' => $paging['page'],
            'size' => $paging['size'],
            'total' => count($all),
        ]);
    }
}
