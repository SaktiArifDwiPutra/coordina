<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BorrowRequest;
use App\Models\FixedSchedule;
use App\Models\Facility;       // Tambahkan ini
use App\Models\Organization;   // Tambahkan ini
use Carbon\Carbon;

class BorrowRequestController extends Controller
{
public function store(Request $request)
    {
        $user = $request->user();

        // 1. Validasi form HANYA untuk input yang boleh diisi user
        $request->validate([
            'facility_id' => 'required|exists:facilities,id',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'reason' => 'required|string',
        ]);

        // 2. Cek apakah fasilitas ini punya pemilik jadwal tetap di hari itu
        $dayName = \Carbon\Carbon::parse($request->date)->format('l');
        $fixedSchedule = \App\Models\FixedSchedule::where('facility_id', $request->facility_id)
                            ->where('day', $dayName)
                            ->where(function($query) use ($request) {
                                $query->where('start_time', '<', $request->end_time)
                                      ->where('end_time', '>', $request->start_time);
                            })->first();

        // 3. Simpan data secara paksa menggunakan identitas asli dari Token Login (Anti-Hacker)
        $borrowRequest = BorrowRequest::create([
            'user_id' => $user->id,
            'organization_id' => $user->organization_id, // <--- AMBIL OTOMATIS DARI DATABASE!
            'facility_id' => $request->facility_id,
            'owner_organization_id' => $fixedSchedule ? $fixedSchedule->organization_id : null,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason,
            'status' => 'pending_mpk', 
        ]);

        return response()->json(['message' => 'Pengajuan berhasil dikirim.', 'data' => $borrowRequest]);
    }
// Fungsi untuk menampilkan semua data pengajuan ke Dashboard MPK
public function index(Request $request)
    {
        $user = $request->user();
        
        $query = \App\Models\BorrowRequest::with(['organization', 'facility', 'ownerOrganization'])
                        ->orderBy('created_at', 'desc');

        if ($user->role === 'admin_mpk') {
            $requests = $query->get();
        } else {
            // Eskul melihat pengajuannya sendiri ATAU pengajuan yang MENABRAK jadwalnya
            $requests = $query->where('user_id', $user->id)
                              ->orWhere('owner_organization_id', $user->organization_id)
                              ->get();
        }
                        
        return response()->json(['data' => $requests]);
    }

public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $borrowRequest = \App\Models\BorrowRequest::findOrFail($id);
        
        $request->validate(['status' => 'required|string']);

        // 1. Jika yang ngeklik adalah Admin MPK (Bebas ubah)
        if ($user->role === 'admin_mpk') {
            $borrowRequest->update(['status' => $request->status]);
            return response()->json(['message' => 'Status berhasil diubah MPK.']);
        }

        // 2. Jika yang ngeklik adalah Eskul PEMILIK JADWAL
        if ($user->role === 'user' && $borrowRequest->owner_organization_id === $user->organization_id) {
            // Pastikan MPK udah nerusin suratnya ke mereka
            if ($borrowRequest->status === 'pending_owner') {
                $borrowRequest->update(['status' => $request->status]);
                return response()->json(['message' => 'Respon izin berhasil diberikan.']);
            }
        }

        return response()->json(['message' => 'Akses ditolak. Anda bukan pemilik jadwal ini.'], 403);
    }
}