<?php

namespace App\Repository\Services\Admin\Users;

use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class UsersService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function store($data)
    {
        try {

            if (request()->hasFile('image')) {
                $documentLink = FileManageHelper::uploadFile('users', $data['image']);
                $data['image'] = $documentLink;
            } else {
                $request['image'] = 'images/trips/default.png';
            }

            $data['password'] = bcrypt($data['password']);
            $userId = DB::table('users')->insertGetId($data);
            if ($userId) {
                return [
                    "status" => true,
                    "message" => "User created successfully",
                    "data" => $userId
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "User not created",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            Log::error("store function error: " . $e->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal server error", []);
        }
    }

    public function getUsers(int $perPage, int $page, string $search)
    {
        try {
            $user = DB::table('users')
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('role', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['id', 'name', 'email', 'role'], 'page', $page);

            if ($user->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Users fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No user found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getUsers function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getUserById($id)
    {
        try {
            $user = DB::table('users')->where('id', $id)->first();
            if ($user) {
                return [
                    "status" => true,
                    "message" => "User fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No user found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getUsers function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function updateUser($id, $data)
    {
        try {
            $userInformationExist = DB::table('users')->where('id', $id)->exists();
            if (!$userInformationExist) {
                return [
                    "status" => true,
                    "message" => "User not found",
                    "data" => []
                ];
            }

            $allowedFields = ['name', 'email', 'role', 'password', 'image'];
            $data = array_intersect_key($data, array_flip($allowedFields));

            if (request()->hasFile('image')) {
                $documentLink = FileManageHelper::uploadFile('users', $data['image']);
                $data['image'] = $documentLink;
            }

            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['password'] = bcrypt($data['password']);
            }

            if (isset($data['email']) && DB::table('users')
                ->where('email', $data['email'])
                ->where('id', '!=', $id)
                ->exists()) {
                return [
                    "status" => false,
                    "message" => "This email address is already in use",
                    "data" => []
                ];
            }

            if (empty($data)) {
                return [
                    "status" => true,
                    "message" => "No changes to save",
                    "data" => []
                ];
            }

            $user = DB::table('users')->where('id', $id)->update($data);
            if ($user) {
                return [
                    "status" => true,
                    "message" => "User Information updated successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "User Information not updated successfully",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getUsers function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, $ex->getMessage(), []);
        }
    }
}
