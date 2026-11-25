<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Localise API messages from the Accept-Language header (ar | en). */
class SetLocaleFromRequest
{
    public const SUPPORTED = ['ar', 'en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $preferred = $request->getPreferredLanguage(self::SUPPORTED);
        if ($request->hasHeader('Accept-Language') && in_array($preferred, self::SUPPORTED, true)) {
            App::setLocale($preferred);
        }

        return $next($request);
    }
}
