<?php

namespace App\Repository\Services\Admin\Product;

use App\Helpers\admin\FileManageHelper;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function paginate($perPage, $page, $search)
    {
        return DB::table('products as p')
            ->join('subcategories as sc', 'sc.id', '=', 'p.subcategory_id')
            ->join('categories as c', 'c.id', '=', 'sc.category_id')
            ->leftJoin('inventory as i', 'i.product_id', '=', 'p.id')
            ->select('p.id', 'p.name', 'p.sku', 'p.price', 'p.is_active', 'p.updated_at', 'c.name as category_name', 'sc.name as subcategory_name', 'i.stock_quantity')
            ->where(function ($query) use ($search) {
                $query->where('p.name', 'like', '%' . $search . '%')
                    ->orWhere('p.sku', 'like', '%' . $search . '%')
                    ->orWhere('c.name', 'like', '%' . $search . '%')
                    ->orWhere('sc.name', 'like', '%' . $search . '%');
            })
            ->orderByDesc('p.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function options()
    {
        return [
            'categories' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
            'subcategories' => DB::table('subcategories')->orderBy('name')->get(['id', 'category_id', 'name']),
            'sections' => DB::table('sections')->where('is_active', 1)->orderBy('display_order')->get(['id', 'name']),
        ];
    }

    public function find($id)
    {
        $product = DB::table('products as p')->join('subcategories as sc', 'sc.id', '=', 'p.subcategory_id')
            ->select('p.*', 'sc.category_id')->where('p.id', $id)->first();
        if (!$product) return null;

        $product->inventory = DB::table('inventory')->where('product_id', $id)->first(['stock_quantity', 'warehouse_location']);
        $product->images = DB::table('product_images')->where('product_id', $id)->orderByDesc('is_primary')->orderBy('id')->get(['id', 'image_url', 'is_primary']);
        $product->discount = DB::table('product_discounts')->where('product_id', $id)->orderByDesc('id')->first(['discount_type', 'discount_value', 'start_date', 'end_date']);
        $sections = DB::table('section_products')->where('product_id', $id)->orderBy('display_order')->orderBy('id')->get(['section_id', 'display_order']);
        $product->section_ids = $sections->pluck('section_id')->values();
        $product->display_order = $sections->first() ? $sections->first()->display_order : 1;
        $product->stock_quantity = $product->inventory ? $product->inventory->stock_quantity : 0;
        $product->warehouse_location = $product->inventory ? $product->inventory->warehouse_location : null;
        unset($product->inventory);
        return $product;
    }

    public function save(array $data, $id = null)
    {
        return DB::transaction(function () use ($data, $id) {
            $productData = [
                'subcategory_id' => $data['subcategory_id'], 'name' => $data['name'], 'description' => $data['description'] ?? null,
                'price' => $data['price'], 'sku' => $data['sku'] ?: null, 'is_active' => $data['is_active'],
            ];
            if ($id) { DB::table('products')->where('id', $id)->update($productData); $productId = $id; }
            else { $productId = DB::table('products')->insertGetId($productData); }

            DB::table('inventory')->where('product_id', $productId)->delete();
            DB::table('inventory')->insert(['product_id' => $productId, 'stock_quantity' => $data['stock_quantity'], 'warehouse_location' => $data['warehouse_location'] ?? null]);
            if (!empty($data['images'])) {
                $oldImages = DB::table('product_images')->where('product_id', $productId)->pluck('image_url');
                DB::table('product_images')->where('product_id', $productId)->delete();
                foreach ($data['images'] as $index => $image) {
                    $path = FileManageHelper::uploadFile('products', $image);
                    DB::table('product_images')->insert(['product_id' => $productId, 'image_url' => $path, 'is_primary' => $index === 0]);
                }
                foreach ($oldImages as $path) FileManageHelper::deleteFile($path);
            }
            DB::table('product_discounts')->where('product_id', $productId)->delete();
            if (!empty($data['discount_type'])) DB::table('product_discounts')->insert(['product_id' => $productId, 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value'], 'start_date' => $data['discount_start_date'] ?: null, 'end_date' => $data['discount_end_date'] ?: null]);
            DB::table('section_products')->where('product_id', $productId)->delete();
            foreach ($data['section_ids'] as $sectionId) DB::table('section_products')->insert(['product_id' => $productId, 'section_id' => $sectionId, 'display_order' => $data['display_order']]);
            return $this->find($productId);
        });
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            if (!DB::table('products')->where('id', $id)->exists()) return false;
            $images = DB::table('product_images')->where('product_id', $id)->pluck('image_url');
            foreach (['product_images', 'product_discounts', 'inventory', 'section_products', 'product_reviews', 'supplier_products'] as $table) DB::table($table)->where('product_id', $id)->delete();
            foreach ($images as $path) FileManageHelper::deleteFile($path);
            return (bool) DB::table('products')->where('id', $id)->delete();
        });
    }
}
