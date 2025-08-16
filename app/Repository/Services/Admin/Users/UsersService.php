<?php
namespace App\Repository\Services\Admin\Blog;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class UsersService{

    public function store($data){
        try{
            $userId = DB::table('users')->insertGetId($data);
            if($userId){
                return [
                    "isExecuted" => true, 
                    "message" => "User created successfully",
                    "data" => $userId
                ];
            }else{
                return [
                    "isExecuted" => false, 
                    "message" => "User not created",
                    "data" => []
                ];
            }
        }catch(Exception $e){
            Log::error("store function error: " . $e->getMessage());
            return [
                "isExecuted" => false, 
                "message" => "Internal server error. Check Log",
                "data" => []
            ];
        }
    }

}


?>

