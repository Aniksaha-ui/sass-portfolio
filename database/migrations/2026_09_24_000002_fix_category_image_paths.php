<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixCategoryImagePaths extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE categories MODIFY image VARCHAR(255) NULL');
        }

        $disk = Storage::disk('public');
        $files = $disk->files('categories');
        foreach (DB::table('categories')->whereNotNull('image')->get(['id', 'image']) as $category) {
            if ($disk->exists($category->image) || strlen($category->image) !== 20) {
                continue;
            }
            $matches = array_values(array_filter($files, fn ($path) => strpos($path, $category->image) === 0));
            if (count($matches) === 1) {
                DB::table('categories')->where('id', $category->id)->update(['image' => $matches[0]]);
            }
        }
    }

    public function down(): void
    {
        // Do not truncate image paths or discard recovered filenames.
    }
}
