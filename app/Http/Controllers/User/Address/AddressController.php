<?php

namespace App\Http\Controllers\User\Address;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Repository\Services\User\Address\AddressService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    private $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    public function index()
    {
        try {
            $response = $this->addressService->myAddresses();

            return response()->json([
                'isExecuted' => $response['status'],
                'message' => $response['message'],
                'data' => $response['data'],
            ], 200);
        } catch (Exception $ex) {
            Log::info('AddressController : index function error: '.$ex->getMessage());
        }
    }

    public function store(AddressRequest $request)
    {
        try {
            $response = $this->addressService->storeAddress($request->validated());

            return response()->json([
                'isExecuted' => $response['status'],
                'message' => $response['message'],
                'data' => $response['data'],
            ], $response['status'] ? 201 : 422);
        } catch (Exception $ex) {
            Log::info('AddressController : store function error: '.$ex->getMessage());
        }
    }
}
