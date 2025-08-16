<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        Log::info("request" . $request->user());
        if (!$request->user() || !$this->isAdmin($request->user())) {
            return response()->json(['message' => 'Authentication failed'], 403);
        }
        return $next($request);
    }

    public function isAdmin($user)
    {
        return $user->role == 'admin' ? true : false;
    }
}
