<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\AuthController;
use App\Models\User;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/rooms/filter', [RoomController::class, 'filterByIds']);

Route::apiResource('explore', ExploreController::class);
Route::apiResource('rooms', RoomController::class);
Route::apiResource('hotels', HotelController::class);
Route::apiResource('reservations', ReservationController::class);

// New route for filtering rooms by IDs (for homepage or special sections)


// File upload routes
Route::post('upload/image', [UploadController::class, 'uploadImage']);
Route::post('upload/video', [UploadController::class, 'uploadVideo']);

// Authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::group(['middleware' => 'auth:api'], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
  
    Route::get('/user-profile', [AuthController::class, 'userProfile']);

    // Get all users (protected route)
    Route::get('/users', function () {
        return \App\Models\User::all();
    });


    // Update user role (protected route)
    Route::patch('/users', function (Request $request) {
        $user = User::find($request->userId);
        if ($user) {
            $user->role = $request->role;
            $user->save();
            return response()->json(['success' => true, 'user' => $user]);
        }
        return response()->json(['error' => 'User not found'], 404);
    });
});

    Route::get('/user',[AuthController::class, 'user']);
    Route::post('/refresh', [AuthController::class, 'refresh']);