<?php

declare(strict_types=1);

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Input;
use App\Application\Dto\ReportRow;
use App\Application\Services\MostBorrowedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $report): JsonResponse
    {
        $v = Input::validate($request, ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d'], $request->query());
        $result = $report->execute(Input::date($v['from']), Input::date($v['to']));

        return response()->json([
            'from' => $result['range']->fromDate,
            'to' => $result['range']->toDate,
            'rows' => array_map(static fn (ReportRow $r): array => [
                'book_id' => $r->bookId,
                'book_title' => $r->bookTitle,
                'category_name' => $r->categoryName,
                'times_borrowed' => $r->timesBorrowed,
            ], $result['rows']),
        ]);
    }
}
