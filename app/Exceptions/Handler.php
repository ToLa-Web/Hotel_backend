<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenInvalidException) {
            return response()->json(['status' => 'error', 'message' => 'Token is Invalid'], 401);
        } elseif ($exception instanceof TokenExpiredException) {
            return response()->json(['status' => 'error', 'message' => 'Token is Expired'], 401);
        } elseif ($exception instanceof JWTException) {
            return response()->json(['status' => 'error', 'message' => 'Token not parsed'], 401);
        } elseif ($exception instanceof AuthenticationException) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
        }

        // Optionally, always return JSON for API requests
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500);
        }

        return parent::render($request, $exception);
    }
}
