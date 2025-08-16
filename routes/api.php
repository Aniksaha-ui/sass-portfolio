<?php

use App\Http\Controllers\Admin\Users\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


/** login and registation routes **/
Route::post('/login', [AuthController::class, 'login']);
Route::post('admin/users', [UserController::class, 'addNewUser']);

/** protected routes for admin **/ 
Route::middleware(['auth:sanctum', 'admin'])->group(function () {

    // Route::get('/users', [UserController::class, 'getAllUsers']);
   

});


/** protected route for users **/

Route::middleware(['auth:sanctum', 'users'])->group(function () {
   
});



/** route for everyone **/



