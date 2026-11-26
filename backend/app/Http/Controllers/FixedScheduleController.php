<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedScheduleRequest;
use App\Models\FixedSchedule;
use Illuminate\Http\Request;

class FixedScheduleController extends Controller
{
    public function store(StoreFixedScheduleRequest $request)
    {
        $schedule = FixedSchedule::create($request->validated());

        return response()->json([
            'message' => 'Jadwal tetap berhasil didaftarkan!',
            'data' => $schedule,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        FixedSchedule::findOrFail($id)->delete();

        return response()->json(['message' => 'Jadwal tetap berhasil dihapus.']);
    }
}
