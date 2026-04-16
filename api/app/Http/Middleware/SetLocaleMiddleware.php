<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleMiddleware
{
    protected array $supported = [
        'en', 'de', 'fr', 'es', 'it', 'ja', 'ko',
        'pt-BR', 'pt-PT', 'nl', 'pl', 'sv', 'da', 'ar', 'tr', 'el',
    ];

    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language', 'en');
        $locale = in_array($locale, $this->supported) ? $locale : 'en';
        app()->setLocale($locale);
        return $next($request);
    }
}
