<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('daily')->info('API Request', [
            'method'   => $request->method(),
            'url'      => $request->fullUrl(),
            'ip'       => $request->ip(),
            'ua'       => $request->userAgent(),
            'status'   => $response->getStatusCode(),
            'duration' => $duration . 'ms',
        ]);

        return $response;
    }
}
