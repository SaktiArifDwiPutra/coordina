<?php

namespace App\Http\Controllers;

use App\Models\FixedSchedule;
use Illuminate\Http\Request;

class FixedScheduleController extends Controller
{
    // Tambah Jadwal Tetap Eskul
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'organization_id' => 'required|exists:organizations,id',
            'day' => 'required|string', // Monday, Tuesday, dst.
            'start_time' => 'required',
            'end_time' => 'required',
        ]);

        FixedSchedule::create($request->all());

        return response()->json(['message' => 'Jadwal tetap berhasil didaftarkan!']);
    }

    // Hapus Jadwal Tetap
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        FixedSchedule::findOrFail($id)->delete();
        return response()->json(['message' => 'Jadwal tetap berhasil dihapus.']);
    }
}