<?php

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Repository\Services\Admin\Blog\BlogService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{

    private $blogService;
    public function __construct(BlogService $usersService)
    {
        $this->blogService = $usersService;
    }

    public function getAllBlogs(Request $request)
    {
        try {
            $page = $request->query('page') ?? 1;
            $search = $request->query('search') ?? '';
            $perPage = $request->query('perPage') ?? 10;
            $response = $this->blogService->getBlogs($perPage, $page, $search);

            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getAllBlogs function error: " . $ex->getMessage());
        }
    }

    public function addNewBlog(Request $request)
    {
        try {

            $insertedUserInformation = $this->blogService->store($request->all());
            return response()->json([
                "isExecuted" => $insertedUserInformation['status'],
                "message" => $insertedUserInformation['message'],
                "data" => $insertedUserInformation['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("addNewUser function error: " . $ex->getMessage());
        }
    }

    public function getBlogById($id)
    {
        try {
            $response = $this->blogService->getBlogById($id);
            return response()->json([
                "isExecuted" => $response['status'],
                "message" => $response['message'],
                "data" => $response['data']
            ], 200);
        } catch (Exception $ex) {
            Log::info("getUserById function error: " . $ex->getMessage());
        }
    }

    public function updateBlog($id, Request $request)
    {
        try {
            $response = $this->blogService->updateBlog($id, $request->all());
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
