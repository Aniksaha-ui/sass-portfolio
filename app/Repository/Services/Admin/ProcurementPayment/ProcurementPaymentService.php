<?php

namespace App\Repository\Services\Admin\ProcurementPayment;

use Illuminate\Support\Facades\DB;

class ProcurementPaymentService
{
    public function paginate($perPage, $page, $search)
    {
        return DB::table('procurement_payments as pp')->join('procurements as p', 'p.id', '=', 'pp.procurement_id')->join('company_accounts as ca', 'ca.id', '=', 'pp.company_account_id')->where(fn($q) => $q->where('p.procurement_number', 'like', "%{$search}%")->orWhere('ca.account_name', 'like', "%{$search}%")->orWhere('pp.payment_reference', 'like', "%{$search}%"))->orderByDesc('pp.paid_at')->paginate($perPage, ['pp.*', 'p.procurement_number', 'ca.account_name', 'ca.account_number'], 'page', $page);
    }

    public function find($id)
    {
        return DB::table('procurement_payments as pp')->join('procurements as p', 'p.id', '=', 'pp.procurement_id')->join('company_accounts as ca', 'ca.id', '=', 'pp.company_account_id')->where('pp.id', $id)->first(['pp.*', 'p.procurement_number', 'ca.account_name', 'ca.account_number']);
    }

    public function options()
    {
        return ['procurements' => DB::table('procurements')->orderByDesc('id')->get(['id', 'procurement_number']), 'accounts' => DB::table('company_accounts')->orderBy('account_name')->get(['id', 'account_name', 'account_number', 'amount'])];
    }

    public function create($data)
    {
        $id = DB::table('procurement_payments')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);

        return $this->find($id);
    }

    public function update($id, $data)
    {
        DB::table('procurement_payments')->where('id', $id)->update([...$data, 'updated_at' => now()]);

        return $this->find($id);
    }

    public function delete($id)
    {
        return DB::table('procurement_payments')->where('id', $id)->delete();
    }
}
