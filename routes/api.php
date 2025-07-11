<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\PaymentController; 

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
Route::get('/room-types/featured', [RoomTypeController::class, 'featuredRoomTypes']);
Route::apiResource('explore', ExploreController::class);
Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);
Route::apiResource('hotels', HotelController::class)->only(['index', 'show']);
Route::apiResource('reservations', ReservationController::class)->only(['index', 'show']);

// Public room type routes
Route::apiResource('room-types', RoomTypeController::class)->only(['index', 'show']);

// Public availability checking routes
Route::get('/hotels/{hotel}/availability', [HotelController::class, 'checkAvailability']);
Route::get('/room-types/{roomType}/availability', [RoomTypeController::class, 'checkAvailability']);
Route::get('/rooms/{room}/availability', [RoomController::class, 'checkAvailability']);

// Public reservation lookup
Route::get('/reservations/lookup', [ReservationController::class, 'getByCode']);

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
    Route::get('/user/profile', [AuthController::class, 'userProfile']);
    Route::get('/user-profile', [AuthController::class, 'userProfile']);
    
    // Regular authenticated user routes (Guests can make reservations)
    Route::apiResource('reservations', ReservationController::class)->except(['index', 'show']);
    
    // Payment routes for authenticated users
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store']);
    Route::get('/reservations/{reservation}/payments', [PaymentController::class, 'getByReservation']);
    Route::patch('/payments/{payment}/complete', [PaymentController::class, 'complete']);
    Route::patch('/payments/{payment}/fail', [PaymentController::class, 'fail']);
    
    // Admin and Owner routes
    Route::middleware(['role:Admin,Owner'])->group(function () {
        // Hotel management
        Route::apiResource('hotels', HotelController::class)->except(['index', 'show']);
        // Room management

        Route::apiResource('rooms', RoomController::class)->except(['index', 'show']);
        Route::patch('/rooms/{room}/status', [RoomController::class, 'updateStatus']);

        // Room type management
        Route::apiResource('room-types', RoomTypeController::class)->except(['index', 'show']);
        
        // Reservation management
        Route::patch('/reservations/{reservation}/confirm', [ReservationController::class, 'confirm']);
        Route::patch('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn']);
        Route::patch('/reservations/{reservation}/check-out', [ReservationController::class, 'checkOut']);
        
        // Payment management
        Route::apiResource('payments', PaymentController::class)->except(['index', 'show', 'store']);
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
        
        // User management
        Route::get('/users', [AuthController::class, 'users']);
    });

    // Admin-only routes
    Route::middleware(['role:Admin'])->group(function () {
        Route::patch('/users/{user}/role', [AuthController::class, 'updateUserRole']);
    });
    
    // Owner-specific routes
    Route::middleware(['role:Owner'])->group(function () {
        // Dashboard
        Route::get('/owner/dashboard', [OwnerController::class, 'dashboard']);
        
        // Hotel CRUD for owners
        Route::get('/owner/hotels', [OwnerController::class, 'getHotels']);
        Route::post('/owner/hotels', [OwnerController::class, 'createHotel']);
        Route::put('/owner/hotels/{hotel}', [OwnerController::class, 'updateHotel']);
        Route::delete('/owner/hotels/{hotel}', [OwnerController::class, 'deleteHotel']);
        
        // Analytics
        Route::get('/owner/analytics', [OwnerController::class, 'getAnalytics']);
        
        // Owner-specific data
        Route::get('/owner/reservations', [ReservationController::class, 'ownerReservations']);
        Route::get('/owner/hotels/{hotel}', [OwnerController::class, 'getHotelDetailById']);
        
    });
});