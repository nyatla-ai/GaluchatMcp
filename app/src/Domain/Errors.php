<?php
namespace App\Domain;

final class Errors
{
    public const INVALID_COORD = 'INVALID_COORD';
    public const INVALID_REF = 'INVALID_REF';
    public const INVALID_T = 'INVALID_T';
    public const API_ERROR = 'API_ERROR';
    public const RATE_LIMIT = 'RATE_LIMIT';
    public const OUT_OF_COVERAGE = 'OUT_OF_COVERAGE';

    public static function format(string $code, string $message): array
    {
        return ['error' => ['code' => $code, 'message' => $message]];
    }
}
