<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route-level gate for one area of the admin center: `->middleware('area:lessons')`. Controllers check again. */
class EnsureArea
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        abort_unless($request->user()?->hasPermission($area), 403);

        return $next($request);
    }
}
