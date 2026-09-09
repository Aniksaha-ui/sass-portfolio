<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Handle login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {

        // Attempt to authenticate the user
        $credentials = $request->validated();

        if (!Auth::attempt($credentials)) {
            return response()->json(['code' => 401, 'message' => 'Unauthorized'], 401);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Generate a token for the user
        $token = $user->createToken('Personal Access Token')->plainTextToken;

        // Return the response with the token
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') ? config('sanctum.expiration') * 60 : null,
            'user' => $user
        ]);
    }
}
