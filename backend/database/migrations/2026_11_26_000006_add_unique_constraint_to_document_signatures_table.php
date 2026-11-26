<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safe deduplication before enforcing uniqueness: keep earliest record per (borrow_document_id, revision_number, stage_order)
        $duplicates = DB::table('document_signatures')
            ->select('borrow_document_id', 'revision_number', 'stage_order', DB::raw('MIN(id) as keep_id'))
            ->groupBy('borrow_document_id', 'revision_number', 'stage_order')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('document_signatures')
                ->where('borrow_document_id', $dup->borrow_document_id)
                ->where('revision_number', $dup->revision_number)
                ->where('stage_order', $dup->stage_order)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        Schema::table('document_signatures', function (Blueprint $table) {
            $table->unique(
                ['borrow_document_id', 'revision_number', 'stage_order'],
                'doc_sigs_doc_rev_stage_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropUnique('doc_sigs_doc_rev_stage_unique');
        });
    }
};

