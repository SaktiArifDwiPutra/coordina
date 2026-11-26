<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\FixedSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;

class ScheduleConflictService
{
    public function findOverlappingFixedSchedules(
        int $facilityId,
        string $day,
        string $startTime,
        string $endTime,
        ?int $ignoreScheduleId = null
    ): Collection {
        return FixedSchedule::query()
            ->where('facility_id', $facilityId)
            ->where('day', $day)
            ->when($ignoreScheduleId !== null, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->get();
    }

    public function findOverlappingApprovedBookings(
        int $facilityId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreRequestId = null,
        bool $lockForUpdate = false
    ): Collection {
        $query = BorrowRequest::query()
            ->where('facility_id', $facilityId)
            ->where('date', $date)
            ->where('status', BorrowRequest::STATUS_APPROVED)
            ->when($ignoreRequestId !== null, fn ($q) => $q->where('id', '!=', $ignoreRequestId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function resolveScheduleOwner(
        int $facilityId,
        string $date,
        string $startTime,
        string $endTime,
        int $requesterOrganizationId
    ): ?int {
        $dayName = Carbon::parse($date)->format('l');
        $overlaps = $this->findOverlappingFixedSchedules($facilityId, $dayName, $startTime, $endTime);

        if ($overlaps->isEmpty()) {
            return null;
        }

        $ownerOrgIds = $overlaps->pluck('organization_id')->unique()->values();

        if ($ownerOrgIds->count() > 1) {
            $this->abortJson(
                'Rentang waktu bertabrakan dengan lebih dari satu jadwal tetap organisasi. Ajukan terpisah per slot jadwal.',
                422
            );
        }

        $ownerOrgId = (int) $ownerOrgIds->first();

        if ($ownerOrgId === $requesterOrganizationId) {
            $this->abortJson(
                'Organisasi Anda sudah memiliki hak jadwal tetap pada fasilitas dan waktu tersebut.',
                422
            );
        }

        return $ownerOrgId;
    }

    public function ensureNoApprovedBookingConflict(
        int $facilityId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $ignoreRequestId = null,
        bool $lockForUpdate = false
    ): void {
        $conflicts = $this->findOverlappingApprovedBookings(
            $facilityId,
            $date,
            $startTime,
            $endTime,
            $ignoreRequestId,
            $lockForUpdate
        );

        if ($conflicts->isNotEmpty()) {
            $this->abortJson(
                'Fasilitas sudah memiliki peminjaman yang disetujui pada tanggal dan jam tersebut.',
                409
            );
        }
    }

    public function abortJson(string $message, int $status): never
    {
        throw new HttpResponseException(response()->json(['message' => $message], $status));
    }
}

