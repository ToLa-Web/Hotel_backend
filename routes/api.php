<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::get('/rooms/filter', [RoomController::class, 'filterByIds']);
Route::apiResource('explore', ExploreController::class);
Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);
Route::apiResource('hotels', HotelController::class)->only(['index', 'show']);
Route::apiResource('reservations', ReservationController::class)->only(['index', 'show']);

// File upload routes
Route::post('upload/image', [UploadController::class, 'uploadImage']);
Route::post('upload/video', [UploadController::class, 'uploadVideo']);

// Authentication routes (public)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

// Protected routes (require JWT authentication)
Route::middleware(['auth:api'])->group(function () {
    // Auth-related routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/profile', [AuthController::class, 'userProfile']);  // Matches api.get("/user/profile")
    Route::get('/user-profile', [AuthController::class, 'userProfile']); // Keep this for backward compatibility
    
    // Regular authenticated user routes
    Route::apiResource('reservations', ReservationController::class)->except(['index', 'show']);
    
    // Admin and Owner routes
    Route::middleware(['role:Admin,Owner'])->group(function () {
        Route::apiResource('rooms', RoomController::class)->except(['index', 'show']);
        Route::apiResource('hotels', HotelController::class)->except(['index', 'show']);
        Route::get('/users', [AuthController::class, 'users']); // Get all users
    });

    // Admin-only routes
    Route::middleware(['role:Admin'])->group(function () {
        Route::patch('/users/{user}/role', [AuthController::class, 'updateUserRole']); // Update user role
    });
});