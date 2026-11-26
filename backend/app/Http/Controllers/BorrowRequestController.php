<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBorrowRequest;
use App\Http\Requests\UpdateBorrowRequestStatusRequest;
use App\Models\BorrowRequest;
use App\Models\FixedSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BorrowRequestController extends Controller
{
    public function store(StoreBorrowRequest $request)
    {
        $user = $request->user();

        if ($user->organization_id === null) {
            return response()->json([
                'message' => 'Akun Anda belum terhubung ke organisasi mana pun.',
            ], 422);
        }

        $validated = $request->validated();
        $dayName = Carbon::parse($validated['date'])->format('l');

        $fixedSchedule = FixedSchedule::where('facility_id', $validated['facility_id'])
            ->where('day', $dayName)
            ->where(function ($q) use ($validated) {
                $q->where('start_time', '<', $validated['end_time'])
                    ->where('end_time', '>', $validated['start_time']);
            })
            ->first();

        $borrowRequest = BorrowRequest::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'facility_id' => $validated['facility_id'],
            'owner_organization_id' => $fixedSchedule?->organization_id,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'reason' => $validated['reason'],
            'status' => 'pending_mpk',
        ]);

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim.',
            'data' => $borrowRequest,
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (! in_array($user->role, ['admin_mpk', 'owner_eskul'], true)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $query = BorrowRequest::query()
            ->with([
                'organization:id,name',
                'facility:id,name',
                'ownerOrganization:id,name',
            ])
            ->latest();

        if ($user->role === 'admin_mpk') {
            return response()->json(['data' => $query->get()]);
        }

        $requests = $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);

            if ($user->organization_id !== null) {
                $q->orWhere('organization_id', $user->organization_id)
                    ->orWhere('owner_organization_id', $user->organization_id);
            }
        })->get();

        return response()->json(['data' => $requests]);
    }

    public function updateStatus(UpdateBorrowRequestStatusRequest $request, $id)
    {
        $user = $request->user();
        $newStatus = $request->validated('status');
        $borrowRequest = BorrowRequest::findOrFail($id);

        if ($user->role === 'admin_mpk') {
            if ($borrowRequest->status !== 'pending_mpk') {
                return response()->json([
                    'message' => 'Status permohonan tidak dapat diubah dari tahap ini oleh MPK.',
                ], 422);
            }

            if ($newStatus === 'pending_owner' && $borrowRequest->owner_organization_id === null) {
                return response()->json([
                    'message' => 'Permohonan ini tidak memiliki pemilik jadwal tetap untuk diteruskan.',
                ], 422);
            }

            if ($newStatus === 'approved' && $borrowRequest->owner_organization_id !== null) {
                return response()->json([
                    'message' => 'Jadwal ini dimiliki organisasi lain dan harus diteruskan ke pemilik jadwal.',
                ], 422);
            }

            if (! in_array($newStatus, ['pending_owner', 'approved', 'rejected'], true)) {
                return response()->json(['message' => 'Transisi status tidak valid.'], 422);
            }

            $borrowRequest->update(['status' => $newStatus]);

            return response()->json([
                'message' => 'Status berhasil diubah MPK.',
            ]);
        }

        if (
            $user->role === 'owner_eskul' &&
            $user->organization_id !== null &&
            $borrowRequest->owner_organization_id !== null &&
            (int) $borrowRequest->owner_organization_id === (int) $user->organization_id
        ) {
            if ($borrowRequest->status !== 'pending_owner') {
                return response()->json([
                    'message' => 'Permohonan belum diteruskan ke pemilik jadwal.',
                ], 422);
            }

            if (! in_array($newStatus, ['approved', 'rejected'], true)) {
                return response()->json(['message' => 'Transisi status tidak valid.'], 422);
            }

            $borrowRequest->update(['status' => $newStatus]);

            return response()->json([
                'message' => 'Respon izin berhasil diberikan.',
            ]);
        }

        return response()->json([
            'message' => 'Akses ditolak.',
        ], 403);
    }
}
