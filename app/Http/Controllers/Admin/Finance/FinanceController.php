<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Finance\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    public function transactions(Request $request)
    {
        return $this->respond('Transactions fetched successfully.', $this->finance->transactions($this->perPage($request), $this->page($request), $this->search($request)));
    }

    public function transaction($id)
    {
        $transaction = $this->finance->transaction((int) $id);

        return $transaction ? $this->respond('Transaction fetched successfully.', $transaction) : response()->json(['isExecuted' => false, 'message' => 'Transaction not found.', 'data' => []], 404);
    }

    public function accounts(Request $request)
    {
        return $this->respond('Accounts fetched successfully.', $this->finance->accounts($this->perPage($request), $this->page($request), $this->search($request)));
    }

    public function account($id)
    {
        $account = $this->finance->account((int) $id);

        return $account ? $this->respond('Account fetched successfully.', $account) : $this->notFound('Account');
    }

    public function storeAccount(Request $request)
    {
        $data = $this->validatedAccount($request);

        return $data instanceof JsonResponse ? $data : response()->json(['isExecuted' => true, 'message' => 'Account created successfully.', 'data' => $this->finance->createAccount($data)], 201);
    }

    public function updateAccount($id, Request $request)
    {
        if (! $this->finance->account((int) $id)) {
            return $this->notFound('Account');
        } $data = $this->validatedAccount($request);

        return $data instanceof JsonResponse ? $data : $this->respond('Account updated successfully.', $this->finance->updateAccount((int) $id, $data));
    }

    public function deleteAccount($id)
    {
        return $this->finance->deleteAccount((int) $id) ? $this->respond('Account deleted successfully.', []) : $this->notFound('Account');
    }

    public function history(Request $request)
    {
        return $this->respond('Account history fetched successfully.', $this->finance->accountHistory($this->perPage($request), $this->page($request), $this->search($request)));
    }

    public function summary()
    {
        return $this->respond('Account summary fetched successfully.', $this->finance->accountSummary());
    }

    private function perPage(Request $request)
    {
        return min(max((int) $request->query('perPage', 10), 1), 100);
    }

    private function page(Request $request)
    {
        return max((int) $request->query('page', 1), 1);
    }

    private function search(Request $request)
    {
        return (string) ($request->query('search') ?? '');
    }

    private function validatedAccount(Request $request)
    {
        $validator = Validator::make($request->all(), ['account_name' => 'required|string|max:191', 'account_number' => 'required|string|max:191', 'amount' => 'required|numeric|min:0', 'type' => 'required|string|max:191']);

        return $validator->fails() ? response()->json(['isExecuted' => false, 'message' => 'Validation error.', 'data' => $validator->errors()], 422) : $validator->validated();
    }

    private function notFound(string $resource)
    {
        return response()->json(['isExecuted' => false, 'message' => "$resource not found.", 'data' => []], 404);
    }

    private function respond($message, $data)
    {
        return response()->json(['isExecuted' => true, 'message' => $message, 'data' => $data]);
    }
}
