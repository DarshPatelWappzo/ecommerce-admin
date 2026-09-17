<?php

use App\Http\Middleware\ResolveTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('tenant/*') ? '/tenant/login' : '/super-admin/login',
        );
        $middleware->prependToPriorityList(AuthenticatesRequests::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $exception, Request $request): ?JsonResponse {
            if ($request->routeIs('api.tenant.login') && $exception->getStatusCode() === 429) {
                return response()->json([
                    'message' => 'Too many login attempts. Please try again later.',
                    'error_code' => 429,
                ], 429, $exception->getHeaders());
            }

            return null;
        });
        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if ($request->routeIs('api.tenant.*')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'error_code' => $exception->status,
                    'errors' => $exception->errors(),
                ], $exception->status);
            }

            return null;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
