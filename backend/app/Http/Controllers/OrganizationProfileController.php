<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationProfileRequest;
use App\Http\Requests\UploadOrganizationAssetsRequest;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrganizationProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $user->ensureMpkOrganizationLinked();

        if ($user->organization_id === null) {
            return response()->json(['message' => 'Akun belum terhubung ke organisasi.'], 422);
        }

        $org = Organization::findOrFail($user->organization_id);

        return response()->json([
            'data' => $this->formatProfile($org),
        ]);
    }

    public function update(UpdateOrganizationProfileRequest $request)
    {
        $user = $request->user();
        $user->ensureMpkOrganizationLinked();
        $org = Organization::findOrFail($user->organization_id);
        $org->update($request->validated());

        return response()->json([
            'message' => 'Profil organisasi berhasil diperbarui.',
            'data' => $this->formatProfile($org->fresh()),
        ]);
    }

    public function uploadAssets(UploadOrganizationAssetsRequest $request)
    {
        $user = $request->user();
        $user->ensureMpkOrganizationLinked();
        $org = Organization::findOrFail($user->organization_id);

        if (! $request->hasFile('logo') && ! $request->hasFile('signature_image')) {
            return response()->json(['message' => 'Pilih file logo atau gambar tanda tangan untuk diunggah.'], 422);
        }

        $updates = [];

        if ($request->hasFile('logo')) {
            $ext = $request->file('logo')->getClientOriginalExtension();
            $path = $request->file('logo')->storeAs("organizations/{$org->id}", 'logo_' . time() . ".{$ext}", 'local');
            $updates['logo_path'] = $path;
        }

        if ($request->hasFile('signature_image')) {
            $ext = $request->file('signature_image')->getClientOriginalExtension();
            $path = $request->file('signature_image')->storeAs("organizations/{$org->id}", 'signature_' . time() . ".{$ext}", 'local');
            $updates['signature_image_path'] = $path;
            $updates['signature_updated_at'] = now();
        }

        $org->update($updates);

        return response()->json([
            'message' => 'Aset organisasi berhasil diunggah.',
            'data' => $this->formatProfile($org->fresh()),
        ]);
    }

    public function streamLogo(int $id)
    {
        $org = Organization::findOrFail($id);

        if (! $org->logo_path || ! Storage::disk('local')->exists($org->logo_path)) {
            return response()->json(['message' => 'Logo tidak ditemukan.'], 404);
        }

        return Storage::disk('local')->response($org->logo_path);
    }

    public function streamSignature(Request $request, int $id)
    {
        $user = $request->user();
        $org = Organization::findOrFail($id);

        if ($user->role !== 'admin_mpk' && (int) $user->organization_id !== (int) $org->id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        if (! $org->signature_image_path || ! Storage::disk('local')->exists($org->signature_image_path)) {
            return response()->json(['message' => 'Tanda tangan tidak ditemukan.'], 404);
        }

        return Storage::disk('local')->response(
            $org->signature_image_path,
            null,
            ['Cache-Control' => 'no-store, private']
        );
    }

    private function formatProfile(Organization $org): array
    {
        return [
            'id' => $org->id,
            'name' => $org->name,
            'advisor_name' => $org->advisor_name,
            'is_mpk' => $org->is_mpk,
            'chairperson_name' => $org->chairperson_name,
            'chairperson_title' => $org->chairperson_title,
            'chairperson_id_number' => $org->chairperson_id_number,
            'has_logo' => ! empty($org->logo_path) && Storage::disk('local')->exists($org->logo_path),
            'has_signature' => ! empty($org->signature_image_path) && Storage::disk('local')->exists($org->signature_image_path),
            'is_complete_for_signing' => $org->isProfileCompleteForSigning(),
            'signature_updated_at' => $org->signature_updated_at,
        ];
    }
}

