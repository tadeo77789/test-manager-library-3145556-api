<?php

declare(strict_types=1);

namespace App\Adapters\Http;

use App\Domain\Errors\DomainError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Translates every failure into the single error body {code, message, details} (api-contract section 1).
 * Never leaks traces, SQL or server paths. Only this adapter knows HTTP statuses.
 */
final class ErrorRenderer
{
    /** Business code -> HTTP status (api-contract section 2). */
    private const STATUS = [
        'INVALID_CREDENTIALS' => 401,
        'UNAUTHENTICATED' => 401,
        'FORBIDDEN_ROLE' => 403,
        'NOT_FOUND' => 404,
        'USERNAME_TAKEN' => 409,
        'ISBN_TAKEN' => 409,
        'DOCUMENT_TAKEN' => 409,
        'BOOK_HAS_OPEN_LOANS' => 409,
        'BOOK_NOT_AVAILABLE' => 409,
        'MEMBER_HAS_OVERDUE' => 409,
        'ITEM_ALREADY_RETURNED' => 409,
        'MEMBER_LIMIT_EXCEEDED' => 422,
        'DUPLICATE_BOOK_IN_LOAN' => 422,
        'VALIDATION_ERROR' => 422,
        'INVALID_DATE_RANGE' => 422,
    ];

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(fn (Throwable $e, Request $request) => self::render($e));
        $exceptions->dontReport([ValidationException::class, DomainError::class, AuthenticationException::class]);
    }

    /** @param list<array{field: string, issue: string}> $details */
    public static function json(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'details' => array_values($details)], $status);
    }

    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof DomainError) {
            return self::json(self::STATUS[$e->errorCode()] ?? 500, $e->errorCode(), $e->getMessage(), $e->details());
        }
        if ($e instanceof ValidationException) {
            $details = [];
            foreach ($e->errors() as $field => $issues) {
                foreach ($issues as $issue) {
                    $details[] = ['field' => $field, 'issue' => $issue];
                }
            }
            return self::json(422, 'VALIDATION_ERROR', 'Los datos enviados no son válidos.', $details);
        }
        if ($e instanceof AuthenticationException) {
            return self::json(401, 'UNAUTHENTICATED', 'Autenticación requerida.');
        }
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            return match (true) {
                $status === 404 => self::json(404, 'NOT_FOUND', 'El recurso solicitado no existe.'),
                $status === 405 => self::json(405, 'METHOD_NOT_ALLOWED', 'Método no permitido.'),
                $status === 413 => self::json(422, 'VALIDATION_ERROR', 'El archivo es demasiado grande.', [['field' => 'file', 'issue' => 'tamaño máximo 2 MB']]),
                $status >= 500 => self::json(500, 'INTERNAL_ERROR', 'Error interno del servidor.'),
                default => self::json($status, 'HTTP_ERROR', 'La solicitud no pudo procesarse.'),
            };
        }

        return self::json(500, 'INTERNAL_ERROR', 'Error interno del servidor.');
    }
}
