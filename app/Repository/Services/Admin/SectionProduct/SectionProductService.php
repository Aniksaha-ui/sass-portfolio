<?php

namespace App\Repository\Services\Admin\SectionProduct;

use Illuminate\Support\Facades\DB;

class SectionProductService
{
    public function paginate(int $perPage, int $page, string $search)
    {
        return DB::table('section_products as sp')->join('sections as s', 's.id', '=', 'sp.section_id')->join('products as p', 'p.id', '=', 'sp.product_id')->leftJoin('product_bundles as b', 'b.id', '=', 'sp.bundle_id')->where(fn($q) => $q->where('s.name', 'like', "%{$search}%")->orWhere('p.name', 'like', "%{$search}%")->orWhere('b.name', 'like', "%{$search}%"))->orderBy('s.display_order')->orderBy('sp.display_order')->paginate($perPage, ['sp.id', 'sp.section_id', 'sp.product_id', 'sp.bundle_id', 'sp.display_order', 's.name as section_name', 'p.name as product_name', 'p.sku as product_sku', 'b.name as bundle_name'], 'page', $page);
    }

    public function options()
    {
        return ['sections' => DB::table('sections')->where('is_active', 1)->orderBy('display_order')->get(['id', 'name']), 'products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'sku']), 'bundles' => DB::table('product_bundles')->orderBy('name')->get(['id', 'name'])];
    }

    public function find(int $id)
    {
        return DB::table('section_products as sp')->join('sections as s', 's.id', '=', 'sp.section_id')->join('products as p', 'p.id', '=', 'sp.product_id')->leftJoin('product_bundles as b', 'b.id', '=', 'sp.bundle_id')->where('sp.id', $id)->first(['sp.id', 'sp.section_id', 'sp.product_id', 'sp.bundle_id', 'sp.display_order', 's.name as section_name', 'p.name as product_name', 'p.sku as product_sku', 'b.name as bundle_name']);
    }

    public function create(array $data)
    {
        $id = DB::table('section_products')->insertGetId($data);

        return $this->find($id);
    }

    public function update(int $id, array $data)
    {
        DB::table('section_products')->where('id', $id)->update($data);

        return $this->find($id);
    }

    public function delete(int $id)
    {
        return DB::table('section_products')->where('id', $id)->delete();
    }
}
