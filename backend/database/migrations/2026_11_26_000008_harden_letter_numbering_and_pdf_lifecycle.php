<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        $existing = DB::table('borrow_requests')
            ->whereNotNull('letter_number')
            ->orderBy('id')
            ->get(['id', 'letter_number', 'letter_date']);

        $seenNumbers = [];
        $maxSeqByYear = [];

        foreach ($existing as $row) {
            $year = $row->letter_date ? (int) substr((string) $row->letter_date, 0, 4) : (int) date('Y');
            $seq = 0;
            if (preg_match('/^(\d+)\//', (string) $row->letter_number, $matches)) {
                $seq = (int) $matches[1];
            }

            if (isset($seenNumbers[$row->letter_number])) {
                $nextSeq = ($maxSeqByYear[$year] ?? 0) + 1;
                $newNumber = sprintf('%03d/MPK/%d', $nextSeq, $year);
                while (isset($seenNumbers[$newNumber])) {
                    $nextSeq++;
                    $newNumber = sprintf('%03d/MPK/%d', $nextSeq, $year);
                }
                DB::table('borrow_requests')->where('id', $row->id)->update(['letter_number' => $newNumber]);
                $seenNumbers[$newNumber] = true;
                $maxSeqByYear[$year] = max($maxSeqByYear[$year] ?? 0, $nextSeq);
            } else {
                $seenNumbers[$row->letter_number] = true;
                $maxSeqByYear[$year] = max($maxSeqByYear[$year] ?? 0, $seq);
            }
        }

        foreach ($maxSeqByYear as $year => $maxSeq) {
            DB::table('letter_counters')->insert([
                'year' => $year,
                'last_sequence' => $maxSeq,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->unique('letter_number', 'borrow_requests_letter_number_unique');
        });

        Schema::table('borrow_documents', function (Blueprint $table) {
            $table->text('pdf_generation_error')->nullable()->after('final_pdf_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('borrow_documents', function (Blueprint $table) {
            $table->dropColumn('pdf_generation_error');
        });

        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropUnique('borrow_requests_letter_number_unique');
        });

        Schema::dropIfExists('letter_counters');
    }
};

