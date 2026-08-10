<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExcludeFromPreviousUrl
{
    /**
     * Keep this request from becoming the session's stored "previous URL".
     *
     * Requirement soft copies are streamed by GET routes and loaded inside an
     * iframe on the registrar review page. Being a plain GET, such a request
     * would otherwise be recorded by Illuminate\Session\Middleware\StartSession
     * as the intended "previous URL". A later validation failure on the review
     * form (e.g. rejecting a soft copy without a reason) — or any
     * redirect()->back() — then sends the reviewer to the raw file instead of
     * back to the queue.
     *
     * StartSession::storeCurrentUrl() skips requests it considers AJAX, so we
     * flag the request accordingly. This runs after the auth middleware, so
     * guest handling is unaffected, and error responses remain HTML because the
     * app only renders JSON for `api/*` (see bootstrap/app.php).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return $next($request);
    }
}
