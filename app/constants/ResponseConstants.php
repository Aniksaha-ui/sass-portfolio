<?php

namespace App\Constants;

use ReflectionClass;

class ResponseConstants
{
    const SUCCESS = 'success';
    const FAILED = 'failed';
    const PENDING = 'pending';


    public static function success(): array
    {
        return [
            self::SUCCESS,
        ];
    }
    public static function failed(): array
    {
        return [
            self::FAILED,
        ];
    }
    public static function pending(): array
    {
        return [
            self::PENDING,
        ];
    }
}
