<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreEskulUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $users = User::where('role', 'owner_eskul')
            ->with('organization:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $users]);
    }

    public function store(StoreEskulUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner_eskul',
            'organization_id' => $validated['organization_id'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Akun Eskul ' . $user->name . ' berhasil dibuat!',
            'data' => $user->load('organization:id,name'),
        ], 201);
    }

    public function resetPassword(ResetUserPasswordRequest $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->role !== 'owner_eskul') {
            return response()->json([
                'message' => 'Aksi ditolak. Hanya akun eskul yang boleh direset melalui endpoint ini.',
            ], 403);
        }

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        // Revoke active Sanctum tokens after password reset
        $user->tokens()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Password untuk ' . $user->name . ' berhasil direset!',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->role !== 'owner_eskul') {
            return response()->json([
                'message' => 'Aksi ditolak. Hanya akun eskul yang boleh dihapus.',
            ], 403);
        }

        $nama = $user->name;
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Akun ' . $nama . ' berhasil dihapus permanen!',
        ]);
    }
}
