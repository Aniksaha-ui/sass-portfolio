<?php

use App\Http\Controllers\Admin\Blog\BlogController;
use App\Http\Controllers\Admin\Category\CategoryController;
use App\Http\Controllers\Admin\Product\ProductController as AdminProductController;
use App\Http\Controllers\Admin\Projects\ProjectController;
use App\Http\Controllers\Admin\Users\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\User\Blog\UserBlogController;
use App\Http\Controllers\User\Address\AddressController;
use App\Http\Controllers\User\Cart\CartController;
use App\Http\Controllers\User\Order\OrderController;
use App\Http\Controllers\User\Products\ProductController;
use Illuminate\Support\Facades\Route;

/** login and registation routes **/
Route::post('/login', [AuthController::class, 'login']);

/** protected routes for admin **/
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('admin/categories', [CategoryController::class, 'index']);
    Route::post('admin/categories', [CategoryController::class, 'store']);
    Route::get('admin/categories/{id}', [CategoryController::class, 'show']);
    Route::match(['put', 'patch', 'post'], 'admin/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('admin/categories/{id}', [CategoryController::class, 'destroy']);

    Route::get('admin/products/options', [AdminProductController::class, 'options']);
    Route::get('admin/products', [AdminProductController::class, 'index']);
    Route::post('admin/products', [AdminProductController::class, 'store']);
    Route::get('admin/products/{id}', [AdminProductController::class, 'show']);
    Route::match(['put', 'patch', 'post'], 'admin/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('admin/products/{id}', [AdminProductController::class, 'destroy']);

    Route::post('admin/users', [UserController::class, 'addNewUser']);
    Route::get('admin/users', [UserController::class, 'getAllUsers']);
    Route::get('admin/users/{id}', [UserController::class, 'getUserById']);
    Route::post('admin/users/{id}', [UserController::class, 'updateUser']);

    Route::post('admin/blogs', [BlogController::class, 'addNewBlog']);
    Route::get('admin/blogs', [BlogController::class, 'getAllBlogs']);
    Route::get('admin/blogs/{id}', [BlogController::class, 'getBlogById']);
    Route::post('admin/blogs/{id}', [BlogController::class, 'updateBlog']);
});

/** protected route for users **/
Route::middleware(['auth:sanctum', 'users'])->group(function () {
    Route::post('users/add/cart', [CartController::class, 'addToCart']);
    Route::post('users/update/cart', [CartController::class, 'updateCart']);
    Route::delete('users/remove/cart/{id}', [CartController::class, 'removeCartItem']);
    Route::get('users/mycart', [CartController::class, 'getMyCart']);
    Route::get('users/applycoupon/{id}', [CartController::class, 'applyCoupon']);
    Route::get('users/addresses', [AddressController::class, 'index']);
    Route::post('users/addresses', [AddressController::class, 'store']);
    Route::post('users/order', [OrderController::class, 'order']);
    Route::get('users/orders', [OrderController::class, 'getMyOrders']);
    Route::get('users/orders/{id}', [OrderController::class, 'getOrderDetails']);
});

Route::post('/users/order/success', [OrderController::class, 'success'])->name('payment.success');
Route::post('/users/order/fail', [OrderController::class, 'fail'])->name('payment.fail');
Route::post('/users/order/cancel', [OrderController::class, 'cancel'])->name('payment.cancel');

/** route for everyone **/
Route::get('users/products', [ProductController::class, 'homePageProducts']);
Route::get('users/products/{id}', [ProductController::class, 'productDetails']);
Route::get('users/category/products/{id}', [ProductController::class, 'categoryWiseProducts']);
Route::get('users/category', [ProductController::class, 'allCategories']);
Route::get('users/categories', [ProductController::class, 'allCategories']);

Route::get('users/blogs', [UserBlogController::class, 'getAllBlogs']);
Route::get('users/blogs/{id}', [UserBlogController::class, 'getBlogById']);
