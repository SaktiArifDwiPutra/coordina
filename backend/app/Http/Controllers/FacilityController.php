<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFacilityRequest;
use App\Models\Facility;
use Illuminate\Http\Request;

class FacilityController extends Controller
{
    public function index()
    {
        $facilities = Facility::with([
            'fixedSchedules.organization',
            'borrowRequests' => function ($query) {
                $query->where('status', 'approved')
                    ->where('date', '>=', now()->toDateString())
                    ->with('organization');
            },
        ])->get();

        return response()->json(['data' => $facilities]);
    }

    public function store(StoreFacilityRequest $request)
    {
        $facility = Facility::create($request->validated());

        return response()->json([
            'message' => 'Fasilitas ' . $facility->name . ' berhasil ditambahkan!',
            'data' => $facility,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin_mpk') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        Facility::findOrFail($id)->delete();

        return response()->json(['message' => 'Fasilitas berhasil dihapus permanen.']);
    }
}
