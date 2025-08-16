<?php

namespace App\Helpers\admin;

use Illuminate\Support\Facades\Storage;

class FileManageHelper
{

    static public function uploadFile($path, $file)
    {
        return Storage::disk('public')->put($path, $file);
    }

    static public function uploadFileUnderCurrentDate($path, $file)
    {
        return Storage::disk('public')->put($path . '/' . now()->toDateString(), $file);
    }

    static public function deleteFile($path)
    {
        Storage::disk('public')->delete($path);
    }
}
