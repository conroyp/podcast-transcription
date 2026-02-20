<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureUtf8JsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // If this is a JSON response, ensure UTF-8 safety
        if ($response instanceof JsonResponse) {
            // Get the original data
            $data = $response->getData(true);

            // Re-encode with UTF-8 safe flags
            $response->setData($data);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }

        return $response;
    }
}
