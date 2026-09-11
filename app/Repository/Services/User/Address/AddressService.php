<?php

namespace App\Repository\Services\User\Address;

use App\Constants\ResponseConstants;
use App\Repository\Services\Common\CommonService;
use DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Helpers\CommonLogger as Log;

class AddressService
{
    private $commonService;

    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function myAddresses()
    {
        try {
            $addresses = DB::table('user_addresses')
                ->where('user_id', Auth::id())
                ->orderByDesc('is_primary')
                ->orderByDesc('updated_at')
                ->get()
                ->map(function ($address) {
                    return $this->transformAddress($address);
                })
                ->values();

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => $addresses->count() > 0 ? 'Addresses fetched successfully' : 'No saved addresses found',
                'data' => $addresses,
            ];
        } catch (Exception $ex) {
            Log::error('AddressService : myAddresses function error: '.$ex->getMessage());

            return $this->commonService->internalServerErrorResponse(
                ResponseConstants::FAILED,
                'Internal Server Error. Please Contact Admin',
                []
            );
        }
    }

    public function storeAddress(array $data)
    {
        DB::beginTransaction();

        try {
            $hasExistingAddresses = DB::table('user_addresses')
                ->where('user_id', Auth::id())
                ->exists();

            $shouldBePrimary = (bool) ($data['is_primary'] ?? false) || ! $hasExistingAddresses;

            if ($shouldBePrimary) {
                DB::table('user_addresses')
                    ->where('user_id', Auth::id())
                    ->update([
                        'is_primary' => 0,
                        'updated_at' => now(),
                    ]);
            }

            $addressId = DB::table('user_addresses')->insertGetId([
                'user_id' => Auth::id(),
                'address_line1' => trim((string) ($data['address_line1'] ?? '')),
                'address_line2' => $this->nullableTrim($data['address_line2'] ?? null),
                'city' => $this->nullableTrim($data['city'] ?? null),
                'state' => $this->nullableTrim($data['state'] ?? null),
                'postal_code' => $this->nullableTrim($data['postal_code'] ?? null),
                'country' => $this->nullableTrim($data['country'] ?? null),
                'phone' => $this->nullableTrim($data['phone'] ?? null),
                'is_primary' => $shouldBePrimary ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $address = DB::table('user_addresses')->where('id', $addressId)->first();

            DB::commit();

            return [
                'status' => ResponseConstants::SUCCESS,
                'message' => 'Address saved successfully',
                'data' => $this->transformAddress($address),
            ];
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error('AddressService : storeAddress function error: '.$ex->getMessage());

            return $this->commonService->internalServerErrorResponse(
                ResponseConstants::FAILED,
                'Internal Server Error. Please Contact Admin',
                []
            );
        }
    }

    private function transformAddress($address): array
    {
        $formattedAddress = implode(', ', array_filter([
            $address->address_line1 ?? '',
            $address->address_line2 ?? '',
            $address->city ?? '',
            $address->state ?? '',
            $address->postal_code ?? '',
            $address->country ?? '',
        ]));

        return [
            'id' => (int) $address->id,
            'user_id' => (int) $address->user_id,
            'address_line1' => $address->address_line1 ?? '',
            'address_line2' => $address->address_line2 ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'postal_code' => $address->postal_code ?? '',
            'country' => $address->country ?? '',
            'phone' => $address->phone ?? '',
            'is_primary' => (bool) ($address->is_primary ?? false),
            'formatted_address' => $formattedAddress,
            'created_at' => $address->created_at ?? null,
            'updated_at' => $address->updated_at ?? null,
        ];
    }

    private function nullableTrim($value)
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }
}
