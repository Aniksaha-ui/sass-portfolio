<?php

namespace App\Repository\Services\Admin\ProductReview;

use Illuminate\Support\Facades\DB;

class ProductReviewService
{
    public function paginate(int $perPage, int $page, string $search)
    {
        return DB::table('product_reviews as pr')->join('users as u', 'u.id', '=', 'pr.user_id')->join('products as p', 'p.id', '=', 'pr.product_id')->where(fn ($q) => $q->where('u.name', 'like', "%{$search}%")->orWhere('u.email', 'like', "%{$search}%")->orWhere('p.name', 'like', "%{$search}%")->orWhere('pr.review', 'like', "%{$search}%"))->orderByDesc('pr.id')->paginate($perPage, ['pr.id', 'pr.user_id', 'pr.product_id', 'pr.rating', 'pr.review', 'pr.created_at', 'u.name as user_name', 'u.email as user_email', 'p.name as product_name', 'p.sku as product_sku'], 'page', $page);
    }

    public function options()
    {
        return ['users' => DB::table('users')->orderBy('name')->get(['id', 'name', 'email']), 'products' => DB::table('products')->orderBy('name')->get(['id', 'name', 'sku'])];
    }

    public function find(int $id)
    {
        return DB::table('product_reviews as pr')->join('users as u', 'u.id', '=', 'pr.user_id')->join('products as p', 'p.id', '=', 'pr.product_id')->where('pr.id', $id)->first(['pr.id', 'pr.user_id', 'pr.product_id', 'pr.rating', 'pr.review', 'pr.created_at', 'u.name as user_name', 'u.email as user_email', 'p.name as product_name', 'p.sku as product_sku']);
    }

    public function create(array $data)
    {
        $id = DB::table('product_reviews')->insertGetId([...$data, 'created_at' => now()]);

        return $this->find($id);
    }

    public function update(int $id, array $data)
    {
        DB::table('product_reviews')->where('id', $id)->update($data);

        return $this->find($id);
    }

    public function delete(int $id)
    {
        return DB::table('product_reviews')->where('id', $id)->delete();
    }
}
