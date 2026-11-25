<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // 1. Ambil daftar akun (Khusus Eskul)
    public function index(Request $request)
    {
        // Pastikan hanya admin_mpk yang bisa akses
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $users = User::where('role', 'user')->orderBy('created_at', 'desc')->get();
        
        return response()->json(['data' => $users]);
    }

    // 2. Buat akun Eskul baru
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'organization_id' => 'required|integer|exists:organizations,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'organization_id' => $request->organization_id, 
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Akun Eskul ' . $user->name . ' berhasil dibuat!'
        ]);
    }

    // 3. Reset Password
    public function resetPassword(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => 'success', 
            'message' => 'Password untuk ' . $user->name . ' berhasil direset!'
        ]);
    }

    // 4. Hapus Akun
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $user = User::findOrFail($id);
        
        // Pastikan MPK tidak bisa menghapus akun MPK lain (keamanan)
        if ($user->role !== 'user') {
            return response()->json(['message' => 'Aksi ditolak. Hanya akun eskul yang boleh dihapus.'], 403);
        }

        $nama = $user->name;
        $user->delete();

        return response()->json([
            'status' => 'success', 
            'message' => 'Akun ' . $nama . ' berhasil dihapus permanen!'
        ]);
    }
}