<?php

namespace App\Repository\Services\Admin\Category;

use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function getCategories($perPage, $page, $search)
    {
        return DB::table('categories')
            ->where('name', 'like', '%' . $search . '%')
            ->orWhere('description', 'like', '%' . $search . '%')
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'description', 'created_at', 'updated_at'], 'page', $page);
    }

    public function create(array $data)
    {
        $id = DB::table('categories')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $this->find($id);
    }

    public function find($id)
    {
        return DB::table('categories')
            ->select('id', 'name', 'description', 'created_at', 'updated_at')
            ->where('id', $id)
            ->first();
    }

    public function update($id, array $data)
    {
        DB::table('categories')->where('id', $id)->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'updated_at' => now(),
        ]);

        return $this->find($id);
    }

    public function delete($id)
    {
        return DB::table('categories')->where('id', $id)->delete();
    }
}
