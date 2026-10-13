<?php

declare(strict_types=1);

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Input;
use App\Adapters\Http\Presenter;
use App\Application\Services\CreateBookService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\GetBookCoverService;
use App\Application\Services\GetBookService;
use App\Application\Services\ListCategoriesService;
use App\Application\Services\RemoveBookCoverService;
use App\Application\Services\SearchBooksService;
use App\Application\Services\SetBookCoverService;
use App\Application\Services\UpdateBookService;
use App\Domain\Category;
use App\Domain\Errors\ValidationFailed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BookController
{
    private const FIELDS = [
        'title' => 'required|string',
        'author' => 'required|string',
        'category_id' => 'required|string',
        'total_copies' => 'required|integer|min:1',
    ];

    public function categories(ListCategoriesService $list): JsonResponse
    {
        return response()->json(array_map(static fn (Category $c): array => ['id' => $c->id, 'name' => $c->name], $list->execute()));
    }

    public function index(Request $request, SearchBooksService $search): JsonResponse
    {
        $v = Input::validate($request, ['q' => 'sometimes|nullable|string', 'category_id' => 'sometimes|nullable|uuid'], $request->query());
        $paging = Input::paging($request);
        $page = $search->execute($v['q'] ?? null, $v['category_id'] ?? null, $paging['page'], $paging['size']);

        return response()->json(Presenter::page($page, [Presenter::class, 'book']));
    }

    public function show(string $id, GetBookService $get): JsonResponse
    {
        return response()->json(Presenter::book($get->execute($id)));
    }

    public function store(Request $request, CreateBookService $create): JsonResponse
    {
        $v = Input::validate($request, self::FIELDS + ['isbn' => 'required']);
        $book = $create->execute($v['title'], $v['author'], (string) (is_scalar($v['isbn']) ? $v['isbn'] : ''), $v['category_id'], (int) $v['total_copies']);

        return response()->json(Presenter::book($book), 201);
    }

    public function update(string $id, Request $request, UpdateBookService $update): JsonResponse
    {
        $v = Input::validate($request, self::FIELDS);
        $book = $update->execute($id, $v['title'], $v['author'], $v['category_id'], (int) $v['total_copies']);

        return response()->json(Presenter::book($book));
    }

    public function destroy(string $id, DeleteBookService $delete): Response
    {
        $delete->execute($id);

        return response()->noContent();
    }

    public function cover(string $id, GetBookCoverService $get): Response
    {
        $cover = $get->execute($id);

        return response($cover->bytes, 200, ['Content-Type' => $cover->mime, 'Cache-Control' => 'private, max-age=300']);
    }

    public function setCover(string $id, Request $request, SetBookCoverService $set): JsonResponse
    {
        $file = $request->file('file');
        if ($file === null || ! $file->isValid()) {
            throw new ValidationFailed('Debe enviar una portada válida de máximo 2 MB.', [['field' => 'file', 'issue' => 'archivo ausente, inválido o mayor a 2 MB']]);
        }
        $bytes = file_get_contents($file->getRealPath());

        return response()->json(Presenter::book($set->execute($id, $bytes === false ? '' : $bytes)));
    }

    public function removeCover(string $id, RemoveBookCoverService $remove): Response
    {
        $remove->execute($id);

        return response()->noContent();
    }
}
