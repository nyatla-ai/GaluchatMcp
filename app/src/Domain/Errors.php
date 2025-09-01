<?php
namespace App\Domain;

final class Errors
{
    public const INVALID_INPUT = 'INVALID_INPUT';
    public const API_ERROR = 'API_ERROR';
    public const OUT_OF_COVERAGE = 'OUT_OF_COVERAGE';
    public const RATE_LIMIT = 'RATE_LIMIT';
    public const INTERNAL = 'INTERNAL';

    public static function format(string $code, string $message): array
    {
        return ['error' => ['code' => $code, 'message' => $message]];
    }
}
