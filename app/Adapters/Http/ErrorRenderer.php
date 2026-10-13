<?php

declare(strict_types=1);

namespace App\Adapters\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Translates every failure into the single error body {code, message, details} (api-contract §1).
 * Never leaks traces, SQL or server paths.
 */
final class ErrorRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(fn () => true);
        $exceptions->render(fn (Throwable $e, Request $request) => self::render($e));
        $exceptions->dontReport([ValidationException::class]);
    }

    public static function body(string $code, string $message, array $details = []): array
    {
        return ['code' => $code, 'message' => $message, 'details' => array_values($details)];
    }

    public static function json(int $status, string $code, string $message, array $details = []): JsonResponse
    {
        return response()->json(self::body($code, $message, $details), $status);
    }

    public static function render(Throwable $e): JsonResponse
    {
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
                $status === 413 => self::json(422, 'VALIDATION_ERROR', 'El archivo es demasiado grande.'),
                $status >= 500 => self::json(500, 'INTERNAL_ERROR', 'Error interno del servidor.'),
                default => self::json($status, 'HTTP_ERROR', 'La solicitud no pudo procesarse.'),
            };
        }

        return self::json(500, 'INTERNAL_ERROR', 'Error interno del servidor.');
    }
}
