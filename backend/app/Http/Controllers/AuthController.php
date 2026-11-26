<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly AccountLifecycleService $accountService
    ) {}

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah',
            ], 401);
        }

        if (! in_array($user->role, ['admin_mpk', 'owner_eskul'], true)) {
            return response()->json([
                'message' => 'Akun tidak memiliki akses ke sistem ini.',
            ], 403);
        }

        if (! ($user->is_active ?? true)) {
            return response()->json([
                'message' => 'Akun organisasi sedang dinonaktifkan oleh Admin MPK.',
            ], 403);
        }

        $user->ensureMpkOrganizationLinked();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('organization:id,name,is_mpk'),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        $token = $user->currentAccessToken();
        $keepTokenId = $token instanceof PersonalAccessToken ? $token->id : null;

        $updated = $this->accountService->changePassword($user, $validated['new_password'], $keepTokenId);

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
            'user' => $updated,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }
}
