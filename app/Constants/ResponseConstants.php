<?php

namespace App\Constants;

use ReflectionClass;

class ResponseConstants
{
    const SUCCESS = true;
    const FAILED = false;


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
  
}
