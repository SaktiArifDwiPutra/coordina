<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFixedScheduleRequest;
use App\Models\FixedSchedule;
use App\Services\ScheduleConflictService;
use Illuminate\Http\Request;

class FixedScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleConflictService $conflictService
    ) {}

    public function store(StoreFixedScheduleRequest $request)
    {
        $validated = $request->validated();

        $overlaps = $this->conflictService->findOverlappingFixedSchedules(
            (int) $validated['facility_id'],
            $validated['day'],
            $validated['start_time'],
            $validated['end_time']
        );

        if ($overlaps->isNotEmpty()) {
            return response()->json([
                'message' => 'Jadwal tetap bertabrakan dengan jadwal tetap lain pada fasilitas dan hari yang sama.',
            ], 409);
        }

        $schedule = FixedSchedule::create($validated);

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
