<?php

use App\Http\Controllers\Admin\Blog\BlogController;
use App\Http\Controllers\Admin\Projects\ProjectController;
use App\Http\Controllers\Admin\Users\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;


/** login and registation routes **/
Route::post('/login', [AuthController::class, 'login']);

/** protected routes for admin **/
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    Route::post('admin/users', [UserController::class, 'addNewUser']);
    Route::get('admin/users', [UserController::class, 'getAllUsers']);
    Route::get('admin/users/{id}', [UserController::class, 'getUserById']);
    Route::post('admin/users/{id}', [UserController::class, 'updateUser']);


    Route::post('admin/blogs', [BlogController::class, 'addNewBlog']);
    Route::get('admin/blogs', [BlogController::class, 'getAllBlogs']);
    Route::get('admin/blogs/{id}', [BlogController::class, 'getBlogById']);
    Route::post('admin/blogs/{id}', [BlogController::class, 'updateBlog']);



    Route::post('admin/projects', [ProjectController::class, 'addNewProject']);
    Route::get('admin/projects', [ProjectController::class, 'getAllProjects']);
    Route::get('admin/projects/{id}', [ProjectController::class, 'getProjectById']);
    Route::post('admin/projects/{id}', [ProjectController::class, 'updateProject']);
});


/** protected route for users **/
Route::middleware(['auth:sanctum', 'users'])->group(function () {});



/** route for everyone **/
