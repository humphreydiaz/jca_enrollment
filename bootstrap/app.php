<?php

use App\Http\Middleware\ExcludeFromPreviousUrl;
use App\Http\Middleware\SecurityHeaders;
use App\Modules\Identity\Http\Middleware\EnsurePasswordChanged;
use App\Modules\Identity\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (): string => route('identity.login'));
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'password.changed' => EnsurePasswordChanged::class,
            'role' => EnsureRole::class,
            'exclude.previous-url' => ExcludeFromPreviousUrl::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'api_key',
            'current_password',
            'document',
            'documents',
            'evidence',
            'file',
            'files',
            'invitation_token',
            'password',
            'password_confirmation',
            'resume_token',
            'secret',
            'token',
            'verification_code',
        ]);
        // Render JSON for API routes and for any request that explicitly accepts
        // JSON (e.g. the configuration page's background AJAX form posts), so
        // validation failures return 422 JSON instead of an HTML redirect.
        // Normal Blade form posts and feature tests don't send Accept: JSON, so
        // they keep the classic redirect-with-errors behaviour.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->wantsJson(),
        );
    })->create();
