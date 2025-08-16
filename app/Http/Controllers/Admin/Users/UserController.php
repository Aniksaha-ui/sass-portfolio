<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Blog\UsersService;
use Exception;
use Illuminate\Http\Request;
use Log;

class UserController extends Controller
{
  
    private $usersService;
    public function __construct(UsersService $usersService){
        $this->usersService = $usersService;
    }

    public function getAllUsers(){}

    public function addNewUser(Request $request){
        try{
        
            $insertedUserInformation = $this->usersService->store($request->all());
            return response()->json([
                "isExecuted" => $insertedUserInformation['isExecute'],
                "message" => $insertedUserInformation['message'],
                "data" => $insertedUserInformation['data']
            ]);

        }catch(Exception $ex){
            Log::info("addNewUser function error: " . $ex->getMessage());
        }
    }


}
