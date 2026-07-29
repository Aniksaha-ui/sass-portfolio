<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Finance\FinanceService;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance) {}
    public function transactions(Request $request) { return $this->respond('Transactions fetched successfully.', $this->finance->transactions($this->perPage($request), $this->page($request), $this->search($request))); }
    public function transaction($id) { $transaction = $this->finance->transaction((int) $id); return $transaction ? $this->respond('Transaction fetched successfully.', $transaction) : response()->json(['isExecuted' => false, 'message' => 'Transaction not found.', 'data' => []], 404); }
    public function accounts(Request $request) { return $this->respond('Accounts fetched successfully.', $this->finance->accounts($this->perPage($request), $this->page($request), $this->search($request))); }
    public function history(Request $request) { return $this->respond('Account history fetched successfully.', $this->finance->accountHistory($this->perPage($request), $this->page($request), $this->search($request))); }
    public function summary() { return $this->respond('Account summary fetched successfully.', $this->finance->accountSummary()); }
    private function perPage(Request $request) { return min(max((int) $request->query('perPage', 10), 1), 100); }
    private function page(Request $request) { return max((int) $request->query('page', 1), 1); }
    private function search(Request $request) { return (string) ($request->query('search') ?? ''); }
    private function respond($message, $data) { return response()->json(['isExecuted' => true, 'message' => $message, 'data' => $data]); }
}
