<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_schedules', function (Blueprint $table) {
            $table->index(['facility_id', 'day', 'start_time', 'end_time'], 'fixed_schedules_slot_idx');
        });

        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->string('workflow_type', 32)->default('inter_eskul')->after('owner_organization_id');
            $table->timestamp('mpk_screened_at')->nullable()->after('rejection_reason');
            $table->timestamp('owner_decided_at')->nullable()->after('mpk_screened_at');
            $table->timestamp('finalized_at')->nullable()->after('owner_decided_at');

            $table->index('workflow_type', 'borrow_requests_workflow_type_idx');
            $table->index(['facility_id', 'date', 'status'], 'borrow_requests_slot_idx');
        });

        // Normalize legacy pending_mpk rows safely
        DB::table('borrow_requests')
            ->where('status', 'pending_mpk')
            ->update(['status' => 'pending_mpk_screening']);
    }

    public function down(): void
    {
        DB::table('borrow_requests')
            ->where('status', 'pending_mpk_screening')
            ->update(['status' => 'pending_mpk']);

        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropIndex('borrow_requests_workflow_type_idx');
            $table->dropIndex('borrow_requests_slot_idx');
            $table->dropColumn(['workflow_type', 'mpk_screened_at', 'owner_decided_at', 'finalized_at']);
        });

        Schema::table('fixed_schedules', function (Blueprint $table) {
            $table->dropIndex('fixed_schedules_slot_idx');
        });
    }
};

