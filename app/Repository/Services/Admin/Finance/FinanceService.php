<?php

namespace App\Repository\Services\Admin\Finance;

use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function transactions(int $perPage, int $page, string $search)
    {
        return DB::table('transactions as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->select('t.*', 'o.status as order_status', 'u.name as customer_name', 'u.email as customer_email')
            ->where(function ($query) use ($search) {
                $query->where('t.id', 'like', "%{$search}%")
                    ->orWhere('t.order_id', 'like', "%{$search}%")
                    ->orWhere('u.name', 'like', "%{$search}%")
                    ->orWhere('t.bank_ssl_id', 'like', "%{$search}%")
                    ->orWhere('t.payment_method', 'like', "%{$search}%");
            })
            ->orderByDesc('t.id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function transaction(int $id)
    {
        return DB::table('transactions as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->where('t.id', $id)
            ->first(['t.*', 'o.total_amount as order_total_amount', 'o.status as order_status', 'o.payment_status as order_payment_status', 'o.tran_id as order_transaction_reference', 'o.created_at as order_created_at', 'u.name as customer_name', 'u.email as customer_email', 'u.phone as customer_phone']);
    }

    public function accounts(int $perPage, int $page, string $search)
    {
        return DB::table('company_accounts')
            ->where(function ($query) use ($search) {
                $query->where('account_name', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            })
            ->orderBy('account_name')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function account(int $id)
    {
        return DB::table('company_accounts')->where('id', $id)->first();
    }

    public function createAccount(array $data)
    {
        $id = DB::table('company_accounts')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);

        return $this->account($id);
    }

    public function updateAccount(int $id, array $data)
    {
        DB::table('company_accounts')->where('id', $id)->update([...$data, 'updated_at' => now()]);

        return $this->account($id);
    }

    public function deleteAccount(int $id)
    {
        return DB::table('company_accounts')->where('id', $id)->delete();
    }

    public function accountHistory(int $perPage, int $page, string $search, $userId = null, $month = null)
    {
        $query = DB::table('account_history as ah')
            ->leftJoin('users as u', 'u.id', '=', 'ah.user_id')
            ->select('ah.*', 'u.name as user_name', 'u.email as user_email')
            ->where(function ($query) use ($search) {
                $query->where('ah.transaction_reference', 'like', "%{$search}%")
                    ->orWhere('ah.com_account_no', 'like', "%{$search}%")
                    ->orWhere('ah.purpose', 'like', "%{$search}%")
                    ->orWhere('u.name', 'like', "%{$search}%");
            });

        if (is_numeric($userId) && (int) $userId > 0) {
            $query->where('ah.user_id', (int) $userId);
        }
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $query->whereRaw("DATE_FORMAT(ah.tran_date, '%Y-%m') = ?", [$month]);
        }

        return $query->orderByDesc('ah.id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function accountHistoryOptions(): array
    {
        return [
            'users' => DB::table('account_history as ah')
                ->leftJoin('users as u', 'u.id', '=', 'ah.user_id')
                ->whereNotNull('ah.user_id')
                ->select('ah.user_id as id', 'u.name', 'u.email')
                ->distinct()
                ->orderBy('u.name')
                ->get(),
        ];
    }

    public function accountSummary($userId = null, $month = null)
    {
        $history = DB::table('account_history');
        if (is_numeric($userId) && (int) $userId > 0) {
            $history->where('user_id', (int) $userId);
        }
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $history->whereRaw("DATE_FORMAT(tran_date, '%Y-%m') = ?", [$month]);
        }

        return [
            'total_balance' => (float) DB::table('company_accounts')->sum('amount'),
            'account_count' => DB::table('company_accounts')->count(),
            'credits' => (float) (clone $history)->where('transaction_type', 'c')->sum('amount'),
            'debits' => (float) (clone $history)->where('transaction_type', 'd')->sum('amount'),
        ];
    }
}
