<?php

declare(strict_types=1);

namespace App\Adapters\Http;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Request validation with Spanish issue texts (the contract wants Spanish user-facing messages). */
final class Input
{
    private const ISSUES = [
        'Required' => 'es obligatorio',
        'String' => 'debe ser texto',
        'Integer' => 'debe ser un entero',
        'Uuid' => 'debe ser un UUID',
        'Date' => 'debe ser una fecha válida',
        'DateFormat' => 'debe tener formato AAAA-MM-DD',
        'Min' => 'valor por debajo del mínimo',
        'Max' => 'valor por encima del máximo',
        'In' => 'valor no permitido',
        'Array' => 'debe ser una lista',
        'Size' => 'tamaño inválido',
    ];

    /**
     * @param array<string, string|list<string>> $rules
     * @return array<string, mixed>
     */
    public static function validate(Request $request, array $rules, ?array $data = null): array
    {
        $validator = Validator::make($data ?? $request->all(), $rules);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->failed() as $field => $failed) {
                foreach (array_keys($failed) as $rule) {
                    $errors[$field][] = self::ISSUES[$rule] ?? 'valor inválido';
                }
            }
            throw ValidationException::withMessages($errors);
        }

        return $validator->validated();
    }

    /** @return array{page: int, size: int} */
    public static function paging(Request $request, int $defaultSize = 20): array
    {
        $v = self::validate($request, ['page' => 'sometimes|integer|min:1', 'size' => 'sometimes|integer|min:1|max:50'], $request->query());

        return ['page' => (int) ($v['page'] ?? 1), 'size' => (int) ($v['size'] ?? $defaultSize)];
    }

    public static function date(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC')) ?: throw new \InvalidArgumentException('date');
    }
}
