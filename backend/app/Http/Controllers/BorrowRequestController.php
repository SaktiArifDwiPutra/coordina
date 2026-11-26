<?php

namespace App\Http\Controllers;

use App\Http\Requests\OwnerDecisionRequest;
use App\Http\Requests\ScreenBorrowRequest;
use App\Http\Requests\StoreBorrowRequest;
use App\Models\BorrowRequest;
use App\Services\BorrowWorkflowService;
use Illuminate\Http\Request;

class BorrowRequestController extends Controller
{
    public function __construct(
        private readonly BorrowWorkflowService $workflowService
    ) {}

    public function store(StoreBorrowRequest $request)
    {
        $borrowRequest = $this->workflowService->submit($request->user(), $request->validated());

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
                'histories.actor:id,name,role',
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

    public function show(Request $request, $id)
    {
        $borrowRequest = BorrowRequest::with([
            'organization:id,name',
            'facility:id,name',
            'ownerOrganization:id,name',
            'histories.actor:id,name,role',
        ])->findOrFail($id);

        if ($request->user()->cannot('view', $borrowRequest)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return response()->json(['data' => $borrowRequest]);
    }

    public function screen(ScreenBorrowRequest $request, $id)
    {
        $borrowRequest = BorrowRequest::findOrFail($id);

        if ($request->user()->cannot('screen', $borrowRequest)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $updated = $this->workflowService->screen(
            $borrowRequest,
            $request->user(),
            $request->validated('decision'),
            $request->validated('rejection_reason')
        );

        return response()->json([
            'message' => 'Hasil screening MPK berhasil disimpan.',
            'data' => $updated,
        ]);
    }

    public function ownerDecision(OwnerDecisionRequest $request, $id)
    {
        $borrowRequest = BorrowRequest::findOrFail($id);

        if ($request->user()->cannot('ownerDecision', $borrowRequest)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $updated = $this->workflowService->ownerDecision(
            $borrowRequest,
            $request->user(),
            $request->validated('decision'),
            $request->validated('rejection_reason')
        );

        return response()->json([
            'message' => 'Keputusan pemilik jadwal berhasil dikirim ke MPK.',
            'data' => $updated,
        ]);
    }

    public function finalize(Request $request, $id)
    {
        $borrowRequest = BorrowRequest::findOrFail($id);

        if ($request->user()->cannot('finalize', $borrowRequest)) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $updated = $this->workflowService->finalize($borrowRequest, $request->user());

        return response()->json([
            'message' => 'Keputusan akhir berhasil dicatat oleh MPK.',
            'data' => $updated,
        ]);
    }
}
