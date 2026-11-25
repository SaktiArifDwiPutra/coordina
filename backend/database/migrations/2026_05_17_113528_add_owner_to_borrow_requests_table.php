<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            // Menyimpan ID eskul pemilik jadwal (boleh kosong kalau jamnya memang free)
            $table->foreignId('owner_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('borrow_requests', function (Blueprint $table) {
            $table->dropForeign(['owner_organization_id']);
            $table->dropColumn('owner_organization_id');
        });
    }
};