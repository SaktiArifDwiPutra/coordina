<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreEskulUserRequest;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly AccountLifecycleService $accountService
    ) {}

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
        $result = $this->accountService->createEskulAccount($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Akun Eskul ' . $result['user']->name . ' berhasil dibuat!',
            'data' => $result['user'],
            'generated_password' => $result['plain_password'],
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

        $result = $this->accountService->resetEskulPassword($user, $request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Password untuk ' . $result['user']->name . ' berhasil direset!',
            'data' => $result['user'],
            'generated_password' => $result['plain_password'],
        ]);
    }

    public function toggleActive(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $user = User::findOrFail($id);

        if ($user->role !== 'owner_eskul') {
            return response()->json([
                'message' => 'Aksi ditolak. Hanya akun eskul yang dapat diubah status aktifnya.',
            ], 403);
        }

        $updated = $this->accountService->toggleActive($user);
        $label = $updated->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'status' => 'success',
            'message' => "Akun {$updated->name} berhasil {$label}.",
            'data' => $updated,
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
