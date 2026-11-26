<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_documents', function (Blueprint $table) {
            $table->string('merged_docx_path')->nullable()->after('document_status');
        });
    }

    public function down(): void
    {
        Schema::table('borrow_documents', function (Blueprint $table) {
            $table->dropColumn('merged_docx_path');
        });
    }
};

