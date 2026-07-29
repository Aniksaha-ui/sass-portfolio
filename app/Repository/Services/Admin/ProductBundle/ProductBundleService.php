<?php

namespace App\Repository\Services\Admin\ProductBundle;

use Illuminate\Support\Facades\DB;

class ProductBundleService
{
    private const FIELDS = ['id', 'name', 'description', 'price', 'discount_price', 'is_active', 'created_at', 'updated_at'];

    public function paginate(int $perPage, int $page, string $search)
    {
        return DB::table('product_bundles')
            ->where('name', 'like', '%'.$search.'%')
            ->orderByDesc('id')
            ->paginate($perPage, self::FIELDS, 'page', $page);
    }

    public function find(int $id)
    {
        return DB::table('product_bundles')->where('id', $id)->first(self::FIELDS);
    }

    public function create(array $data)
    {
        $id = DB::table('product_bundles')->insertGetId($data);

        return $this->find($id);
    }

    public function update(int $id, array $data)
    {
        DB::table('product_bundles')->where('id', $id)->update($data);

        return $this->find($id);
    }

    public function delete(int $id)
    {
        return DB::table('product_bundles')->where('id', $id)->delete();
    }
}
