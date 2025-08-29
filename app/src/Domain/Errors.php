<?php
namespace App\Domain;

final class Errors
{
    public const INVALID_ARGUMENT = 'INVALID_ARGUMENT';
    public const OUT_OF_RANGE = 'OUT_OF_RANGE';
    public const RATE_LIMITED = 'RATE_LIMITED';
    public const INTERNAL = 'INTERNAL';

    public static function format(string $code, string $message): array
    {
        return ['error' => ['code' => $code, 'message' => $message]];
    }
}
