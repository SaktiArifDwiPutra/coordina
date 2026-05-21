<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BorrowRequest;
use App\Models\FixedSchedule;
use Carbon\Carbon;

class BorrowRequestController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'reason' => 'required|string',
        ]);

        $dayName = Carbon::parse($request->date)->format('l');

        $fixedSchedule = FixedSchedule::where('facility_id', $request->facility_id)
            ->where('day', $dayName)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);
            })
            ->first();

        $borrowRequest = BorrowRequest::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'facility_id' => $request->facility_id,
            'owner_organization_id' => $fixedSchedule?->organization_id,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason,
            'status' => 'pending_mpk',
        ]);

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim.',
            'data' => $borrowRequest
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = BorrowRequest::query()
            ->with([
                'organization:id,name',
                'facility:id,name',
                'ownerOrganization:id,name'
            ])
            ->latest();

        if ($user->role === 'admin_mpk') {
            $requests = $query->get();
        } else {
            $requests = $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('owner_organization_id', $user->organization_id);
            })->get();
        }

        return response()->json(['data' => $requests]);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|string'
        ]);

        $borrowRequest = BorrowRequest::findOrFail($id);

        if ($user->role === 'admin_mpk') {
            $borrowRequest->update(['status' => $request->status]);

            return response()->json([
                'message' => 'Status berhasil diubah MPK.'
            ]);
        }

        if (
            $user->role === 'user' &&
            $borrowRequest->owner_organization_id === $user->organization_id
        ) {
            if ($borrowRequest->status === 'pending_owner') {
                $borrowRequest->update(['status' => $request->status]);

                return response()->json([
                    'message' => 'Respon izin berhasil diberikan.'
                ]);
            }
        }

        return response()->json([
            'message' => 'Akses ditolak.'
        ], 403);
    }
}