<?php

namespace App\Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roleCodes): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->hasAnyRole($roleCodes), 403);

        return $next($request);
    }
}
