<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function index()
    {
        // Ambil fasilitas beserta jadwal tetapnya
        // DAN ambil jadwal peminjaman yang sudah di-ACC untuk hari ini ke depan
        $facilities = Facility::with([
            'fixedSchedules.organization',
            'borrowRequests' => function ($query) {
                $query->where('status', 'approved')
                      ->where('date', '>=', now()->toDateString())
                      ->with('organization');
            }
        ])->get();

        return response()->json(['data' => $facilities]);
    }

    // Tambah Fasilitas Baru
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $facility = \App\Models\Facility::create($request->all());

        return response()->json([
            'message' => 'Fasilitas ' . $facility->name . ' berhasil ditambahkan!', 
            'data' => $facility
        ]);
    }

    // Hapus Fasilitas
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        \App\Models\Facility::findOrFail($id)->delete();
        return response()->json(['message' => 'Fasilitas berhasil dihapus permanen.']);
    }
}