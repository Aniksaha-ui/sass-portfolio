<?php

namespace App\Repository\Services\Admin\Product;

use Illuminate\Support\Facades\DB;

class ProductDiscountService
{
    public function paginate($perPage, $page, $search)
    {
        return DB::table('product_discounts as pd')
            ->join('products as p', 'p.id', '=', 'pd.product_id')
            ->select('pd.id', 'pd.product_id', 'pd.discount_type', 'pd.discount_value', 'pd.start_date', 'pd.end_date', 'p.name as product_name', 'p.sku', 'p.price as product_price')
            ->where(function ($query) use ($search) {
                $query->where('p.name', 'like', '%' . $search . '%')
                    ->orWhere('p.sku', 'like', '%' . $search . '%');
            })
            ->orderByDesc('pd.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function options()
    {
        return DB::table('products')->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'sku', 'price']);
    }

    public function find($id)
    {
        return DB::table('product_discounts as pd')
            ->join('products as p', 'p.id', '=', 'pd.product_id')
            ->where('pd.id', $id)
            ->select('pd.id', 'pd.product_id', 'pd.discount_type', 'pd.discount_value', 'pd.start_date', 'pd.end_date', 'p.name as product_name', 'p.sku', 'p.price as product_price')
            ->first();
    }

    public function create(array $data)
    {
        $id = DB::table('product_discounts')->insertGetId($data);
        return $this->find($id);
    }

    public function update($id, array $data)
    {
        DB::table('product_discounts')->where('id', $id)->update($data);
        return $this->find($id);
    }

    public function delete($id)
    {
        return DB::table('product_discounts')->where('id', $id)->delete();
    }
}
