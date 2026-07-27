<?php

namespace App\Repository\Services\Admin\Coupon;

use Illuminate\Support\Facades\DB;

class CouponService
{
    public function paginate($perPage, $page, $search)
    {
        return DB::table('coupons')
            ->where('code', 'like', '%' . $search . '%')
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'code', 'discount_type', 'discount_value', 'start_date', 'end_date', 'max_usage', 'created_at', 'updated_at'], 'page', $page);
    }

    public function find($id)
    {
        return DB::table('coupons')->where('id', $id)->first(['id', 'code', 'discount_type', 'discount_value', 'start_date', 'end_date', 'max_usage', 'created_at', 'updated_at']);
    }

    public function create(array $data)
    {
        $id = DB::table('coupons')->insertGetId($data);
        return $this->find($id);
    }

    public function update($id, array $data)
    {
        DB::table('coupons')->where('id', $id)->update($data);
        return $this->find($id);
    }

    public function delete($id)
    {
        return DB::table('coupons')->where('id', $id)->delete();
    }
}
