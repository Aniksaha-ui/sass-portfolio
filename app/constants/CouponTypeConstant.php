<?php

namespace App\Constants;

use ReflectionClass;

class CouponTypeConstant
{
    const FLAT = 'flat';
    const PERCENTAGE = 'percentage';


    public static function flat(): array
    {
        return [
            self::FLAT,
        ];
    }
    public static function percentage(): array
    {
        return [
            self::PERCENTAGE,
        ];
    }
}
