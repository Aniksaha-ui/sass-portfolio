<?php

namespace App\Repository\Services\Admin\Subcategory;

use Illuminate\Support\Facades\DB;

class SubcategoryService
{
    public function paginate(int $perPage, int $page, string $search)
    {
        return DB::table('subcategories as s')->join('categories as c', 'c.id', '=', 's.category_id')->where(fn ($q) => $q->where('s.name', 'like', "%{$search}%")->orWhere('s.description', 'like', "%{$search}%")->orWhere('c.name', 'like', "%{$search}%"))->orderBy('c.name')->orderBy('s.name')->paginate($perPage, ['s.id', 's.category_id', 's.name', 's.description', 's.created_at', 's.updated_at', 'c.name as category_name'], 'page', $page);
    }

    public function options()
    {
        return DB::table('categories')->orderBy('name')->get(['id', 'name']);
    }

    public function find(int $id)
    {
        return DB::table('subcategories as s')->join('categories as c', 'c.id', '=', 's.category_id')->where('s.id', $id)->first(['s.id', 's.category_id', 's.name', 's.description', 's.created_at', 's.updated_at', 'c.name as category_name']);
    }

    public function create(array $data)
    {
        $id = DB::table('subcategories')->insertGetId($data);

        return $this->find($id);
    }

    public function update(int $id, array $data)
    {
        DB::table('subcategories')->where('id', $id)->update($data);

        return $this->find($id);
    }

    public function delete(int $id)
    {
        return DB::table('subcategories')->where('id', $id)->delete();
    }
}
