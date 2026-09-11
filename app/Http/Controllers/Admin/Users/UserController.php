<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Repository\Services\Admin\Users\UsersService;
use Exception;
use Illuminate\Http\Request;
use App\Helpers\CommonLogger as Log;

class UserController extends Controller
{

    private $usersService;
    public function __construct(UsersService $usersService)
    {
        $this->usersService = $usersService;
    }

    public function getAllUsers(Request $request)
    {
        try {
            $page = max((int) $request->query('page', 1), 1);
            $perPage = min(max((int) $request->query('perPage', 10), 1), 100);
            $search = (string) $request->query('search', '');
            $response = $this->usersService->getUsers($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllUsers function error: " . $ex->getMessage());
        }
    }

    public function addNewUser(UserRequest $request)
    {
        try {

            $insertedUserInformation = $this->usersService->store($request->validated());
            Log::info(json_encode($insertedUserInformation));
            return response()->json([
                "isExecuted" => $insertedUserInformation['status'],
                "message" => $insertedUserInformation['message'],
                "data" => $insertedUserInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewUser function error: " . $ex->getMessage());
        }
    }

    public function getUserById($id)
    {
        try {
            $response = $this->usersService->getUserById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getUserById function error: " . $ex->getMessage());
        }
    }

    public function updateUser($id, UserRequest $request)
    {
        try {


            $response = $this->usersService->updateUser($id, $request->validated());
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getUserById function error: " . $ex->getMessage());
        }
    }
}
