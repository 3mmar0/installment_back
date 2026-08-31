<?php

use App\Enums\ErrorCodes;
use App\Exceptions\MailDeliveryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'owner' => \App\Http\Middleware\EnsureOwner::class,
            'platform_admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API exceptions with consistent JSON responses
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error_code' => ErrorCodes::Unauthorized->value,
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error_code' => ErrorCodes::Forbidden->value,
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Forbidden',
                ], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error_code' => ErrorCodes::NotFound->value,
                    'success' => false,
                    'message' => 'Resource not found',
                ], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error_code' => ErrorCodes::ValidationFailed->value,
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (MailDeliveryException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error_code' => ErrorCodes::MailDeliveryFailed->value,
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 503);
            }
        });

        $exceptions->render(function (TransportExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                Log::error('Mail transport error', ['error' => $e->getMessage()]);

                return response()->json([
                    'error_code' => ErrorCodes::MailDeliveryFailed->value,
                    'success' => false,
                    'message' => 'تعذر إرسال البريد الإلكتروني، يرجى المحاولة لاحقاً',
                ], 503);
            }
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') && !$e instanceof ValidationException) {
                // Get status code from exception if it's an HTTP exception
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $statusCode = $e->getStatusCode();
                } else {
                    $statusCode = 500;
                }

                $debug = (bool) config('app.debug');
                $message = $debug
                    ? ($e->getMessage() ?: 'An error occurred')
                    : 'حدث خطأ في الخادم، يرجى المحاولة لاحقاً';

                return response()->json([
                    'error_code' => ErrorCodes::InternalServerError->value,
                    'success' => false,
                    'message' => $message,
                    'error' => $debug ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTrace(),
                    ] : null,
                ], $statusCode >= 100 && $statusCode < 600 ? $statusCode : 500);
            }
        });
    })->create();
