<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('is_mpk')->default(false)->after('advisor_name');
            $table->string('logo_path')->nullable()->after('is_mpk');
            $table->string('chairperson_name')->nullable()->after('logo_path');
            $table->string('chairperson_title')->nullable()->after('chairperson_name');
            $table->string('chairperson_id_number')->nullable()->after('chairperson_title');
            $table->string('signature_image_path')->nullable()->after('chairperson_id_number');
            $table->timestamp('signature_updated_at')->nullable()->after('signature_image_path');
        });

        // Mark seeded MPK / OSIS organization automatically if present
        DB::table('organizations')
            ->where('name', 'like', '%MPK%')
            ->update(['is_mpk' => true]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'is_mpk',
                'logo_path',
                'chairperson_name',
                'chairperson_title',
                'chairperson_id_number',
                'signature_image_path',
                'signature_updated_at',
            ]);
        });
    }
};

