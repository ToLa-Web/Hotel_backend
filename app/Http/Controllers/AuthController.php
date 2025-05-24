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
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\UserController;


class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register', 'refresh']]);
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

    public function logout(Request $request)
    {
        try {
            // Invalidate the current token
            auth()->logout();
            
            // Blacklist the refresh token if provided
            $refreshToken = $request->refresh_token;
            if ($refreshToken) {
                try {
                    JWTAuth::setToken($refreshToken)->invalidate();
                } catch (\Exception $e) {
                    // Ignore errors related to invalid refresh tokens
                    Log::info('Failed to invalidate refresh token: ' . $e->getMessage());
                }
            }
            
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
            $validator = Validator::make($request->all(), [
                'refresh_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Refresh token is required',
                    'errors' => $validator->errors()
                ], 422);
            }

            $refreshToken = $request->refresh_token;
            
            // Set the refresh token as the current token
            JWTAuth::setToken($refreshToken);
            
            // Verify the token is valid
            if (!JWTAuth::check()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid refresh token'
                ], 401);
            }
            
            // Get the user ID from the refresh token
            $user = JWTAuth::toUser();
            
            // Generate a new access token for the user
            $newToken = JWTAuth::fromUser($user);
            
            // Generate a new refresh token
            $newRefreshToken = $this->generateRefreshToken($user);
            
            // Invalidate the old refresh token (optional but recommended for security)
            try {
                JWTAuth::setToken($refreshToken)->invalidate();
            } catch (\Exception $e) {
                Log::error('Failed to invalidate old refresh token: ' . $e->getMessage());
            }
            
            return $this->respondWithTokens($newToken, $newRefreshToken);
            
        } catch (\Exception $e) {
            Log::error('Token refresh error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Token refresh failed'
            ], 401);
        }
    }
    
     public function user(Request $request)
{
    $user = auth()->user();

    // Define roles and permissions
    $rolePermissions = [
        'Admin' => [
            'permissions' => ['dashboard', 'users', 'settings'],
            'access_level' => 100
        ],
        'Owner' => [
            'permissions' => ['reservation', 'properties', 'reports'],
            'access_level' => 80
        ],
        'User' => [
            'permissions' => ['profile', 'bookings'],
            'access_level' => 50
        ]
    ];

    $userPermissions = $rolePermissions[$user->role] ?? [
        'permissions' => [],
        'access_level' => 0
    ];

    return response()->json([
        'status' => 'success',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ],
        'permissions' => $userPermissions['permissions'],
        'access_level' => $userPermissions['access_level']
    ]);
}
    public function users()
{
    $user = auth()->user();

    if (!$user || !in_array($user->role, ['Admin', 'Owner'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Forbidden'
        ], 403);
    }

    $users = User::all(); // or paginate

    return response()->json([
        'status' => 'success',
        'users' => $users
    ]);
}

    public function userProfile()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }
            
            // Define role permissions structure (could be moved to config or database)
            $rolePermissions = [
                'Admin' => [
                    'permissions' => ['dashboard', 'users', 'settings'],
                    'access_level' => 100
                ],
                'Owner' => [
                    'permissions' => ['reservation', 'properties', 'reports'],
                    'access_level' => 80
                ],
                'User' => [
                    'permissions' => ['profile', 'bookings'],
                    'access_level' => 50
                ]
            ];
            
            // Get permissions for the user's role with fallback
            $userPermissions = $rolePermissions[$user->role] ?? [
                'permissions' => [],
                'access_level' => 0
            ];
            
            return response()->json([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ],
                'permissions' => $userPermissions['permissions'],
                'access_level' => $userPermissions['access_level'],
                'meta' => [
                    'permissions_defined' => !empty($userPermissions['permissions'])
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Profile fetch error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user profile',
                'error' => config('app.debug') ? $e->getMessage() : null
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

    /**
     * Generate a refresh token for the user
     * 
     * @param User $user
     * @return string
     */
    protected function generateRefreshToken(User $user)
    {
        // Create a custom token with longer expiry (e.g., 30 days)
        $customClaims = [
            'sub' => $user->id,
            'typ' => 'refresh',
            // Setting a longer expiration for refresh token
            'exp' => time() + (60 * 60 * 24 * 30) // 30 days
        ];
        
        return JWTAuth::customClaims($customClaims)->fromUser($user);
    }

    /**
     * Create a response with both access and refresh tokens
     *
     * @param string $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token)
    {
        $user = auth()->user();
        $refreshToken = $this->generateRefreshToken($user);
        
        return $this->respondWithTokens($token, $refreshToken);
    }
    
    /**
     * Respond with both tokens
     * 
     * @param string $accessToken
     * @param string $refreshToken
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithTokens($accessToken, $refreshToken)
    {
        return response()->json([
            'status' => 'success',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'bearer',
            'access_expires_in' => auth()->factory()->getTTL() * 60, // Access token expiry in seconds
            'user' => auth()->user()
        ]);
    }
}