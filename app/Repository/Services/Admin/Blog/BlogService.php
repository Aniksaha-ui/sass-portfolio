<?php

namespace App\Repository\Services\Admin\Blog;

use App\Helpers\admin\FileManageHelper;
use App\Repository\Services\Common\CommonService;
use Exception;
use Illuminate\Support\Facades\Log;
use DB;


class BlogService
{

    private $commonService;
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    public function store($data)
    {
        try {
            $userId = DB::table('blogs')->insertGetId($data);
            if ($userId) {
                return [
                    "status" => true,
                    "message" => "Blogs created successfully",
                    "data" => $userId
                ];
            } else {
                return [
                    "status" => false,
                    "message" => "Blogs not created",
                    "data" => []
                ];
            }
        } catch (Exception $e) {
            Log::error("blogservice - store function error: " . $e->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal server error", []);
        }
    }

    public function getBlogs($perPage, $page, $search)
    {
        try {
            $user = DB::table('blogs')
                ->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('category', 'like', '%' . $search . '%')
                        ->orWhere('author_name', 'like', '%' . $search . '%');
                })
                ->paginate($perPage, ['*'], 'page', $page);

            if ($user->count() > 0) {
                return [
                    "status" => true,
                    "message" => "Blogs fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No blog found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("blogservice  - getBlogs function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }

    public function getBlogById($id)
    {
        try {
            $user = DB::table('blogs')->where('id', $id)->first();
            if ($user) {
                return [
                    "status" => true,
                    "message" => "Blogs fetched successfully",
                    "data" => $user
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "No blogs found",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("getBlogById function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, "Internal Server Error. Please Contact Admin", []);
        }
    }


    public function updateBlog($id, $data)
    {
        try {
            $userInformationExist = DB::table('blogs')->where('id', $id)->exists();
            if (!$userInformationExist) {
                return [
                    "status" => true,
                    "message" => "Blogs not found",
                    "data" => []
                ];
            }

            $blogs = DB::table('blogs')->where('id', $id)->update($data);
            if ($blogs) {
                return [
                    "status" => true,
                    "message" => "Blog Information updated successfully",
                    "data" => $blogs
                ];
            } else {
                return [
                    "status" => true,
                    "message" => "Blog Information not updated successfully",
                    "data" => []
                ];
            }
        } catch (Exception $ex) {
            Log::error("updateBlog function error: " . $ex->getMessage());
            return $this->commonService->internalServerErrorResponse(false, $ex->getMessage(), []);
        }
    }
}
