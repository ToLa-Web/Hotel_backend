<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        return $this->createNewToken($token);
    }

    public function user(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'user' => auth()->user()
        ]);

    }

    public function register(RegisterRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'User', // Default role is User if not specified
            ]);

            // Generate token for the newly registered user
            $token = auth()->login($user);

            DB::commit();

            return $this->createNewToken($token);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed'
            ], 500);
        }
    }

    public function logout()
    {
        try {
            auth()->logout();
            return response()->json([
                'status' => 'success',
                'message' => 'User successfully logged out'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed'
            ], 500);
        }
    }

    public function refresh(Request $request)
    {
        try {
            // Invalidate the current token and generate a new one
            $newToken = auth()->refresh(false, true);
            return $this->createNewToken($newToken);
        } catch (\Exception $e) {
            // handle error
        }
    }

    public function userProfile()
    {
        try {
            return response()->json([
                'status' => 'success',
                'user' => auth()->user()
            ]);
        } catch (\Exception $e) {
            Log::error('Profile fetch error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user profile'
            ], 500);
        }
    }

    public function updateUserRole(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'userId' => 'required|exists:users,id',
                'role' => 'required|in:User,Owner,Admin'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Only Admin can update roles
            if (auth()->user()->role !== 'Admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: Only Admin can update roles'
                ], 403);
            }

            $user = User::findOrFail($request->userId);
            $user->role = $request->role;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'User role updated successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Role update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user role'
            ], 500);
        }
    }

    protected function createNewToken($token)
    {
        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
    }
}