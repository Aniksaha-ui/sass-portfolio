<?php

namespace App\Repository\Services\Admin\Category;

use App\Helpers\admin\FileManageHelper;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function getCategories($perPage, $page, $search)
    {
        return DB::table('categories')
            ->where('name', 'like', '%' . $search . '%')
            ->orWhere('description', 'like', '%' . $search . '%')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'description', 'image', 'created_at', 'updated_at'], 'page', $page);
    }

    public function create(array $data)
    {
        $id = DB::table('categories')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'image' => ! empty($data['image']) ? FileManageHelper::uploadFile('categories', $data['image']) : null,
        ]);

        return $this->find($id);
    }

    public function find($id)
    {
        return DB::table('categories')
            ->select('id', 'name', 'description', 'image', 'created_at', 'updated_at')
            ->where('id', $id)
            ->first();
    }

    public function update($id, array $data)
    {
        $category = $this->find($id);
        $updates = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'updated_at' => now(),
        ];

        if (! empty($data['image'])) {
            $updates['image'] = FileManageHelper::uploadFile('categories', $data['image']);
        }

        DB::table('categories')->where('id', $id)->update($updates);

        if (! empty($updates['image']) && ! empty($category?->image)) {
            FileManageHelper::deleteFile($category->image);
        }

        return $this->find($id);
    }

    public function delete($id)
    {
        $category = $this->find($id);
        if (! $category) return false;

        $deleted = DB::table('categories')->where('id', $id)->delete();
        if ($deleted && ! empty($category->image)) {
            FileManageHelper::deleteFile($category->image);
        }

        return (bool) $deleted;
    }
}
