<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! ($user->is_active ?? true)) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'message' => 'Akun Anda telah dinonaktifkan oleh Admin MPK.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        if ($user->must_change_password) {
            return response()->json([
                'message' => 'Anda wajib mengganti password sementara sebelum mengakses fitur sistem.',
                'code' => 'MUST_CHANGE_PASSWORD',
            ], 403);
        }

        return $next($request);
    }
}

