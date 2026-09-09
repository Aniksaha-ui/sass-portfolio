<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class AddTraceIdToResponse
{
    /**
     * Generate one trace ID per request and expose it in every response.
     */
    public function handle($request, Closure $next)
    {
        $traceId = (string) Str::uuid();

        $request->attributes->set('trace_id', $traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);

            // Preserve existing response fields while adding the common trace ID.
            if (is_array($payload)) {
                $payload['trace_id'] = $traceId;
            } else {
                $payload = [
                    'data' => $payload,
                    'trace_id' => $traceId,
                ];
            }

            $response->setData($payload);
        }

        return $response;
    }
}
