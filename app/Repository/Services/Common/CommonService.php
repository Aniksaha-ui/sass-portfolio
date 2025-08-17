<?php

namespace App\Repository\Services\Common;

class CommonService
{

    public function internalServerErrorResponse($status, $message, $data)
    {
        return [
            "status" => $status,
            "message" => $message,
            "data" => $data
        ];
    }
}
